// JavaScript otimizado
// Compilado em: 2025-11-06 13:17:29
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    // Workz Sheets App v7 — Versão integrada: navegação, fórmulas PT-BR, e seleção de referência por clique
class WorkzSheetsApp {
    constructor() {
        this.isInitialized = false;
        this.filename = "planilha.xlsx";
        this.sheets = [{ name: "Planilha1", data: this.createEmptySheet(15, 10), formats: {} }];
        this.activeSheetIndex = 0;
        this.selected = { r: 0, c: 0 };
        this.isEditingFormula = false;
        this.editingCell = null;
        this.init();
    }

    async init() {
        try {
            console.log("🚀 Inicializando Workz Sheets v7...");
            if (typeof WorkzSDK !== "undefined") {
                this.isInitialized = true;
                console.log("✅ WorkzSDK disponível");
            }
            this.render();
            this.setupEventListeners();
        } catch (err) {
            console.error(err);
            this.renderError(err.message);
        }
    }

    get activeSheet() {
        return this.sheets[this.activeSheetIndex];
    }

    render() {
        const appContainer = document.getElementById("app") || document.body;
        appContainer.innerHTML = `
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:30px;color:white;text-align:center;">
            <div style="max-width:1100px;margin:0 auto;background:rgba(255,255,255,.1);border-radius:20px;padding:20px 30px 40px;backdrop-filter:blur(10px);box-shadow:0 8px 32px rgba(0,0,0,.25);">                
                <p style="opacity:.9;margin-bottom:25px;">Navegação, fórmulas PT-BR e seleção de referência integradas</p>

                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-bottom:15px;">
                    <button id="new-sheet" style="${this.btnPrimary()}">🆕 Nova</button>
                    <button id="save" style="${this.btnSecondary()}">💾 Salvar</button>
                    <button id="load" style="${this.btnSecondary()}">📂 Abrir</button>
                    <button id="export" style="${this.btnPrimary()}">⬇️ Exportar XLSX</button>
                    <label style="${this.btnSecondary()}cursor:pointer;">📤 Importar XLSX<input type="file" id="import" accept=".xlsx,.xls" style="display:none;"></label>
                    <button id="add-row" style="${this.btnGhost()}">➕ Linha abaixo</button>
                    <button id="add-col" style="${this.btnGhost()}">➕ Coluna à direita</button>
                </div>

                <div id="sheet" style="overflow-x:auto;background:rgba(255,255,255,.08);border-radius:10px;border:1px solid rgba(255,255,255,.25);box-shadow:inset 0 0 8px rgba(0,0,0,.2);">${this.renderTable()}</div>
            </div>
        </div>`;
    }

    renderTable() {
        const data = this.activeSheet.data;
        const cols = data[0].length;
        let html = `<table style='width:100%;border-collapse:collapse;'>`;
        html += `<thead><tr><th></th>`;
        for (let c = 0; c < cols; c++) html += `<th>${this.colLabel(c)}</th>`;
        html += `</tr></thead><tbody>`;
        for (let r = 0; r < data.length; r++) {
            html += `<tr><th>${r + 1}</th>`;
            for (let c = 0; c < cols; c++) {
                const val = this.getDisplayValue(r, c);
                html += `<td contenteditable='true' data-r='${r}' data-c='${c}'
                    style='border:1px solid rgba(255,255,255,.25);min-width:80px;padding:6px 8px;background:rgba(255,255,255,.05);color:white;'>${val ?? ''}</td>`;
            }
            html += `</tr>`;
        }
        html += `</tbody></table>`;
        return html;
    }

    setupEventListeners() {
        const sheetDiv = document.getElementById("sheet");

        // ======= ENTRADA E NAVEGAÇÃO =======
        sheetDiv.addEventListener("keydown", (e) => {
            const cell = e.target.closest('td[data-r]');
            if (!cell) return;

            const r = parseInt(cell.dataset.r);
            const c = parseInt(cell.dataset.c);

            // Início do modo fórmula
            if (e.key === "=" && !cell.innerText.startsWith("=")) {
                this.isEditingFormula = true;
                this.editingCell = cell;
                this.selected = { r, c };
            }

            // Validação com Enter
            if (e.key === "Enter") {
                e.preventDefault();
                const val = cell.innerText.trim();
                this.activeSheet.data[r][c] = val;
                this.refreshDisplayOnly();
                this.isEditingFormula = false;
                this.editingCell = null;
                const next = document.querySelector(`td[data-r='${r + 1}'][data-c='${c}']`);
                if (next) {
                    next.focus();
                    this.selected = { r: r + 1, c };
                    this.updateSelectionHighlight();
                }
                return;
            }

            // Navegação com setas
            if (["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight"].includes(e.key) && !this.isEditingFormula) {
                e.preventDefault();
                let nr = r, nc = c;
                if (e.key === "ArrowUp") nr = Math.max(0, r - 1);
                if (e.key === "ArrowDown") nr = Math.min(this.activeSheet.data.length - 1, r + 1);
                if (e.key === "ArrowLeft") nc = Math.max(0, c - 1);
                if (e.key === "ArrowRight") nc = Math.min(this.activeSheet.data[0].length - 1, c + 1);
                const next = document.querySelector(`td[data-r='${nr}'][data-c='${nc}']`);
                if (next) {
                    next.focus();
                    this.selected = { r: nr, c: nc };
                    this.updateSelectionHighlight();
                }
            }
        });

        // ======= SELEÇÃO DE REFERÊNCIA =======
        sheetDiv.addEventListener("click", (e) => {
            const target = e.target.closest('td[data-r]');
            if (!target) return;
            const r = parseInt(target.dataset.r);
            const c = parseInt(target.dataset.c);
            const ref = this.colLabel(c) + (r + 1);

            if (this.isEditingFormula && this.editingCell) {
                e.preventDefault();
                e.stopPropagation();
                const cur = this.editingCell.innerText;
                this.editingCell.innerText = cur + ref;
                this.placeCaretAtEnd(this.editingCell);
                return;
            }

            this.selected = { r, c };
            this.updateSelectionHighlight();
        });

        sheetDiv.addEventListener("input", (e) => {
            const cell = e.target;
            const r = parseInt(cell.dataset.r);
            const c = parseInt(cell.dataset.c);
            this.activeSheet.data[r][c] = cell.innerText;
        });
    }

    // ======= FÓRMULAS =======
    getDisplayValue(r, c) {
        const val = this.activeSheet.data[r][c];
        if (typeof val === 'string' && val.startsWith('=')) {
            try {
                const expr = val
                    .replace(/^=/, '')
                    .toUpperCase()
                    .replace(/SOMA\(([^)]+)\)/g, (_, r) => this.sumRange(r))
                    .replace(/MÉDIA\(([^)]+)\)/g, (_, r) => this.avgRange(r))
                    .replace(/MÍNIMO\(([^)]+)\)/g, (_, r) => this.minRange(r))
                    .replace(/MÁXIMO\(([^)]+)\)/g, (_, r) => this.maxRange(r))
                    .replace(/[A-Z]+\d+/g, ref => this.cellValue(ref) || 0);
                return eval(expr);
            } catch {
                return '#ERRO';
            }
        }
        return val;
    }

    refToRC(ref) {
        const match = ref.match(/^([A-Z]+)(\d+)$/);
        if (!match) return [0, 0];
        const col = match[1].split('').reduce((a, ch) => a * 26 + (ch.charCodeAt(0) - 64), 0) - 1;
        const row = parseInt(match[2]) - 1;
        return [row, col];
    }

    cellValue(ref) {
        const [r, c] = this.refToRC(ref);
        return parseFloat(this.activeSheet.data[r]?.[c]) || 0;
    }

    rangeValues(range) {
        const [a, b] = range.split(":");
        const [r1, c1] = this.refToRC(a);
        const [r2, c2] = this.refToRC(b);
        const vals = [];
        for (let r = Math.min(r1, r2); r <= Math.max(r1, r2); r++) {
            for (let c = Math.min(c1, c2); c <= Math.max(c1, c2); c++) {
                vals.push(parseFloat(this.activeSheet.data[r][c]) || 0);
            }
        }
        return vals;
    }

    sumRange(range) { return this.rangeValues(range).reduce((a, b) => a + b, 0); }
    avgRange(range) { const v = this.rangeValues(range); return v.length ? v.reduce((a, b) => a + b, 0) / v.length : 0; }
    minRange(range) { return Math.min(...this.rangeValues(range)); }
    maxRange(range) { return Math.max(...this.rangeValues(range)); }

    // ======= HELPERS =======
    refreshDisplayOnly() {
        document.querySelectorAll('td[data-r]').forEach(td => {
            const r = parseInt(td.dataset.r);
            const c = parseInt(td.dataset.c);
            td.textContent = this.getDisplayValue(r, c) ?? '';
        });
        this.updateSelectionHighlight();
    }

    updateSelectionHighlight() {
        document.querySelectorAll('td[data-r]').forEach(td => td.style.outline = 'none');
        const sel = this.selected;
        const td = document.querySelector(`td[data-r='${sel.r}'][data-c='${sel.c}']`);
        if (td) td.style.outline = '2px solid #00d4ff';
    }

    placeCaretAtEnd(el) {
        el.focus();
        const range = document.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    createEmptySheet(r, c) { return Array.from({ length: r }, () => Array(c).fill('')); }
    colLabel(i) { let s = ''; while (i >= 0) { s = String.fromCharCode(65 + (i % 26)) + s; i = Math.floor(i / 26) - 1; } return s; }

    btnPrimary() { return `background:#007bff;color:white;border:none;padding:10px 18px;border-radius:10px;cursor:pointer;`; }
    btnSecondary() { return `background:transparent;color:white;border:1px solid rgba(255,255,255,.8);padding:10px 18px;border-radius:10px;cursor:pointer;`; }
    btnGhost() { return `background:rgba(255,255,255,.1);color:white;border:1px solid rgba(255,255,255,.25);padding:10px 18px;border-radius:10px;cursor:pointer;`; }
    btnFormat() { return `background:rgba(255,255,255,.2);color:white;border:none;padding:5px 10px;border-radius:8px;cursor:pointer;`; }

    renderError(m) {
        document.body.innerHTML = `<div style='padding:50px;text-align:center;'><h1>❌ Erro</h1><p>${m}</p></div>`;
    }
}

document.addEventListener('DOMContentLoaded',()=>new WorkzSheetsApp());
    
    console.log('✅ App JavaScript executado com sucesso');
    
} catch (error) {
    console.error('❌ Erro na execução JavaScript:', error);
    
    // Mostrar erro na tela
    const container = document.getElementById('app-container') || document.body;
    container.innerHTML = `
        <div style="
            display: flex; align-items: center; justify-content: center; 
            height: 100vh; text-align: center; color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        ">
            <div>
                <h2>⚠️ Erro na Execução</h2>
                <p>${error.message}</p>
                <button onclick="location.reload()" style="
                    background: #4CAF50; color: white; border: none;
                    padding: 10px 20px; border-radius: 5px; cursor: pointer;
                    margin-top: 15px;
                ">Recarregar</button>
            </div>
        </div>
    `;
}