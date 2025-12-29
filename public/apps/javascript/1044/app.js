// JavaScript otimizado
// Compilado em: 2025-11-18 18:21:47
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    // hp12c.js - Calculadora tipo HP-12C em JS monolítico
(function () {
  'use strict';

  // ==========================
  // ESTADO DA CALCULADORA
  // ==========================
  const state = {
    stack: [0, 0, 0, 0],   // [X, Y, Z, T]
    entering: false,       // se estamos digitando um número
    hasDecimal: false,     // se já digitamos ponto
    justCalculated: false, // se acabou de fazer operação
    // Registradores financeiros
    N: 0,
    i: 0,   // em %
    PV: 0,
    PMT: 0,
    FV: 0
  };

  let displayEl = null;

  // ==========================
  // FUNÇÕES DE PILHA (RPN)
  // ==========================
  function getX() {
    return state.stack[0];
  }

  function setX(v) {
    state.stack[0] = v;
    updateDisplay();
  }

  function pushStack() {
    state.stack[3] = state.stack[2];
    state.stack[2] = state.stack[1];
    state.stack[1] = state.stack[0];
  }

  function popStackToX(v) {
    // Coloca v em X e desce a pilha
    state.stack[0] = v;
    state.stack[1] = state.stack[2];
    state.stack[2] = state.stack[3];
    updateDisplay();
  }

  function clearStack() {
    state.stack = [0, 0, 0, 0];
    state.entering = false;
    state.hasDecimal = false;
    state.justCalculated = false;
    updateDisplay();
  }

  // ==========================
  // DISPLAY
  // ==========================
  function formatNumber(num) {
    if (!isFinite(num)) return 'Error';

    let s = num.toString();

    // Tentar formato mais "calculadora"
    // Limite de 10-11 caracteres
    if (Math.abs(num) >= 1e10 || (Math.abs(num) > 0 && Math.abs(num) < 1e-6)) {
      s = num.toExponential(6);
    } else {
      s = num.toLocaleString('en-US', {
        maximumFractionDigits: 10,
        useGrouping: false
      });
    }

    if (s.length > 12) {
      s = num.toExponential(6);
    }
    return s;
  }

  function updateDisplay() {
    if (!displayEl) return;
    displayEl.textContent = formatNumber(getX());
  }

  // ==========================
  // DIGITAÇÃO
  // ==========================
  function pressDigit(d) {
    if (state.justCalculated) {
      // Começar novo número após cálculo
      state.entering = false;
      state.hasDecimal = false;
      state.justCalculated = false;
    }

    let x = getX().toString();

    if (!state.entering) {
      // Começa novo número
      x = d.toString();
      state.entering = true;
      state.hasDecimal = false;
    } else {
      x += d.toString();
    }

    const parsed = Number(x);
    if (!isNaN(parsed)) {
      state.stack[0] = parsed;
      updateDisplay();
    }
  }

  function pressDecimal() {
    if (state.justCalculated) {
      state.entering = false;
      state.justCalculated = false;
      state.hasDecimal = false;
    }

    if (!state.entering) {
      state.stack[0] = 0;
      state.entering = true;
      state.hasDecimal = true;
      displayEl.textContent = '0.';
      return;
    }

    if (!state.hasDecimal) {
      displayEl.textContent = formatNumber(getX()) + '.';
      state.hasDecimal = true;
    }
  }

  function pressEnter() {
    pushStack();
    state.entering = false;
    state.hasDecimal = false;
    state.justCalculated = false;
    updateDisplay();
  }

  function pressBackspace() {
    if (!state.entering) return;
    let xStr = displayEl.textContent;
    if (xStr === 'Error') return;

    if (xStr.endsWith('.')) {
      xStr = xStr.slice(0, -1);
      state.hasDecimal = false;
    } else {
      xStr = xStr.slice(0, -1);
    }

    if (xStr === '' || xStr === '-') {
      state.stack[0] = 0;
    } else {
      const parsed = Number(xStr);
      if (!isNaN(parsed)) {
        state.stack[0] = parsed;
      }
    }
    updateDisplay();
  }

  function pressCHS() {
    state.stack[0] = -getX();
    updateDisplay();
  }

  function pressCLX() {
    state.stack[0] = 0;
    state.entering = false;
    state.hasDecimal = false;
    updateDisplay();
  }

  // ==========================
  // OPERAÇÕES BÁSICAS
  // ==========================
  function binaryOp(fn) {
    const x = state.stack[0];
    const y = state.stack[1];
    const result = fn(y, x);

    state.stack[0] = result;
    state.stack[1] = state.stack[2];
    state.stack[2] = state.stack[3];

    state.entering = false;
    state.hasDecimal = false;
    state.justCalculated = true;
    updateDisplay();
  }

  function pressAdd() {
    binaryOp((y, x) => y + x);
  }

  function pressSub() {
    binaryOp((y, x) => y - x);
  }

  function pressMul() {
    binaryOp((y, x) => y * x);
  }

  function pressDiv() {
    binaryOp((y, x) => (x === 0 ? NaN : y / x));
  }

  function pressSqrt() {
    const x = getX();
    state.stack[0] = x < 0 ? NaN : Math.sqrt(x);
    state.entering = false;
    state.justCalculated = true;
    updateDisplay();
  }

  function pressInv() {
    const x = getX();
    state.stack[0] = x === 0 ? NaN : 1 / x;
    state.entering = false;
    state.justCalculated = true;
    updateDisplay();
  }

  function pressPercent() {
    // % estilo calculadora: Y * (X/100)
    const x = getX();
    const y = state.stack[1];
    state.stack[0] = (y * x) / 100;
    state.entering = false;
    state.justCalculated = true;
    updateDisplay();
  }

  // ==========================
  // FINANCEIRO (TVM SIMPLIFICADO)
  // ==========================
  // Assumimos:
  // N  = número de períodos
  // i  = taxa por período (%)
  // PV = valor presente
  // PMT = pagamento por período (fim de período)
  // FV = valor futuro
  //
  // Fórmulas padrão:
  // FV = PV*(1+i)^N + PMT * ((1+i)^N - 1)/i
  // PV = (FV - PMT * ((1+i)^N - 1)/i) / (1+i)^N
  // PMT = (FV - PV*(1+i)^N) * i / ((1+i)^N - 1)
  //
  // Convenção de sinal real do HP-12C é mais complexa;
  // aqui deixamos tudo "algébrico" para simplificar.

  function storeRegister(key) {
    const x = getX();
    switch (key) {
      case 'N':
        state.N = x;
        break;
      case 'i':
        state.i = x;
        break;
      case 'PV':
        state.PV = x;
        break;
      case 'PMT':
        state.PMT = x;
        break;
      case 'FV':
        state.FV = x;
        break;
    }
  }

  function calcFV() {
    const n = state.N;
    const i = state.i / 100;
    const pv = state.PV;
    const pmt = state.PMT;

    const factor = Math.pow(1 + i, n);
    const fv = pv * factor + pmt * ((factor - 1) / i);

    state.FV = fv;
    popStackToX(fv);
    state.justCalculated = true;
  }

  function calcPV() {
    const n = state.N;
    const i = state.i / 100;
    const fv = state.FV;
    const pmt = state.PMT;

    const factor = Math.pow(1 + i, n);
    const pv = (fv - pmt * ((factor - 1) / i)) / factor;

    state.PV = pv;
    popStackToX(pv);
    state.justCalculated = true;
  }

  function calcPMT() {
    const n = state.N;
    const i = state.i / 100;
    const pv = state.PV;
    const fv = state.FV;

    const factor = Math.pow(1 + i, n);
    const pmt = (fv - pv * factor) * i / (factor - 1);

    state.PMT = pmt;
    popStackToX(pmt);
    state.justCalculated = true;
  }

  function pressN() {
    storeRegister('N');
  }

  function pressI() {
    storeRegister('i');
  }

  function pressPV() {
    // Se estamos só armazenando:
    if (state.entering || state.justCalculated === false) {
      storeRegister('PV');
    } else {
      // Se quiser forçar cálculo de PV com base nos regs:
      calcPV();
    }
  }

  function pressPMT() {
    if (state.entering || state.justCalculated === false) {
      storeRegister('PMT');
    } else {
      calcPMT();
    }
  }

  function pressFV() {
    if (state.entering || state.justCalculated === false) {
      storeRegister('FV');
    } else {
      calcFV();
    }
  }

  // ==========================
  // RENDERIZAÇÃO (VIA JS)
  // ==========================
  function createStyle() {
    const style = document.createElement('style');
    style.textContent = `
      .hp12c-root {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        width: 360px;
        padding: 16px;
        background: linear-gradient(135deg, #3b2f2f, #171213);
        border-radius: 16px;
        box-shadow: 0 14px 35px rgba(0,0,0,0.6);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      }

      .hp12c-body {
        width: 100%;
        background: linear-gradient(180deg, #222, #111);
        border-radius: 12px;
        padding: 12px;
        border: 2px solid #c49c48;
        box-shadow:
          inset 0 0 0 1px rgba(255,255,255,0.08),
          0 4px 10px rgba(0,0,0,0.9);
        color: #f5f5f5;
      }

      .hp12c-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 12px;
        color: #d0b36c;
      }

      .hp12c-logo {
        font-weight: 600;
        letter-spacing: 0.04em;
      }

      .hp12c-model {
        font-size: 11px;
        opacity: 0.8;
      }

      .hp12c-display {
        background: radial-gradient(circle at top left, #3f4a3f 0%, #1a1f1a 55%, #101410 100%);
        border-radius: 6px;
        border: 1px solid #222;
        box-shadow:
          inset 0 0 0 1px rgba(255,255,255,0.07),
          0 2px 4px rgba(0,0,0,0.8);
        padding: 12px 10px;
        margin-bottom: 10px;
        text-align: right;
        font-family: "SF Mono", "Roboto Mono", monospace;
        font-size: 22px;
        color: #f5fbe0;
        text-shadow: 0 0 3px rgba(255,255,200,0.7);
        overflow: hidden;
        white-space: nowrap;
      }

      .hp12c-keys {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
      }

      .hp12c-key {
        border: none;
        outline: none;
        border-radius: 5px;
        padding: 8px 4px;
        font-size: 13px;
        cursor: pointer;
        color: #f7f7f7;
        background: linear-gradient(180deg, #444, #222);
        box-shadow:
          0 2px 4px rgba(0,0,0,0.9),
          inset 0 0 0 1px rgba(255,255,255,0.05);
        transition: transform 0.05s ease, box-shadow 0.05s ease, background 0.1s ease;
      }

      .hp12c-key:active {
        transform: translateY(1px);
        box-shadow:
          0 1px 2px rgba(0,0,0,0.7),
          inset 0 0 0 1px rgba(255,255,255,0.02);
      }

      .hp12c-key-wide {
        grid-column: span 2;
      }

      .hp12c-key-orange {
        background: linear-gradient(180deg, #f3a848, #b26b1f);
        color: #2b1a00;
        font-weight: 600;
      }

      .hp12c-key-blue {
        background: linear-gradient(180deg, #4068c4, #1f3577);
      }

      .hp12c-key-op {
        background: linear-gradient(180deg, #555, #111);
      }

      .hp12c-key-fn {
        background: linear-gradient(180deg, #333, #111);
        font-size: 12px;
      }

      .hp12c-spacer-row {
        height: 4px;
        grid-column: 1 / -1;
      }

      .hp12c-key-label-small {
        font-size: 11px;
      }

      @media (max-width: 420px) {
        .hp12c-root {
          width: 100%;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function createLayout() {
    const root = document.createElement('div');
    root.className = 'hp12c-root';

    const body = document.createElement('div');
    body.className = 'hp12c-body';

    // Header
    const header = document.createElement('div');
    header.className = 'hp12c-header';

    const logo = document.createElement('div');
    logo.className = 'hp12c-logo';
    logo.textContent = 'Workz!';

    const model = document.createElement('div');
    model.className = 'hp12c-model';
    model.textContent = '12C FINANCIAL';

    header.appendChild(logo);
    header.appendChild(model);

    // Display
    const display = document.createElement('div');
    display.className = 'hp12c-display';
    display.textContent = '0';
    displayEl = display;

    // Keys grid
    const keys = document.createElement('div');
    keys.className = 'hp12c-keys';

    // Helper pra criar botões
    function addKey(label, options, handler) {
      const btn = document.createElement('button');
      btn.className = 'hp12c-key';
      if (options && options.className) {
        btn.className += ' ' + options.className;
      }
      btn.textContent = label;
      btn.addEventListener('click', handler);
      keys.appendChild(btn);
    }

    function addSpacerRow() {
      const s = document.createElement('div');
      s.className = 'hp12c-spacer-row';
      keys.appendChild(s);
    }

    // ----------
    // Linha 1
    // ----------
    addKey('ON/C', { className: 'hp12c-key-orange hp12c-key-label-small' }, () => {
      clearStack();
    });

    addKey('CLX', { className: 'hp12c-key-fn' }, () => {
      pressCLX();
    });

    addKey('ENTER', { className: 'hp12c-key-op hp12c-key-wide' }, () => {
      pressEnter();
    });

    addKey('←', { className: 'hp12c-key-fn' }, () => {
      pressBackspace();
    });

    addSpacerRow();

    // ----------
    // Linha 2
    // ----------
    addKey('7', null, () => pressDigit(7));
    addKey('8', null, () => pressDigit(8));
    addKey('9', null, () => pressDigit(9));
    addKey('÷', { className: 'hp12c-key-op' }, () => pressDiv());
    addKey('√x', { className: 'hp12c-key-fn' }, () => pressSqrt());

    // ----------
    // Linha 3
    // ----------
    addKey('4', null, () => pressDigit(4));
    addKey('5', null, () => pressDigit(5));
    addKey('6', null, () => pressDigit(6));
    addKey('×', { className: 'hp12c-key-op' }, () => pressMul());
    addKey('1/x', { className: 'hp12c-key-fn' }, () => pressInv());

    // ----------
    // Linha 4
    // ----------
    addKey('1', null, () => pressDigit(1));
    addKey('2', null, () => pressDigit(2));
    addKey('3', null, () => pressDigit(3));
    addKey('−', { className: 'hp12c-key-op' }, () => pressSub());
    addKey('%', { className: 'hp12c-key-fn' }, () => pressPercent());

    // ----------
    // Linha 5
    // ----------
    addKey('0', { className: 'hp12c-key-wide' }, () => pressDigit(0));
    addKey('.', null, () => pressDecimal());
    addKey('CHS', { className: 'hp12c-key-fn' }, () => pressCHS());
    addKey('+', { className: 'hp12c-key-op' }, () => pressAdd());

    addSpacerRow();

    // ----------
    // Linha 6 - Financeiro
    // ----------
    addKey('N', { className: 'hp12c-key-blue' }, () => pressN());
    addKey('i', { className: 'hp12c-key-blue' }, () => pressI());
    addKey('PV', { className: 'hp12c-key-blue' }, () => pressPV());
    addKey('PMT', { className: 'hp12c-key-blue' }, () => pressPMT());
    addKey('FV', { className: 'hp12c-key-blue' }, () => pressFV());

    body.appendChild(header);
    body.appendChild(display);
    body.appendChild(keys);
    root.appendChild(body);

    document.body.style.margin = '0';
    document.body.style.background = '#222';
    document.body.style.display = 'flex';
    document.body.style.justifyContent = 'center';
    document.body.style.alignItems = 'center';
    document.body.style.height = '100vh';

    document.body.appendChild(root);

    // Inicializa display
    updateDisplay();
  }

  // ==========================
  // INICIALIZAÇÃO
  // ==========================
  function init() {
    createStyle();
    createLayout();
  }

  init();
  
})();
    
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