// File: build-worker/index.js

const express = require('express');
const { exec } = require('child_process');
const fs = require('fs-extra'); // Usamos fs-extra para facilitar a cópia de diretórios
const path = require('path');
const crypto = require('crypto');
const os = require('os');

const app = express();
app.use(express.json({ limit: '5mb' }));
// Lightweight CORS to allow direct access from different origins (e.g., 9090 -> 9091)
app.use((req, res, next) => {
  try {
    const origin = req.headers.origin || '*';
    res.header('Access-Control-Allow-Origin', origin);
    res.header('Vary', 'Origin');
    res.header('Access-Control-Allow-Credentials', 'true');
    res.header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    if (req.method === 'OPTIONS') { return res.sendStatus(204); }
  } catch (_) { /* ignore */ }
  next();
});

const PORT = 9091; // Rodando em uma porta diferente da sua API principal

// --- CONFIGURAÇÃO ---
// Diretório onde os projetos Flutter serão temporariamente criados para build
const BUILD_WORKSPACE = path.join(__dirname, 'workspace');
// Diretório público final onde os apps compilados serão servidos
const PUBLIC_APPS_DIR = path.resolve(__dirname, '../public/apps');

// Diretório de pré-visualizações (servido via /preview/<token>/)
const PREVIEW_ROOT = path.join(BUILD_WORKSPACE, 'preview');

// Minimal default entrypoint to avoid empty main.dart builds
const DEFAULT_MAIN_DART = "import 'package:flutter/material.dart';\nvoid main()=>runApp(const MaterialApp(home: Scaffold(body: Center(child: Text('Hello')))));\n";
const ANDROID_DART_INJECTION = process.env.ANDROID_DART_INJECTION || '';
const BUILD_COMMAND_TIMEOUT_MS = Number(process.env.BUILD_COMMAND_TIMEOUT_MS || 600000);
const BUILD_APK_TIMEOUT_SECS = Number(process.env.BUILD_APK_TIMEOUT_SECS || 600);

async function ensureBuildLogDir(tempBuildDir) {
  const logDir = path.join(tempBuildDir, 'build-logs');
  await fs.ensureDir(logDir);
  return logDir;
}

async function appendBuildLog(tempBuildDir, platform, entry) {
  try {
    const logDir = await ensureBuildLogDir(tempBuildDir);
    const filePath = path.join(logDir, `${platform}.log`);
    const timestamp = new Date().toISOString();
    const formatted = `${timestamp} | ${entry}\n\n`;
    await fs.appendFile(filePath, formatted);
  } catch (e) {
    console.warn(`[build-log] Falha ao escrever log (${platform}): ${e.message || e}`);
  }
}

async function cleanAndroidGradleCache(tempBuildDir, slug) {
  try {
    const homeDir = os.homedir ? os.homedir() : (process.env.HOME || process.env.USERPROFILE);
    if (homeDir) {
      const gradleDir = path.join(homeDir, '.gradle');
      if (await fs.pathExists(gradleDir)) {
        await fs.remove(gradleDir);
        console.log(`[${slug}] Removed global Gradle dir: ${gradleDir}`);
      }
    }
    const androidGradleDir = path.join(tempBuildDir, 'android', '.gradle');
    if (await fs.pathExists(androidGradleDir)) {
      await fs.remove(androidGradleDir);
      console.log(`[${slug}] Removed local Android .gradle dir`);
    }
    const androidBuildDir = path.join(tempBuildDir, 'android', 'build');
    if (await fs.pathExists(androidBuildDir)) {
      await fs.remove(androidBuildDir);
      console.log(`[${slug}] Removed local Android build dir`);
    }
    const dartToolAndroid = path.join(tempBuildDir, '.dart_tool', 'android');
    if (await fs.pathExists(dartToolAndroid)) {
      await fs.remove(dartToolAndroid);
      console.log(`[${slug}] Removed .dart_tool/android cache`);
    }
  } catch (e) {
    console.warn(`[${slug}] Falha ao limpar cache Gradle:`, e.message || e);
  }
}

async function applyAndroidDartInjection(tempBuildDir, slug) {
  try {
    if (!ANDROID_DART_INJECTION) return;
    const targetPath = path.join(tempBuildDir, 'lib', 'main.dart');
    if (!await fs.pathExists(targetPath)) return;
    const current = await fs.readFile(targetPath, 'utf8');
    if (current.includes('// Workz Android injection')) return;
    await fs.appendFile(targetPath, `\n// Workz Android injection\n${ANDROID_DART_INJECTION}\n`);
    console.log(`[${slug}] Android dart injection aplicado.`);
  } catch (e) {
    console.warn(`[${slug}] Falha ao aplicar Android Dart injection:`, e.message || e);
  }
}

async function disableAndroidMinify(tempBuildDir, slug) {
  try {
    const appGradle = path.join(tempBuildDir, 'android', 'app', 'build.gradle');
    if (!await fs.pathExists(appGradle)) return;
    let content = await fs.readFile(appGradle, 'utf8');
    // Força minifyEnabled/shrinkResources para false no build release
    content = content.replace(/minifyEnabled\s+true/g, 'minifyEnabled false');
    if (!/shrinkResources\s+false/.test(content)) {
      content = content.replace(/(minifyEnabled\s+false)/, '$1\n            shrinkResources false');
    } else {
      content = content.replace(/shrinkResources\s+true/g, 'shrinkResources false');
    }
    await fs.writeFile(appGradle, content);
    console.log(`[${slug}] minify/shrink desabilitados para release (APK).`);
  } catch (e) {
    console.warn(`[${slug}] Falha ao desabilitar minify/shrink:`, e.message || e);
  }
}

// Ensure Flutter Web toolchain is ready (run once, best-effort)
let __flutterReady = false;
async function ensureFlutterWebReady() {
  if (__flutterReady) return;
  try {
    await executeCommand('flutter --version', process.cwd());
    await executeCommand('flutter config --enable-web', process.cwd());
    await executeCommand('flutter precache --web', process.cwd());
  } catch (e) {
    console.warn('[worker] Flutter preflight warning:', e && e.message ? e.message : String(e));
  }
  __flutterReady = true;
}

/**
 * Endpoint para iniciar o processo de build de um app Flutter.
 * Ex: POST http://localhost:9091/build/1012
 */
app.post('/build/:appId', (req, res) => {
    const appId = req.params.appId;
    const { dart_code, slug, files, platforms } = req.body; // Agora pode receber 'files' e 'platforms'

    // Valida se tem 'slug', 'appId' e pelo menos uma forma de código ('dart_code' ou 'files')
    if (!appId || !slug || (!dart_code && !files)) {
        return res.status(400).json({ success: false, message: 'appId, slug e (dart_code ou files) são obrigatórios.' });
    }

    // Responde imediatamente para o cliente não ficar esperando o build terminar.
    res.status(202).json({ success: true, message: `Build para o app ${appId} iniciado.` });

    // Inicia o processo de build em segundo plano (sem 'await')
    runBuildProcess(appId, slug, { dart_code, files, platforms });
});

// Valid Dart package name helper (lowercase, digits, underscores; must start with letter or underscore)
function toDartPackageName(input, fallbackPrefix = 'p') {
    try {
        let s = String(input || '').toLowerCase();
        s = s.replace(/[^a-z0-9_]/g, '_');
        s = s.replace(/_{2,}/g, '_');
        if (!/^[a-z_]/.test(s) || s.length === 0) s = `${fallbackPrefix}_${s}`;
        return s;
    } catch(_) { return `${fallbackPrefix}_app`; }
}

/**
 * Função principal que orquestra o processo de build.
 */
async function runBuildProcess(appId, slug, codeSource) {
    await ensureFlutterWebReady();
    const tempBuildDir = path.join(BUILD_WORKSPACE, slug);

    console.log(`[${slug}] Iniciando build...`);
    try {
        const dc = codeSource && typeof codeSource.dart_code === 'string' ? codeSource.dart_code : '';
        const dcLen = dc.length;
        const dcHash = dcLen > 0 ? crypto.createHash('sha1').update(dc).digest('hex').slice(0, 12) : 'none';
        const filesCount = codeSource && codeSource.files && typeof codeSource.files === 'object' ? Object.keys(codeSource.files).length : 0;
        const platforms = Array.isArray(codeSource.platforms) && codeSource.platforms.length
            ? Array.from(new Set(codeSource.platforms.map(p => String(p || '').toLowerCase())))
            : ['web'];
        console.log(`[${slug}] payload: dart_code_len=${dcLen} sha1=${dcHash} files=${filesCount} platforms=${platforms.join(',')}`);
    } catch (_) { /* ignore diagnostics */ }
    await updateBuildStatus(appId, 'building', 'Preparando ambiente...', { platform: 'web' });

    try {
        // 1. Preparar o ambiente de build
        await fs.ensureDir(tempBuildDir);
        await appendBuildLog(tempBuildDir, 'workflow', 'Workspace preparado e pronto para build.');
        const projectName = toDartPackageName(slug || `app_${appId}`);
        await executeCommand(`flutter create . --project-name ${projectName}`, tempBuildDir);
        await disableAndroidMinify(tempBuildDir, slug);
        
        // 2. Injetar o código do usuário (lógica nova)
        if (codeSource.files && typeof codeSource.files === 'object') {
            console.log(`[${slug}] Injetando múltiplos arquivos...`);
            const provided = Object.keys(codeSource.files);
            for (const filePath of provided) {
                const content = codeSource.files[filePath];
                const fullPath = path.join(tempBuildDir, filePath);
                // Garante que o diretório do arquivo exista
                await fs.ensureDir(path.dirname(fullPath));
                await fs.writeFile(fullPath, content);
            }
            // Garantir ponto de entrada lib/main.dart a partir dos arquivos fornecidos
            const libMain = path.join(tempBuildDir, 'lib', 'main.dart');
            let hasLibMain = await fs.pathExists(libMain);
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
                    console.log(`[${slug}] normalizado ${candidate} -> lib/main.dart`);
                    hasLibMain = true;
                }
            }
            // Fallback: se ainda não há lib/main.dart e veio dart_code, cria-o
            if (!hasLibMain && codeSource.dart_code && String(codeSource.dart_code).trim().length > 0) {
                await fs.ensureDir(path.dirname(libMain));
                await fs.writeFile(libMain, codeSource.dart_code);
                console.log(`[${slug}] fallback dart_code -> lib/main.dart`);
            }
            // Ensure textarea (dart_code) takes priority even when files provided
            if (codeSource.dart_code && String(codeSource.dart_code).trim().length > 0) {
                await fs.ensureDir(path.dirname(libMain));
                await fs.writeFile(libMain, codeSource.dart_code);
                console.log(`[${slug}] wrote lib/main.dart from dart_code (priority override)`);
            }
            console.log(`[${slug}] Todos os arquivos foram injetados.`);
        } else {
            // Lógica antiga (fallback) para apps com um único arquivo
            console.log(`[${slug}] Injetando código único (dart_code)...`);
            const pubspecContent = `
name: ${slug.replace(/-/g, '_')}
description: Um novo aplicativo Flutter criado pela Workz Platform.
version: 1.0.0+1
environment:
  sdk: '>=3.0.0 <4.0.0'
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  provider: ^6.0.5
`;
            await fs.writeFile(path.join(tempBuildDir, 'pubspec.yaml'), pubspecContent);
            console.log(`[${slug}] pubspec.yaml de fallback criado.`);

            const mainDartPath = path.join(tempBuildDir, 'lib', 'main.dart');
            const code = (codeSource.dart_code && String(codeSource.dart_code).trim().length > 0)
              ? codeSource.dart_code
              : DEFAULT_MAIN_DART;
            await fs.ensureDir(path.dirname(mainDartPath));
            await fs.writeFile(mainDartPath, code);
            console.log(`[${slug}] Código injetado em lib/main.dart.`);
        }

        await updateBuildStatus(appId, 'building', 'Código injetado, iniciando compilação...', { platform: 'web' });
        // Ensure pubspec has required dependencies before fetching
        await ensurePubspecDeps(tempBuildDir, slug);

        // 3. Executar o 'flutter pub get' para garantir as dependências
        await executeCommand('flutter pub get', tempBuildDir);

        // 3.1 Pré‑validação: análise estática (falha somente em ERROS; avisos não bloqueiam)
        await updateBuildStatus(appId, 'building', 'Executando análise estática (dart analyze)...');
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
                // Conta ocorrências de " error • " ou linhas marcadas como erro
                const lines = txt.split(/\r?\n/);
                let count = 0;
                for (const ln of lines) {
                    if (!ln) continue;
                    if (ln.includes(' error • ') || ln.match(/\berror:\b/)) count++;
                }
                // Também tenta pegar o resumo do analyzer quando presente
                const m = txt.match(/(\d+)\s+issues?\s+found\s*\(([^)]*)\)/);
                if (m && m[2] && /error/.test(m[2])) { count = Math.max(count, 1); }
                return count > 0;
            } catch (_) { return false; }
        })();
        if (hasErrors) {
            console.warn(`[${slug}] dart analyze encontrou erros. Abortando build.`);
            await updateBuildStatus(appId, 'failed', 'Análise estática falhou:\n' + analyzeOutput);
            return; // encerra cedo; finally limpará o workspace
        } else {
            console.log(`[${slug}] dart analyze: sem erros (avisos não bloqueiam).`);
        }

        // 4. Executar os builds solicitados
        const platforms = Array.isArray(codeSource.platforms) && codeSource.platforms.length
            ? Array.from(new Set(codeSource.platforms.map(p => String(p || '').toLowerCase())))
            : ['web'];

        // 4.1 Build Web (sempre que solicitado)
        if (platforms.includes('web')) {
            try {
                const baseHref = `/apps/flutter/${appId}/web/`;
                const webCommand = `flutter build web --release --base-href=${baseHref}`;
                await appendBuildLog(tempBuildDir, 'web', `COMMAND: ${webCommand}`);
                const { stdout, stderr } = await executeCommand(webCommand, tempBuildDir);
                const buildLog = stdout + '\n' + stderr;
                await appendBuildLog(tempBuildDir, 'web', `${webCommand}\n${buildLog}`);
                console.log(`[${slug}] Build Web finalizado.`);

                const sourceArtifacts = path.join(tempBuildDir, 'build', 'web');
                // Canonical path: /public/apps/flutter/<appId>/web
                const destArtifacts = path.join(PUBLIC_APPS_DIR, 'flutter', String(appId), 'web');
                
                await fs.remove(destArtifacts).catch(() => {});
                await fs.ensureDir(destArtifacts);
                await fs.copy(sourceArtifacts, destArtifacts);
                // Build stamp to confirm update times regardless of FS timestamp quirks
                try {
                    const stamp = { app_id: appId, slug, completed_at: new Date().toISOString(), worker_pid: process.pid };
                    await fs.writeFile(path.join(destArtifacts, 'workz-build.json'), JSON.stringify(stamp, null, 2));
                } catch(_) {}
                console.log(`[${slug}] Artefatos Web copiados para ${destArtifacts}.`);

                await updateBuildStatus(appId, 'success', buildLog, {
                    platform: 'web',
                    filePath: `/apps/flutter/${appId}/web/`
                });
            } catch (err) {
                await appendBuildLog(tempBuildDir, 'web', `ERROR: ${err.message}`);
                console.error(`[${slug}] ERRO NO BUILD WEB:`, err.message);
                await updateBuildStatus(appId, 'failed', err.message, { platform: 'web' });
                // Se o web falhar, não tentamos outras plataformas
                throw err;
            }
        }

        // 4.2 Build Android (APK) quando solicitado
        if (platforms.includes('android')) {
            await appendBuildLog(tempBuildDir, 'android', 'Inicializando build Android (limpando caches)...');
            await cleanAndroidGradleCache(tempBuildDir, slug);
            await applyAndroidDartInjection(tempBuildDir, slug);
            try {
                await updateBuildStatus(appId, 'building', 'Iniciando build Android (APK)...', { platform: 'android' });
                const androidClean = 'flutter clean';
                await appendBuildLog(tempBuildDir, 'android', `COMMAND: ${androidClean}`);
                await executeCommand(androidClean, tempBuildDir);

                const androidCommand = 'flutter build apk --debug --no-shrink';
                const timeoutAndroid = `timeout ${BUILD_APK_TIMEOUT_SECS}s ${androidCommand}`;
                await appendBuildLog(tempBuildDir, 'android', `COMMAND: ${timeoutAndroid}`);
                const { stdout: aStdout, stderr: aStderr } = await executeCommand(timeoutAndroid, tempBuildDir, BUILD_APK_TIMEOUT_SECS * 1000 + 5000);
                const androidLog = aStdout + '\n' + aStderr;
                await appendBuildLog(tempBuildDir, 'android', `${androidCommand}\n${androidLog}`);
                console.log(`[${slug}] Build Android finalizado.`);

                const sourceApk = path.join(tempBuildDir, 'build', 'app', 'outputs', 'flutter-apk', 'app-debug.apk');
                const destAndroidDir = path.join(PUBLIC_APPS_DIR, 'flutter', String(appId), 'android');
                await fs.ensureDir(destAndroidDir);
                const safeSlug = (slug || `app_${appId || 'x'}`).replace(/[^a-zA-Z0-9_-]/g, '_');
                const destApk = path.join(destAndroidDir, `${safeSlug}-debug.apk`);
                await fs.copy(sourceApk, destApk);
                console.log(`[${slug}] APK copiado para ${destApk}.`);

                await updateBuildStatus(appId, 'success', androidLog, {
                    platform: 'android',
                    filePath: `/apps/flutter/${appId}/android/app-release.apk`
                });
            } catch (err) {
                await appendBuildLog(tempBuildDir, 'android', `ERROR: ${err.message}`);
                console.error(`[${slug}] ERRO NO BUILD ANDROID:`, err.message);
                await updateBuildStatus(appId, 'failed', err.message, { platform: 'android' });
                // Não relança; permite que outras plataformas (como web) permaneçam com status próprio
            }
        }

        console.log(`[${slug}] Build concluído para plataformas: ${platforms.join(', ')}.`);

    } catch (error) {
        console.error(`[${slug}] ERRO NO BUILD:`, error.message);
        await updateBuildStatus(appId, 'failed', error.message, { platform: 'web' });

    } finally {
        // 7. Limpar o diretório de trabalho temporário
        await fs.remove(tempBuildDir);
        console.log(`[${slug}] Workspace limpo.`);
    }
}

/**
 * Helper para executar comandos de shell de forma assíncrona.
 */
function executeCommand(command, cwd, timeoutMs = BUILD_COMMAND_TIMEOUT_MS) {
    return new Promise((resolve, reject) => {
        exec(command, { cwd, maxBuffer: 1024 * 1024 * 20, timeout: timeoutMs }, (error, stdout, stderr) => {
            if (error) {
                // Mesmo com erro, retornamos o log para análise
                reject(new Error(stdout + '\n' + stderr));
                return;
            }
            resolve({ stdout, stderr });
        });
    });
}

// Garante dependências essenciais no pubspec.yaml (http, provider, workz_sdk)
async function ensurePubspecDeps(projectDir, slug) {
    const pubspecPath = path.join(projectDir, 'pubspec.yaml');
    try {
        let content = await fs.readFile(pubspecPath, 'utf8');
        const lines = content.split(/\r?\n/);
        const hasLine = (test) => lines.some(l => l.trim().startsWith(test));
        if (!/^name:\s/m.test(content)) {
            lines.unshift(`name: ${slug.replace(/-/g, '_')}`);
        }
        if (!/^[^\S\n]*dependencies:\s*$/m.test(content)) {
            if (!hasLine('dependencies:')) lines.push('dependencies:');
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
        if (!/\bworkz_sdk:\b/.test(content)) {
            const relPath = path.relative(projectDir, path.resolve(__dirname, '../packages/workz_sdk')).replace(/\\/g, '/');
            const yamlBlock = [`workz_sdk:`, `  path: ${relPath}`];
            addDep('workz_sdk', yamlBlock);
        }
        content = lines.join('\n');
        await fs.writeFile(pubspecPath, content);
    } catch (_) {
        // Se não existir pubspec, não faz nada (fluxo fallback já cria)
    }
}

/**
 * Constrói uma pré-visualização rápida em modo debug e serve em /preview/<token>/
 */
async function createPreview(slug, codeSource) {
    await ensureFlutterWebReady();
    const token = crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2));
    const previewDir = path.join(PREVIEW_ROOT, token);
    const tempBuildDir = path.join(BUILD_WORKSPACE, `preview-src-${token}`);
    await fs.ensureDir(tempBuildDir);

    try {
        // Scaffold + inject (use valid package name)
        const projectName = toDartPackageName(`preview_src_${token}`);
        await executeCommand(`flutter create . --project-name ${projectName}`, tempBuildDir);

        if (codeSource.files && typeof codeSource.files === 'object') {
            const provided = Object.keys(codeSource.files);
            for (const filePath of provided) {
                const fullPath = path.join(tempBuildDir, filePath);
                await fs.ensureDir(path.dirname(fullPath));
                await fs.writeFile(fullPath, codeSource.files[filePath]);
            }
        }
        const libMain = path.join(tempBuildDir, 'lib', 'main.dart');
        await fs.ensureDir(path.dirname(libMain));
        await fs.writeFile(libMain, codeSource.dart_code || 'void main() {}');

        await ensurePubspecDeps(tempBuildDir, slug);
        await executeCommand('flutter pub get', tempBuildDir);

        // Debug build (rápido) + sem PWA para evitar cache
        const baseHref = `/preview/${token}/`;
        await executeCommand(`flutter build web --debug --pwa-strategy=none --base-href=${baseHref}`, tempBuildDir);

        const sourceArtifacts = path.join(tempBuildDir, 'build', 'web');
        await fs.remove(previewDir).catch(() => {});
        await fs.ensureDir(previewDir);
        await fs.copy(sourceArtifacts, previewDir);
        await fs.writeFile(path.join(previewDir, 'preview.json'), JSON.stringify({ slug, token, built_at: new Date().toISOString() }, null, 2));

        return { token, url: `/preview/${token}/` };
    } finally {
        await fs.remove(tempBuildDir).catch(() => {});
    }
}

// Serve estático das pré-visualizações
app.use('/preview', express.static(PREVIEW_ROOT));

// Endpoint: criar preview
app.post('/preview', async (req, res) => {
    try {
        const { slug, dart_code, files } = req.body || {};
        if (!slug || (!dart_code && !files)) {
            return res.status(400).json({ success: false, message: 'slug e (dart_code ou files) são obrigatórios.' });
        }
        const out = await createPreview(slug, { dart_code, files });
        res.status(201).json({ success: true, data: out });
    } catch (e) {
        res.status(500).json({ success: false, message: 'Falha ao gerar preview', error: String(e.message || e) });
    }
});

// Endpoint: remover preview
app.delete('/preview/:token', async (req, res) => {
    try {
        const dir = path.join(PREVIEW_ROOT, req.params.token);
        await fs.remove(dir);
        res.json({ success: true });
    } catch (e) {
        res.status(500).json({ success: false, message: 'Falha ao remover preview', error: String(e.message || e) });
    }
});

/**
 * Função que atualiza o status no seu banco de dados via API PHP.
 */
async function updateBuildStatus(appId, status, log, options = {}) {
    console.log(`[App ${appId}] Atualizando status para: ${status}`);
    const platform = options.platform || 'web';
    const buildVersion = options.buildVersion || '1.0.0';
    const filePath = options.filePath || null;
    
    // URL do endpoint que criamos no AppsController.php
    const apiBase = process.env.API_BASE || 'http://nginx';
    const updateUrl = `${apiBase}/api/apps/${appId}/build-status`;
    
    // SECURITY: Em produção, use uma variável de ambiente para o segredo.
    const workerSecret = process.env.WORKER_SECRET || 'seu-segredo-super-secreto';

    try {
        await fetch(updateUrl, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                // Envia um segredo para o backend PHP validar a requisição
                'X-Worker-Secret': workerSecret 
            },
            body: JSON.stringify({ 
                status: status, 
                log: log,
                platform: platform,
                build_version: buildVersion,
                file_path: filePath
            })
        });
    } catch (e) {
        console.error(`[App ${appId}] Falha ao comunicar com a API principal para atualizar status:`, e.message);
    }
}


app.listen(PORT, () => {
    console.log(`🚀 Workz Build Worker escutando na porta ${PORT}`);
    fs.ensureDirSync(BUILD_WORKSPACE); // Garante que o diretório de workspace exista
    fs.ensureDirSync(PREVIEW_ROOT);
    // Inicia o poller da fila no mesmo processo para garantir processamento de builds enfileirados
    try { require('./queue-poller'); } catch (e) { console.error('Falha ao iniciar queue-poller:', e?.message || e); }
});
