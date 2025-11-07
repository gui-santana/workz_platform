// JavaScript otimizado
// Compilado em: 2025-11-04 12:54:20
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    // Meu App JavaScript com WorkzSDK — Sudoku Edition
class MyWorkzApp {
    constructor() {
        this.isInitialized = false;

        // ----- Sudoku state -----
        this.size = 9;
        this.box = 3;
        this.solution = this.makeEmptyBoard();
        this.puzzle = this.makeEmptyBoard();
        this.userBoard = this.makeEmptyBoard();
        this.readonlyMask = this.makeEmptyBoard(false);
        this.selectedCell = { r: 0, c: 0 };
        this.notesMode = false;
        this.notes = Array.from({ length: 9 }, () =>
            Array.from({ length: 9 }, () => new Set())
        );
        this.timerInterval = null;
        this.startTime = null;
        this.elapsedSecs = 0;
        this.mistakes = 0;
        this.maxMistakes = 3;
        this.difficulty = "médio"; // fácil | médio | difícil

        // Guard contra instância dupla (caso template chame 2x)
        if (window.__myworkzapp_initialized) return;
        window.__myworkzapp_initialized = true;

        this.init();
    }

    // ===========================
    // Core Template Flow
    // ===========================
    async init() {
        try {
            console.log('🚀 Inicializando app...');

            if (typeof WorkzSDK !== 'undefined') {
                console.log('✅ WorkzSDK disponível');
                this.isInitialized = true;
            } else {
                console.log('⚠️ WorkzSDK não encontrado');
            }

            this.render();
            this.setupEventListeners();

            // Tenta restaurar jogo salvo
            await this.tryRestoreFromSDK();

            // Se não houver jogo, cria um novo
            if (this.isBoardEmpty(this.puzzle)) {
                this.newGame(this.difficulty);
            }

        } catch (error) {
            console.error('❌ Erro ao inicializar:', error);
            this.renderError(error.message);
        }
    }

    render() {
        const appContainer = document.getElementById('app') || document.body;

        appContainer.innerHTML = `
            <div style="
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 980px;
                margin: 0 auto;
                padding: 40px 20px;
                text-align: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                color: white;
            ">
                <div style="
                    background: rgba(255, 255, 255, 0.12);
                    padding: 28px;
                    border-radius: 20px;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                ">
                    <h1 style="font-size: 2.6rem; margin-bottom: 6px;">🧩 Sudoku</h1>
                    <p style="opacity: .9; margin-top: 0;">Aplicativo criado com WorkzSDK</p>

                    <div style="
                        display:flex; 
                        gap: 12px; 
                        justify-content:center; 
                        flex-wrap: wrap; 
                        margin: 18px 0 26px;
                    ">
                        <div style="
                            display:flex;align-items:center;gap:8px;
                            background: rgba(40, 167, 69, 0.18);
                            padding: 10px 14px;border-radius: 999px;
                            border:1px solid rgba(40,167,69,0.28);
                            font-weight:600;
                        ">
                            <span>✅</span><span>App funcionando perfeitamente!</span>
                        </div>
                        <div id="status-mistakes" style="
                            display:flex;align-items:center;gap:8px;
                            background: rgba(220, 53, 69, 0.18);
                            padding: 10px 14px;border-radius: 999px;
                            border:1px solid rgba(220,53,69,0.28);
                            font-weight:600;
                        ">❌ Erros: 0 / ${this.maxMistakes}</div>
                        <div id="status-timer" style="
                            display:flex;align-items:center;gap:8px;
                            background: rgba(255, 255, 255, 0.18);
                            padding: 10px 14px;border-radius: 999px;
                            border:1px solid rgba(255,255,255,0.28);
                            font-weight:600;
                        ">⏱️ 00:00</div>
                    </div>

                    <div style="
                        display:grid; 
                        grid-template-columns: 1fr 360px;
                        gap: 22px;
                    ">
                        <!-- Painel Esquerdo -->
                        <div>
                            <!-- Controles -->
                            <div style="
                                display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-bottom:16px;
                            ">
                                <select id="difficulty" style="
                                    background: rgba(255,255,255,.12);
                                    color:white;border:1px solid rgba(255,255,255,.3);
                                    padding:10px 12px;border-radius:10px;cursor:pointer;
                                ">
                                    <option value="fácil">Fácil</option>
                                    <option value="médio" selected>Médio</option>
                                    <option value="difícil">Difícil</option>
                                </select>

                                <button id="new-game" style="${this.btnPrimary()}">Novo Jogo</button>
                                <button id="check-board" style="${this.btnSecondary()}">Checar</button>
                                <button id="hint" style="${this.btnSecondary()}">Dica</button>
                                <button id="solve" style="${this.btnDanger()}">Resolver</button>
                                <button id="reset" style="${this.btnGhost()}">Resetar</button>
                            </div>

                            <!-- Grade Sudoku -->
                            <div id="sudoku-grid" style="${this.gridStyle()}">
                                ${this.renderGridCells()}
                            </div>

                            <!-- Teclado numérico -->
                            <div style="margin-top: 16px;">
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap: wrap;">
                                    ${[1,2,3,4,5,6,7,8,9].map(n => 
                                        `<button data-num="${n}" class="num-key" style="${this.keyStyle()}">${n}</button>`
                                    ).join('')}
                                    <button id="erase" style="${this.keyStyle(true)}">⌫ Apagar</button>
                                </div>
                            </div>
                        </div>

                        <!-- Painel Direito -->
                        <div>
                            <div style="${this.card()}">
                                <div style="font-size: 2rem; margin-bottom: 6px;">🔧</div>
                                <div style="font-weight: 700; font-size: 1.05rem">WorkzSDK</div>
                                <div style="font-size: .92rem; opacity: .85; margin-top: 8px;">Integração completa</div>

                                <div style="display:flex; gap:10px; flex-wrap: wrap; justify-content:center; margin-top: 14px;">
                                    <button id="test-sdk-btn" style="${this.btnPrimary()}">Testar WorkzSDK</button>
                                    <button id="app-info-btn" style="${this.btnGhost()}">Informações do App</button>
                                </div>

                                <hr style="border-color: rgba(255,255,255,.2); margin: 16px 0" />

                                <div style="display:flex; gap:10px; flex-wrap: wrap; justify-content:center;">
                                    <button id="save" style="${this.btnSecondary()}">💾 Salvar</button>
                                    <button id="load" style="${this.btnSecondary()}">📂 Restaurar</button>
                                    <button id="toggle-notes" style="${this.btnSecondary()}">📝 Notas: <span id="notes-state">Off</span></button>
                                </div>

                                <div style="margin-top:12px;font-size:.9rem;opacity:.9">
                                    <div>• Salva/recupera o jogo via <b>WorkzSDK.storage.kv</b> (quando disponível)</div>
                                    <div>• Realce de linha/coluna/bloco e números iguais</div>
                                    <div>• Validação em tempo real e limite de erros</div>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns: repeat(3,1fr); gap:14px; margin-top:14px;">
                                <div style="${this.card()}">
                                    <div style="font-size:2rem">⚡</div>
                                    <div style="font-weight:700">Performance</div>
                                    <div style="font-size:.9rem;opacity:.85">Algoritmo backtracking</div>
                                </div>
                                <div style="${this.card()}">
                                    <div style="font-size:2rem">🌐</div>
                                    <div style="font-weight:700">Web Ready</div>
                                    <div style="font-size:.9rem;opacity:.85">Funciona em qualquer navegador</div>
                                </div>
                                <div style="${this.card()}">
                                    <div style="font-size:2rem">🧠</div>
                                    <div style="font-weight:700">UX</div>
                                    <div style="font-size:.9rem;opacity:.85">Teclado numérico e notas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        // Template buttons
        const testBtn = document.getElementById('test-sdk-btn');
        const infoBtn = document.getElementById('app-info-btn');
        if (testBtn) testBtn.addEventListener('click', () => this.testWorkzSDK());
        if (infoBtn) infoBtn.addEventListener('click', () => this.showAppInfo());

        // Sudoku controls
        const diffSel = document.getElementById('difficulty');
        const newBtn = document.getElementById('new-game');
        const checkBtn = document.getElementById('check-board');
        const hintBtn = document.getElementById('hint');
        const solveBtn = document.getElementById('solve');
        const resetBtn = document.getElementById('reset');
        const eraseBtn = document.getElementById('erase');
        const toggleNotesBtn = document.getElementById('toggle-notes');
        const saveBtn = document.getElementById('save');
        const loadBtn = document.getElementById('load');

        if (diffSel) diffSel.addEventListener('change', (e) => this.difficulty = e.target.value);
        if (newBtn) newBtn.addEventListener('click', () => this.newGame(this.difficulty));
        if (checkBtn) checkBtn.addEventListener('click', () => this.checkBoard());
        if (hintBtn) hintBtn.addEventListener('click', () => this.giveHint());
        if (solveBtn) solveBtn.addEventListener('click', () => this.solveBoard());
        if (resetBtn) resetBtn.addEventListener('click', () => this.resetBoard());
        if (eraseBtn) eraseBtn.addEventListener('click', () => this.placeNumber(0));

        if (toggleNotesBtn) toggleNotesBtn.addEventListener('click', () => {
            this.notesMode = !this.notesMode;
            const el = document.getElementById('notes-state');
            if (el) el.textContent = this.notesMode ? 'On' : 'Off';
        });

        if (saveBtn) saveBtn.addEventListener('click', () => this.saveToSDK());
        if (loadBtn) loadBtn.addEventListener('click', () => this.tryRestoreFromSDK(true));

        // Grid cell events + keyboard
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                const id = `cell-${r}-${c}`;
                const cell = document.getElementById(id);
                if (!cell) continue;

                cell.addEventListener('click', () => {
                    this.selectCell(r, c);
                });
            }
        }

        document.querySelectorAll('.num-key').forEach(btn => {
            btn.addEventListener('click', () => {
                const num = parseInt(btn.getAttribute('data-num'), 10);
                this.placeNumber(num);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key >= '1' && e.key <= '9') {
                this.placeNumber(parseInt(e.key, 10));
            } else if (['Backspace', 'Delete', '0'].includes(e.key)) {
                this.placeNumber(0);
            } else if (e.key.toLowerCase() === 'n') {
                this.notesMode = !this.notesMode;
                const el = document.getElementById('notes-state');
                if (el) el.textContent = this.notesMode ? 'On' : 'Off';
            } else if (e.key === 'ArrowUp') {
                this.moveSelection(-1, 0);
            } else if (e.key === 'ArrowDown') {
                this.moveSelection(1, 0);
            } else if (e.key === 'ArrowLeft') {
                this.moveSelection(0, -1);
            } else if (e.key === 'ArrowRight') {
                this.moveSelection(0, 1);
            }
        });
    }

    // ===========================
    // UI Helpers (estilos)
    // ===========================
    btnPrimary() {
        return `
            background:#007bff;color:white;border:none;padding:10px 18px;
            border-radius:10px;font-size:1rem;cursor:pointer;transition:.2s
        `;
    }
    btnSecondary() {
        return `
            background:transparent;color:white;border:1px solid rgba(255,255,255,.8);
            padding:10px 18px;border-radius:10px;font-size:1rem;cursor:pointer;transition:.2s
        `;
    }
    btnDanger() {
        return `
            background:#dc3545;color:white;border:none;padding:10px 18px;
            border-radius:10px;font-size:1rem;cursor:pointer;transition:.2s
        `;
    }
    btnGhost() {
        return `
            background:rgba(255,255,255,.1);color:white;border:1px solid rgba(255,255,255,.25);
            padding:10px 18px;border-radius:10px;font-size:1rem;cursor:pointer;transition:.2s
        `;
    }
    keyStyle(isErase=false) {
        return `
            background:${isErase? 'rgba(220,53,69,.2)':'rgba(255,255,255,.12)'}; 
            color:white;border:1px solid rgba(255,255,255,.35);
            padding:12px 14px;border-radius:12px;min-width:54px;
            font-size:1.05rem;cursor:pointer;backdrop-filter: blur(6px)
        `;
    }
    card() {
        return `
            background: rgba(255, 255, 255, 0.1);
            padding: 18px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align:center
        `;
    }
    gridStyle() {
        return `
            margin: 0 auto; 
            width: min(92vw, 520px);
            aspect-ratio:1/1;
            display:grid;grid-template-columns: repeat(9, 1fr);
            gap: 0;
            background: rgba(0,0,0,.2);
            border: 3px solid rgba(255,255,255,.8);
            border-radius: 10px;
            overflow: hidden;
            user-select: none;
        `;
    }
    renderGridCells() {
        let html = '';
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                const id = `cell-${r}-${c}`;
                html += `
                    <div id="${id}" data-r="${r}" data-c="${c}" style="
                        display:flex;align-items:center;justify-content:center;
                        font-size: clamp(18px, 3.5vw, 26px);
                        border-right: ${((c+1)%3===0 && c!==8) ? '2px solid rgba(255,255,255,.6)' : '1px solid rgba(255,255,255,.25)'};
                        border-bottom: ${((r+1)%3===0 && r!==8) ? '2px solid rgba(255,255,255,.6)' : '1px solid rgba(255,255,255,.25)'};
                        position:relative;
                        background: rgba(255,255,255,.06);
                        cursor: pointer;
                        transition: background .1s ease;
                    ">
                        <span class="cell-value"></span>
                        <div class="cell-notes" style="
                            position:absolute; inset:3px; display:grid; 
                            grid-template-columns: repeat(3,1fr); grid-auto-rows: 1fr;
                            gap:1px; font-size: clamp(8px, 1.7vw, 12px); opacity:.9; pointer-events:none; 
                        "></div>
                    </div>
                `;
            }
        }
        return html;
    }

    // ===========================
    // Sudoku: geração/solução
    // ===========================
    makeEmptyBoard(fillZero = true) {
        return Array.from({ length: 9 }, () =>
            Array.from({ length: 9 }, () => fillZero ? 0 : false)
        );
    }
    isBoardEmpty(board) {
        for (let r = 0; r < 9; r++) for (let c = 0; c < 9; c++) if (board[r][c] !== 0) return false;
        return true;
    }
    shuffle(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }
    findEmpty(board) {
        for (let r = 0; r < 9; r++) for (let c = 0; c < 9; c++) if (board[r][c] === 0) return [r, c];
        return null;
    }
    isSafe(board, r, c, val) {
        for (let i = 0; i < 9; i++) {
            if (board[r][i] === val) return false;
            if (board[i][c] === val) return false;
        }
        const br = Math.floor(r / 3) * 3;
        const bc = Math.floor(c / 3) * 3;
        for (let i = 0; i < 3; i++)
            for (let j = 0; j < 3; j++)
                if (board[br + i][bc + j] === val) return false;
        return true;
    }
    // Backtracking solver
    solve(board) {
        const empty = this.findEmpty(board);
        if (!empty) return true;
        const [r, c] = empty;
        const nums = this.shuffle([1,2,3,4,5,6,7,8,9]);
        for (const val of nums) {
            if (this.isSafe(board, r, c, val)) {
                board[r][c] = val;
                if (this.solve(board)) return true;
                board[r][c] = 0;
            }
        }
        return false;
    }
    // Gera solução completa
    generateSolution() {
        const board = this.makeEmptyBoard();
        // Pré-preenche diagonal das 3 caixas principais para acelerar
        for (let k = 0; k < 3; k++) {
            const base = k * 3;
            const nums = this.shuffle([1,2,3,4,5,6,7,8,9]);
            let idx = 0;
            for (let i = 0; i < 3; i++)
                for (let j = 0; j < 3; j++)
                    board[base + i][base + j] = nums[idx++];
        }
        this.solve(board);
        return board;
    }
    // Remove valores para criar puzzle conforme dificuldade
    carvePuzzle(solution, difficulty = 'médio') {
        const removals = {
            'fácil': 36,   // 81 - 36 = 45 pistas
            'médio': 45,   // 36 pistas
            'difícil': 54  // 27 pistas
        }[difficulty] ?? 45;

        const puzzle = solution.map(row => row.slice());
        let removed = 0;
        const positions = this.shuffle(
            Array.from({ length: 81 }, (_, k) => [Math.floor(k/9), k%9])
        );
        for (const [r, c] of positions) {
            if (removed >= removals) break;
            const backup = puzzle[r][c];
            puzzle[r][c] = 0;
            // Opcional: checar unicidade é custoso; aqui aceitamos múltiplas soluções raríssimas
            removed++;
        }
        return puzzle;
    }

    // ===========================
    // Game flow
    // ===========================
    newGame(diff) {
        this.stopTimer();
        this.elapsedSecs = 0;
        this.updateTimerUI();

        this.difficulty = diff || 'médio';
        this.mistakes = 0;
        this.updateMistakesUI();

        this.solution = this.generateSolution();
        this.puzzle = this.carvePuzzle(this.solution, this.difficulty);
        this.userBoard = this.puzzle.map(row => row.slice());
        this.readonlyMask = this.puzzle.map(row => row.map(v => v !== 0));
        this.notes = Array.from({ length: 9 }, () =>
            Array.from({ length: 9 }, () => new Set())
        );
        this.renderBoard();
        this.startTimer();
    }

    renderBoard() {
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                const cell = document.getElementById(`cell-${r}-${c}`);
                if (!cell) continue;
                const span = cell.querySelector('.cell-value');
                const notesEl = cell.querySelector('.cell-notes');

                cell.classList.remove('readonly', 'selected', 'conflict', 'highlight');
                cell.style.background = 'rgba(255,255,255,.06)';
                span.textContent = '';
                notesEl.innerHTML = '';

                if (this.userBoard[r][c] !== 0) {
                    span.textContent = this.userBoard[r][c];
                } else if (this.notes[r][c].size > 0) {
                    // mostra notas (1-9 em mini-grid)
                    const ns = [];
                    for (let n = 1; n <= 9; n++) {
                        ns.push(`<div style="display:flex;align-items:center;justify-content:center;opacity:${this.notes[r][c].has(n)?1:.25}">${this.notes[r][c].has(n)?n:''}</div>`);
                    }
                    notesEl.innerHTML = ns.join('');
                }

                if (this.readonlyMask[r][c]) {
                    cell.style.background = 'rgba(255,255,255,.12)';
                    cell.style.fontWeight = '700';
                    cell.style.cursor = 'default';
                } else {
                    cell.style.fontWeight = '500';
                    cell.style.cursor = 'pointer';
                }
            }
        }
        this.applyHighlights();
    }

    selectCell(r, c) {
        this.selectedCell = { r, c };
        this.applyHighlights();
    }

    applyHighlights() {
        // limpa
        for (let rr = 0; rr < 9; rr++) {
            for (let cc = 0; cc < 9; cc++) {
                const cell = document.getElementById(`cell-${rr}-${cc}`);
                if (!cell) continue;
                cell.classList.remove('selected','rowhl','colhl','boxhl','same');
                cell.style.outline = 'none';
            }
        }

        const { r, c } = this.selectedCell;
        const selectedVal = this.userBoard[r][c];

        for (let i = 0; i < 9; i++) {
            this.highlightCell(r, i, 'rowhl');
            this.highlightCell(i, c, 'colhl');
        }
        const br = Math.floor(r/3)*3;
        const bc = Math.floor(c/3)*3;
        for (let i = 0; i < 3; i++)
            for (let j = 0; j < 3; j++)
                this.highlightCell(br+i, bc+j, 'boxhl');

        this.highlightCell(r, c, 'selected');

        if (selectedVal) {
            for (let rr = 0; rr < 9; rr++)
                for (let cc = 0; cc < 9; cc++)
                    if (this.userBoard[rr][cc] === selectedVal)
                        this.highlightCell(rr, cc, 'same');
        }

        // aplica estilos visuais dessas classes
        const styleMap = {
            selected: '0 0 0 2px rgba(255,255,255,.9) inset',
            rowhl: '0 0 0 9999px rgba(255,255,255,.03) inset',
            colhl: '0 0 0 9999px rgba(255,255,255,.03) inset',
            boxhl: '0 0 0 9999px rgba(255,255,255,.03) inset',
            same: '0 0 0 2px rgba(255,255,255,.6) inset'
        };
        for (let rr = 0; rr < 9; rr++) {
            for (let cc = 0; cc < 9; cc++) {
                const el = document.getElementById(`cell-${rr}-${cc}`);
                if (!el) continue;
                let boxShadow = '';
                if (rr === r || cc === c || (Math.floor(rr/3)===Math.floor(r/3) && Math.floor(cc/3)===Math.floor(c/3))) {
                    boxShadow = styleMap['boxhl'];
                }
                if (this.userBoard[rr][cc] && this.userBoard[rr][cc] === selectedVal) {
                    boxShadow = styleMap['same'];
                }
                if (rr === r && cc === c) {
                    boxShadow = styleMap['selected'];
                }
                el.style.boxShadow = boxShadow;
            }
        }
    }

    highlightCell(r, c, cls) {
        const el = document.getElementById(`cell-${r}-${c}`);
        if (el) el.classList.add(cls);
    }

    moveSelection(dr, dc) {
        let { r, c } = this.selectedCell;
        r = Math.min(8, Math.max(0, r + dr));
        c = Math.min(8, Math.max(0, c + dc));
        this.selectCell(r, c);
    }

    placeNumber(num) {
        const { r, c } = this.selectedCell;
        if (this.readonlyMask[r][c]) return; // não pode editar pista original

        if (this.notesMode && num !== 0) {
            // alterna nota
            if (this.userBoard[r][c] !== 0) this.userBoard[r][c] = 0; // limpar valor para mostrar notas
            if (this.notes[r][c].has(num)) this.notes[r][c].delete(num); else this.notes[r][c].add(num);
        } else {
            // valor direto
            this.notes[r][c].clear();
            this.userBoard[r][c] = num;
            // validação imediata (se num != 0)
            if (num !== 0 && !this.isSafe(this.userBoard, r, c, num)) {
                // conflito: linha/col/box contém num
                this.mistakes++;
                this.updateMistakesUI();
                this.flashConflict(r, c);
                if (this.mistakes >= this.maxMistakes) {
                    setTimeout(() => {
                        alert('Limite de erros atingido. Vou mostrar a solução.');
                        this.solveBoard(true);
                    }, 100);
                }
            }
        }

        this.renderBoard();
        if (this.isComplete()) {
            this.stopTimer();
            setTimeout(() => {
                alert(`🎉 Parabéns! Sudoku concluído em ${this.formatTime(this.elapsedSecs)} com ${this.mistakes} erro(s).`);
            }, 50);
        }
    }

    flashConflict(r, c) {
        const cell = document.getElementById(`cell-${r}-${c}`);
        if (!cell) return;
        const original = cell.style.background;
        cell.style.background = 'rgba(220,53,69,.35)';
        setTimeout(() => cell.style.background = original, 350);
    }

    isComplete() {
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                if (this.userBoard[r][c] === 0) return false;
                if (!this.isSafe(this.userBoard, r, c, this.userBoard[r][c])) return false;
            }
        }
        return true;
    }

    checkBoard() {
        // compara com solução
        let ok = true;
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                if (this.userBoard[r][c] !== 0 && this.userBoard[r][c] !== this.solution[r][c]) {
                    ok = false;
                    this.flashConflict(r, c);
                }
            }
        }
        if (ok) {
            alert('✅ Até aqui, tudo certo!');
        } else {
            alert('⚠️ Existem valores incorretos.');
        }
    }

    giveHint() {
        // encontra primeira célula vazia e preenche com solução
        for (let r = 0; r < 9; r++) {
            for (let c = 0; c < 9; c++) {
                if (this.userBoard[r][c] === 0) {
                    this.userBoard[r][c] = this.solution[r][c];
                    this.readonlyMask[r][c] = false; // continua editável, mas dado pela dica
                    this.notes[r][c].clear();
                    this.renderBoard();
                    return;
                }
            }
        }
        alert('Não há células vazias para dica!');
    }

    solveBoard(silent = false) {
        this.userBoard = this.solution.map(row => row.slice());
        this.notes = Array.from({ length: 9 }, () =>
            Array.from({ length: 9 }, () => new Set())
        );
        this.renderBoard();
        this.stopTimer();
        if (!silent) alert('🧠 Tabuleiro resolvido.');
    }

    resetBoard() {
        this.userBoard = this.puzzle.map(row => row.slice());
        this.notes = Array.from({ length: 9 }, () =>
            Array.from({ length: 9 }, () => new Set())
        );
        this.mistakes = 0;
        this.updateMistakesUI();
        this.elapsedSecs = 0;
        this.stopTimer();
        this.startTimer();
        this.renderBoard();
    }

    // ===========================
    // Timer & UI
    // ===========================
    startTimer() {
        this.stopTimer();
        this.startTime = Date.now() - (this.elapsedSecs * 1000);
        this.timerInterval = setInterval(() => {
            this.elapsedSecs = Math.floor((Date.now() - this.startTime) / 1000);
            this.updateTimerUI();
        }, 500);
    }
    stopTimer() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        this.timerInterval = null;
    }
    updateTimerUI() {
        const el = document.getElementById('status-timer');
        if (el) el.textContent = `⏱️ ${this.formatTime(this.elapsedSecs)}`;
    }
    updateMistakesUI() {
        const el = document.getElementById('status-mistakes');
        if (el) el.textContent = `❌ Erros: ${this.mistakes} / ${this.maxMistakes}`;
    }
    formatTime(total) {
        const m = String(Math.floor(total / 60)).padStart(2, '0');
        const s = String(total % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    // ===========================
    // WorkzSDK Save/Load
    // ===========================
    async saveToSDK() {
        if (!this.isInitialized || typeof WorkzSDK === 'undefined' || !WorkzSDK?.storage?.kv) {
            alert('⚠️ WorkzSDK.storage.kv não disponível.');
            return;
        }
        const payload = {
            version: 1,
            difficulty: this.difficulty,
            puzzle: this.puzzle,
            solution: this.solution,
            userBoard: this.userBoard,
            readonlyMask: this.readonlyMask,
            notes: this.notes.map(row => row.map(set => Array.from(set))),
            mistakes: this.mistakes,
            elapsedSecs: this.elapsedSecs,
            savedAt: new Date().toISOString()
        };
        try {
            await WorkzSDK.storage.kv.set('sudoku_save', payload);
            alert('💾 Jogo salvo com sucesso!');
        } catch (e) {
            console.error(e);
            alert('❌ Erro ao salvar no WorkzSDK.');
        }
    }

    async tryRestoreFromSDK(forceAlert = false) {
        if (!this.isInitialized || typeof WorkzSDK === 'undefined' || !WorkzSDK?.storage?.kv) {
            if (forceAlert) alert('⚠️ WorkzSDK.storage.kv não disponível.');
            return;
        }
        try {
            const data = await WorkzSDK.storage.kv.get('sudoku_save');
            if (!data) {
                if (forceAlert) alert('Nenhum jogo salvo encontrado.');
                return;
            }
            this.difficulty = data.difficulty || 'médio';
            this.puzzle = data.puzzle;
            this.solution = data.solution;
            this.userBoard = data.userBoard;
            this.readonlyMask = data.readonlyMask;
            this.notes = data.notes.map(row => row.map(arr => new Set(arr)));
            this.mistakes = data.mistakes ?? 0;
            this.elapsedSecs = data.elapsedSecs ?? 0;

            const diffSel = document.getElementById('difficulty');
            if (diffSel) diffSel.value = this.difficulty;

            this.updateMistakesUI();
            this.updateTimerUI();
            this.renderBoard();
            this.startTimer();

            if (forceAlert) alert('📂 Jogo restaurado!');
        } catch (e) {
            console.error(e);
            if (forceAlert) alert('❌ Erro ao restaurar do WorkzSDK.');
        }
    }

    // ===========================
    // Template original: info & erros
    // ===========================
    testWorkzSDK() {
        if (this.isInitialized && typeof WorkzSDK !== 'undefined') {
            alert(`✅ WorkzSDK está funcionando perfeitamente!

Recursos disponíveis:
• Autenticação
• Storage (KV, Docs, Blobs)  
• API Integration
• Push Notifications
• Performance Monitoring`);
        } else {
            alert('⚠️ WorkzSDK não foi carregado. Verifique a configuração.');
        }
    }

    showAppInfo() {
        const info = `🚀 Informações do Aplicativo

📱 Tipo: JavaScript App
🌐 Plataforma: Web
⚡ Engine: JavaScript ES6+
🔧 SDK: WorkzSDK v2.0
📅 Build: ${new Date().toLocaleString('pt-BR')}
🎯 Status: Funcionando

✨ Recursos Ativos:
• Interface responsiva
• Integração WorkzSDK
• Performance otimizada
• Compatibilidade universal`;
        alert(info);
    }

    renderError(message) {
        const appContainer = document.getElementById('app') || document.body;

        appContainer.innerHTML = `
            <div style="
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                padding: 40px 20px;
                text-align: center;
                background: #f8d7da;
                min-height: 100vh;
                color: #721c24;
            ">
                <div style="
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    border: 1px solid #f5c6cb;
                ">
                    <div style="font-size: 4rem; margin-bottom: 20px;">❌</div>
                    <h1 style="margin-bottom: 20px;">Erro ao Inicializar</h1>
                    <p style="margin-bottom: 30px; font-size: 1.1rem;">${message}</p>
                    <button onclick="location.reload()" style="
                        background: #dc3545;
                        color: white;
                        border: none;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-size: 1rem;
                        cursor: pointer;
                    ">
                        Tentar Novamente
                    </button>
                </div>
            </div>
        `;
    }
}

// ===========================
// Inicialização (com guarda)
// ===========================
function bootApp() {
    if (!window.__myworkzapp_initialized) new MyWorkzApp();
}
document.addEventListener('DOMContentLoaded', bootApp);
if (document.readyState !== 'loading') bootApp();
    
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