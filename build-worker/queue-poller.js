// File: build-worker/queue-poller.js

const { exec } = require('child_process');
const fs = require('fs-extra');
const path = require('path');
const crypto = require('crypto');

// Prefer Docker network host when running inside containers
// Can be overridden via env var API_BASE
const API_BASE = process.env.API_BASE || 'http://nginx';
const WORKER_SECRET = process.env.WORKER_SECRET || 'seu-segredo-super-secreto';
const BUILD_WORKSPACE = path.join(__dirname, 'workspace');
const PUBLIC_APPS_DIR = path.resolve(__dirname, '../public/apps');

// Minimal default entrypoint to avoid empty main.dart builds
const DEFAULT_MAIN_DART = "import 'package:flutter/material.dart';\nvoid main()=>runApp(const MaterialApp(home: Scaffold(body: Center(child: Text('Hello')))));\n";

async function claimJob() {
  try {
    const resp = await fetch(`${API_BASE}/api/build-queue/claim`, { method: 'POST', headers: { 'X-Worker-Secret': WORKER_SECRET } });
    if (resp.status === 204) return null;
    const data = await resp.json();
    if (!data || !data.success) return null;
    return data.data;
  } catch (_) { return null; }
}

async function updateQueueJob(jobId, appId, status, log, filePath) {
  try {
    const url = `${API_BASE}/api/build-queue/update/${jobId}`;
    const body = { app_id: Number(appId), status, log, platform: 'web', build_version: '1.0.0' };
    if (filePath) body.file_path = filePath;
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Worker-Secret': WORKER_SECRET }, body: JSON.stringify(body) });
    if (!r.ok) {
      const t = await r.text().catch(() => '');
      console.error(`[Job ${jobId}] API update returned ${r.status}: ${t.slice(0,300)}`);
    }
  } catch (e) {
    console.error(`[Job ${jobId}] Falha ao atualizar status da fila:`, e?.message || e);
  }
}

function executeCommand(command, cwd) {
  return new Promise((resolve, reject) => {
    // Increase buffer to handle Flutter verbose output
    exec(command, { cwd, maxBuffer: 1024 * 1024 * 20 }, (error, stdout, stderr) => {
      if (error) {
        reject(new Error(stdout + '\n' + stderr));
        return;
      }
      resolve({ stdout, stderr });
    });
  });
}

async function ensurePubspecDeps(projectDir, slug) {
  const pubspecPath = path.join(projectDir, 'pubspec.yaml');
  try {
    let content = await fs.readFile(pubspecPath, 'utf8');
    const lines = content.split(/\r?\n/);
    const ensureLine = (needle) => lines.some(l => l.trim().startsWith(needle));

    // Ensure name
    if (!/^name:\s/m.test(content)) {
      lines.unshift(`name: ${slug.replace(/-/g, '_')}`);
    }

    // Ensure dependencies section exists
    if (!/^[^\S\n]*dependencies:\s*$/m.test(content)) {
      if (!ensureLine('dependencies:')) lines.push('dependencies:');
    }

    const addDep = (key, valueLines) => {
      if (!lines.some(l => l.trim().startsWith(`${key}:`))) {
        const idx = lines.findIndex(l => /^dependencies:\s*$/.test(l.trim()));
        if (idx !== -1) {
          lines.splice(idx + 1, 0, ...valueLines.map(v => `  ${v}`));
        } else {
          lines.push('dependencies:');
          lines.push(...valueLines.map(v => `  ${v}`));
        }
      }
    };

    addDep('http', ['http: ^1.1.0']);
    addDep('provider', ['provider: ^6.0.5']);

    // Workz SDK as a local path dependency
    if (!/\bworkz_sdk:\b/.test(content)) {
      const relPath = path.relative(projectDir, path.resolve(__dirname, '../packages/workz_sdk')).replace(/\\/g, '/');
      const yamlBlock = [`workz_sdk:`, `  path: ${relPath}`];
      addDep('workz_sdk', yamlBlock);
    }

    content = lines.join('\n');
    await fs.writeFile(pubspecPath, content);
  } catch (e) {
    // ignore; use default pubspec
  }
}

async function processQueueJob(job) {
  const appId = job.app_id;
  const slug = job.slug;
  const codeSource = { dart_code: job.dart_code, files: job.files };

  // Plataformas alvo para este job.
  // v1: lemos de job.platforms (string "web,android") quando existir; caso contrário, default ['web'].
  let platforms = ['web'];
  try {
    if (job.platforms && typeof job.platforms === 'string') {
      const parts = job.platforms.split(',').map(p => String(p || '').trim().toLowerCase()).filter(Boolean);
      if (parts.length > 0) {
        platforms = Array.from(new Set(parts));
      }
    }
  } catch (_) {
    // fallback silencioso para ['web']
  }

  try {
    const dc = typeof codeSource.dart_code === 'string' ? codeSource.dart_code : '';
    const dcLen = dc.length;
    const dcHash = dcLen > 0 ? crypto.createHash('sha1').update(dc).digest('hex').slice(0, 12) : 'none';
    const filesCount = codeSource.files && typeof codeSource.files === 'object' ? Object.keys(codeSource.files).length : 0;
    console.log(`[queue:${job.job_id}] payload: dart_code_len=${dcLen} sha1=${dcHash} files=${filesCount} platforms=${platforms.join(',')}`);
  } catch (_) { /* ignore */ }

  const tempBuildDir = path.join(BUILD_WORKSPACE, slug);

  console.log(`[queue:${job.job_id}] Iniciando build do app ${appId} (${slug})...`);
  await updateQueueJob(job.job_id, appId, 'building', 'Preparando ambiente...');

  try {
    // Preflight: ensure Flutter CLI is available
    try {
      await executeCommand('flutter --version', process.cwd());
    } catch (e) {
      const msg = "Flutter CLI não encontrado no PATH. Instale o Flutter e reinicie o worker.";
      console.error(`[queue:${job.job_id}] ${msg}`);
      await updateQueueJob(job.job_id, appId, 'failed', msg);
      return;
    }

    console.log(`[queue:${job.job_id}] ensureDir workspace`);
    await fs.ensureDir(tempBuildDir);
  console.log(`[queue:${job.job_id}] flutter create .`);
  try {
    const raw = String(slug || `app_${appId || job.app_id || 'x'}`).toLowerCase();
    const name = raw.replace(/[^a-z0-9_]/g,'_').replace(/_{2,}/g,'_');
    const safe = /^[a-z_]/.test(name) ? name : `p_${name}`;
    await executeCommand(`flutter create . --project-name ${safe}`, tempBuildDir);
  } catch (e) {
    await executeCommand('flutter create .', tempBuildDir);
  }

    if (codeSource.files && typeof codeSource.files === 'object') {
      console.log(`[queue:${job.job_id}] inject files`);
      const provided = Object.keys(codeSource.files);
      for (const filePath of provided) {
        const content = codeSource.files[filePath];
        const fullPath = path.join(tempBuildDir, filePath);
        await fs.ensureDir(path.dirname(fullPath));
        await fs.writeFile(fullPath, content);
      }
      // Priority: textarea (dart_code) always wins; otherwise ensure lib/main.dart exists
      const libMain = path.join(tempBuildDir, 'lib', 'main.dart');
      const hasDartCode = codeSource.dart_code && String(codeSource.dart_code).trim().length > 0;
      if (hasDartCode) {
        await fs.ensureDir(path.dirname(libMain));
        await fs.writeFile(libMain, codeSource.dart_code);
        console.log(`[queue:${job.job_id}] wrote lib/main.dart from dart_code (priority)`);
      } else {
        // Ensure Flutter entry at lib/main.dart even if builder uses src/main.dart or main.dart
        const hasLibMain = await fs.pathExists(libMain);
        if (!hasLibMain) {
          let candidate = null;
          if (provided.includes('src/main.dart')) candidate = 'src/main.dart';
          else if (provided.includes('main.dart')) candidate = 'main.dart';
          else {
            candidate = provided.find(p => /(^|\/)main\.dart$/i.test(p)) || null;
          }
          if (candidate) {
            const content = codeSource.files[candidate];
            await fs.ensureDir(path.dirname(libMain));
            await fs.writeFile(libMain, content);
            console.log(`[queue:${job.job_id}] normalized ${candidate} -> lib/main.dart`);
          } else {
            await fs.ensureDir(path.dirname(libMain));
            await fs.writeFile(libMain, DEFAULT_MAIN_DART);
            console.log(`[queue:${job.job_id}] wrote default lib/main.dart (no code provided)`);
          }
        }
      }
    } else {
      console.log(`[queue:${job.job_id}] write pubspec + main.dart`);
      const pubspecContent = `\nname: ${slug.replace(/-/g, '_')}\ndescription: Um novo aplicativo Flutter criado pela Workz Platform.\nversion: 1.0.0+1\nenvironment:\n  sdk: '>=3.0.0 <4.0.0'\ndependencies:\n  flutter:\n    sdk: flutter\n  http: ^1.1.0\n  provider: ^6.0.5\n`;
      await fs.writeFile(path.join(tempBuildDir, 'pubspec.yaml'), pubspecContent);
      const mainDartPath = path.join(tempBuildDir, 'lib', 'main.dart');
      const code = (codeSource.dart_code && String(codeSource.dart_code).trim().length > 0)
        ? codeSource.dart_code
        : DEFAULT_MAIN_DART;
      await fs.ensureDir(path.dirname(mainDartPath));
      await fs.writeFile(mainDartPath, code);
    }

    await updateQueueJob(job.job_id, appId, 'building', 'Código injetado, iniciando compilação...');
  console.log(`[queue:${job.job_id}] ensure pubspec deps`);
  await ensurePubspecDeps(tempBuildDir, slug);
  console.log(`[queue:${job.job_id}] flutter pub get`);
  await executeCommand('flutter pub get', tempBuildDir);
  // Pré-validação: análise estática
  console.log(`[queue:${job.job_id}] dart analyze`);
  let analyzeOutput = '';
  try {
    const { stdout, stderr } = await executeCommand('dart analyze', tempBuildDir);
    analyzeOutput = (stdout || '') + '\n' + (stderr || '');
  } catch (anErr) {
    analyzeOutput = String((anErr && anErr.message) ? anErr.message : anErr);
  }
  const hasErrors = (() => {
    try {
      const txt = String(analyzeOutput || '').toLowerCase();
      const lines = txt.split(/\r?\n/);
      let count = 0;
      for (const ln of lines) {
        if (!ln) continue;
        if (ln.includes(' error • ') || /\berror:\b/.test(ln)) count++;
      }
      const m = txt.match(/(\d+)\s+issues?\s+found\s*\(([^)]*)\)/);
      if (m && m[2] && /error/.test(m[2])) { count = Math.max(count, 1); }
      return count > 0;
    } catch (_) { return false; }
  })();
  if (hasErrors) {
    await updateQueueJob(job.job_id, appId, 'failed', 'Análise estática falhou:\n' + analyzeOutput);
    console.log(`[queue:${job.job_id}] analyze failed -> aborting build`);
    await fs.remove(tempBuildDir);
    return;
  } else {
    console.log(`[queue:${job.job_id}] analyze ok (sem erros; avisos permitidos)`);
  }
    let combinedLog = '';

    // Sempre construir Web quando solicitado
    if (platforms.includes('web')) {
      const baseHref = `/apps/flutter/${appId}/web/`;
      console.log(`[queue:${job.job_id}] flutter build web --release --base-href=${baseHref}`);
      const { stdout, stderr } = await executeCommand(`flutter build web --release --base-href=${baseHref}`, tempBuildDir);
      combinedLog += stdout + '\n' + stderr;

      const sourceArtifacts = path.join(tempBuildDir, 'build', 'web');
      // New canonical path for web artifacts: /public/apps/flutter/<appId>/web
      const destArtifacts = path.join(PUBLIC_APPS_DIR, 'flutter', String(appId), 'web');
      console.log(`[queue:${job.job_id}] copy web artifacts`);
      await fs.remove(destArtifacts).catch(() => {});
      await fs.ensureDir(destArtifacts);
      await fs.copy(sourceArtifacts, destArtifacts);

      const stamp = {
        job_id: job.job_id,
        app_id: appId,
        slug,
        completed_at: new Date().toISOString(),
        api_base: API_BASE,
        worker_pid: process.pid,
        platforms,
      };
      try {
        await fs.writeFile(path.join(destArtifacts, 'workz-build.json'), JSON.stringify(stamp, null, 2));
      } catch(_) {}
    }

    // Build Android (APK) quando incluído nas plataformas
    if (platforms.includes('android')) {
      const androidCommand = 'flutter build apk --debug --no-shrink';
      console.log(`[queue:${job.job_id}] ${androidCommand}`);
      const { stdout, stderr } = await executeCommand(androidCommand, tempBuildDir);
      combinedLog += '\n\n=== ANDROID BUILD ===\n' + stdout + '\n' + stderr;

      const sourceApk = path.join(tempBuildDir, 'build', 'app', 'outputs', 'flutter-apk', 'app-debug.apk');
      const destDir = path.join(PUBLIC_APPS_DIR, 'flutter', String(appId), 'android');
      await fs.ensureDir(destDir);
      if (await fs.pathExists(sourceApk)) {
        const safeSlug = (slug || `app_${appId || 'x'}`).replace(/[^a-zA-Z0-9_-]/g, '_');
        const destApk = path.join(destDir, `${safeSlug}-debug.apk`);
        console.log(`[queue:${job.job_id}] copy android artifact -> ${destApk}`);
        await fs.copy(sourceApk, destApk);
      } else {
        console.warn(`[queue:${job.job_id}] APK não encontrado em ${sourceApk}`);
      }
    }

    const finalLog = combinedLog || 'Build concluído sem saída de log.';
    const webPath = `/apps/flutter/${appId}/web/`;
    console.log(`[queue:${job.job_id}] success -> ${webPath} (platforms=${platforms.join(',')})`);
    await updateQueueJob(job.job_id, appId, 'success', finalLog, webPath);
  } catch (error) {
    console.error(`[queue:${job.job_id}] build failed:`, error?.message || error);
    await updateQueueJob(job.job_id, appId, 'failed', error?.message || String(error));
  } finally {
    console.log(`[queue:${job.job_id}] cleanup`);
    await fs.remove(tempBuildDir);
  }
}

async function tick() {
  try {
    const job = await claimJob();
    if (job) { await processQueueJob(job); }
  } catch (_) {}
  finally { setTimeout(tick, 1500); }
}

(async () => {
  try { await fs.ensureDir(BUILD_WORKSPACE); } catch(_){}
  console.log(`[worker] Queue poller iniciado. API_BASE=${API_BASE}`);
  setTimeout(tick, 1000);
})();
