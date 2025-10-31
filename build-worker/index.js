// File: build-worker/index.js

const express = require('express');
const { exec } = require('child_process');
const fs = require('fs-extra'); // Usamos fs-extra para facilitar a cópia de diretórios
const path = require('path');
const crypto = require('crypto');

const app = express();
app.use(express.json({ limit: '5mb' }));

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

/**
 * Endpoint para iniciar o processo de build de um app Flutter.
 * Ex: POST http://localhost:9091/build/1012
 */
app.post('/build/:appId', (req, res) => {
    const appId = req.params.appId;
    const { dart_code, slug, files } = req.body; // Agora pode receber 'files'

    // Valida se tem 'slug', 'appId' e pelo menos uma forma de código ('dart_code' ou 'files')
    if (!appId || !slug || (!dart_code && !files)) {
        return res.status(400).json({ success: false, message: 'appId, slug e (dart_code ou files) são obrigatórios.' });
    }

    // Responde imediatamente para o cliente não ficar esperando o build terminar.
    res.status(202).json({ success: true, message: `Build para o app ${appId} iniciado.` });

    // Inicia o processo de build em segundo plano (sem 'await')
    runBuildProcess(appId, slug, { dart_code, files });
});

/**
 * Função principal que orquestra o processo de build.
 */
async function runBuildProcess(appId, slug, codeSource) {
    const tempBuildDir = path.join(BUILD_WORKSPACE, slug);
    const finalAppDir = path.join(PUBLIC_APPS_DIR, slug);

    console.log(`[${slug}] Iniciando build...`);
    try {
        const dc = codeSource && typeof codeSource.dart_code === 'string' ? codeSource.dart_code : '';
        const dcLen = dc.length;
        const dcHash = dcLen > 0 ? crypto.createHash('sha1').update(dc).digest('hex').slice(0, 12) : 'none';
        const filesCount = codeSource && codeSource.files && typeof codeSource.files === 'object' ? Object.keys(codeSource.files).length : 0;
        console.log(`[${slug}] payload: dart_code_len=${dcLen} sha1=${dcHash} files=${filesCount}`);
    } catch (_) { /* ignore diagnostics */ }
    await updateBuildStatus(appId, 'building', 'Preparando ambiente...');

    try {
        // 1. Preparar o ambiente de build
        await fs.ensureDir(tempBuildDir);
        await executeCommand('flutter create .', tempBuildDir);
        
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

        await updateBuildStatus(appId, 'building', 'Código injetado, iniciando compilação...');
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

        // 4. Executar o build do Flutter (com base-href para subpath)
        const baseHref = `/apps/flutter/${appId}/web/`;
        const { stdout, stderr } = await executeCommand(`flutter build web --release --base-href=${baseHref}`, tempBuildDir);
        const buildLog = stdout + '\n' + stderr;
        console.log(`[${slug}] Build finalizado.`);

        // 5. Copiar os artefatos para o diretório público
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
        console.log(`[${slug}] Artefatos copiados para ${destArtifacts}.`);

        // 6. Atualizar status final e limpar
        await updateBuildStatus(appId, 'success', buildLog);
        console.log(`[${slug}] Build concluído com sucesso!`);

    } catch (error) {
        console.error(`[${slug}] ERRO NO BUILD:`, error.message);
        await updateBuildStatus(appId, 'failed', error.message);

    } finally {
        // 7. Limpar o diretório de trabalho temporário
        await fs.remove(tempBuildDir);
        console.log(`[${slug}] Workspace limpo.`);
    }
}

/**
 * Helper para executar comandos de shell de forma assíncrona.
 */
function executeCommand(command, cwd) {
    return new Promise((resolve, reject) => {
        exec(command, { cwd, maxBuffer: 1024 * 1024 * 20 }, (error, stdout, stderr) => {
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
    const token = crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2));
    const previewDir = path.join(PREVIEW_ROOT, token);
    const tempBuildDir = path.join(BUILD_WORKSPACE, `preview-src-${token}`);
    await fs.ensureDir(tempBuildDir);

    try {
        // Scaffold + inject
        await executeCommand('flutter create .', tempBuildDir);

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
async function updateBuildStatus(appId, status, log) {
    console.log(`[App ${appId}] Atualizando status para: ${status}`);
    
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
                platform: 'web',
                build_version: '1.0.0',
                file_path: `/apps/flutter/${appId}/web/`
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
