// Simple Calculator App - Database Storage Example
// This app demonstrates a simple JavaScript application suitable for database storage

class Calculator {
    constructor() {
        this.display = null;
        this.currentInput = '0';
        this.previousInput = null;
        this.operator = null;
        this.waitingForOperand = false;
        this.history = [];
        
        this.init();
    }
    
    async init() {
        try {
            // Initialize Workz SDK
            await WorkzSDK.init({
                apiUrl: 'https://api.workz.com',
                token: window.WORKZ_APP_TOKEN
            });
            
            console.log('Calculator initialized with Workz SDK');
            
            // Load calculation history
            await this.loadHistory();
            
            // Setup UI
            this.setupUI();
            this.setupEventListeners();
            
        } catch (error) {
            console.error('Failed to initialize calculator:', error);
        }
    }
    
    setupUI() {
        document.body.innerHTML = `
            <div class="calculator">
                <div class="display">
                    <div class="previous-operand" id="previousOperand"></div>
                    <div class="current-operand" id="currentOperand">0</div>
                </div>
                
                <div class="buttons">
                    <button class="btn btn-clear" onclick="calculator.clear()">C</button>
                    <button class="btn btn-clear" onclick="calculator.clearEntry()">CE</button>
                    <button class="btn btn-operator" onclick="calculator.inputOperator('/')">/</button>
                    <button class="btn btn-operator" onclick="calculator.inputOperator('*')">×</button>
                    
                    <button class="btn btn-number" onclick="calculator.inputNumber('7')">7</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('8')">8</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('9')">9</button>
                    <button class="btn btn-operator" onclick="calculator.inputOperator('-')">-</button>
                    
                    <button class="btn btn-number" onclick="calculator.inputNumber('4')">4</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('5')">5</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('6')">6</button>
                    <button class="btn btn-operator" onclick="calculator.inputOperator('+')">+</button>
                    
                    <button class="btn btn-number" onclick="calculator.inputNumber('1')">1</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('2')">2</button>
                    <button class="btn btn-number" onclick="calculator.inputNumber('3')">3</button>
                    <button class="btn btn-equals" onclick="calculator.calculate()" rowspan="2">=</button>
                    
                    <button class="btn btn-number btn-zero" onclick="calculator.inputNumber('0')">0</button>
                    <button class="btn btn-number" onclick="calculator.inputDecimal()">.</button>
                </div>
                
                <div class="history">
                    <h3>History</h3>
                    <div id="historyList"></div>
                    <button class="btn btn-clear-history" onclick="calculator.clearHistory()">Clear History</button>
                </div>
            </div>
        `;
        
        this.display = document.getElementById('currentOperand');
        this.previousDisplay = document.getElementById('previousOperand');
        this.historyList = document.getElementById('historyList');
        
        this.updateDisplay();
        this.updateHistory();
    }
    
    setupEventListeners() {
        // Keyboard support
        document.addEventListener('keydown', (e) => {
            if (e.key >= '0' && e.key <= '9') {
                this.inputNumber(e.key);
            } else if (e.key === '.') {
                this.inputDecimal();
            } else if (['+', '-', '*', '/'].includes(e.key)) {
                this.inputOperator(e.key === '*' ? '*' : e.key);
            } else if (e.key === 'Enter' || e.key === '=') {
                this.calculate();
            } else if (e.key === 'Escape') {
                this.clear();
            } else if (e.key === 'Backspace') {
                this.backspace();
            }
        });
    }
    
    inputNumber(num) {
        if (this.waitingForOperand) {
            this.currentInput = num;
            this.waitingForOperand = false;
        } else {
            this.currentInput = this.currentInput === '0' ? num : this.currentInput + num;
        }
        
        this.updateDisplay();
    }
    
    inputDecimal() {
        if (this.waitingForOperand) {
            this.currentInput = '0.';
            this.waitingForOperand = false;
        } else if (this.currentInput.indexOf('.') === -1) {
            this.currentInput += '.';
        }
        
        this.updateDisplay();
    }
    
    inputOperator(nextOperator) {
        const inputValue = parseFloat(this.currentInput);
        
        if (this.previousInput === null) {
            this.previousInput = inputValue;
        } else if (this.operator) {
            const currentValue = this.previousInput || 0;
            const newValue = this.performCalculation();
            
            this.currentInput = String(newValue);
            this.previousInput = newValue;
        }
        
        this.waitingForOperand = true;
        this.operator = nextOperator;
        this.updateDisplay();
    }
    
    calculate() {
        if (this.operator && this.previousInput !== null && !this.waitingForOperand) {
            const result = this.performCalculation();
            
            // Add to history
            const calculation = {
                expression: `${this.previousInput} ${this.getOperatorSymbol(this.operator)} ${this.currentInput}`,
                result: result,
                timestamp: new Date().toISOString()
            };
            
            this.history.unshift(calculation);
            this.saveHistory();
            
            this.currentInput = String(result);
            this.previousInput = null;
            this.operator = null;
            this.waitingForOperand = true;
            
            this.updateDisplay();
            this.updateHistory();
        }
    }
    
    performCalculation() {
        const prev = parseFloat(this.previousInput);
        const current = parseFloat(this.currentInput);
        
        switch (this.operator) {
            case '+':
                return prev + current;
            case '-':
                return prev - current;
            case '*':
                return prev * current;
            case '/':
                return current !== 0 ? prev / current : 0;
            default:
                return current;
        }
    }
    
    getOperatorSymbol(operator) {
        switch (operator) {
            case '*': return '×';
            case '/': return '÷';
            default: return operator;
        }
    }
    
    clear() {
        this.currentInput = '0';
        this.previousInput = null;
        this.operator = null;
        this.waitingForOperand = false;
        this.updateDisplay();
    }
    
    clearEntry() {
        this.currentInput = '0';
        this.updateDisplay();
    }
    
    backspace() {
        if (this.currentInput.length > 1) {
            this.currentInput = this.currentInput.slice(0, -1);
        } else {
            this.currentInput = '0';
        }
        this.updateDisplay();
    }
    
    updateDisplay() {
        this.display.textContent = this.currentInput;
        
        if (this.operator && this.previousInput !== null) {
            this.previousDisplay.textContent = `${this.previousInput} ${this.getOperatorSymbol(this.operator)}`;
        } else {
            this.previousDisplay.textContent = '';
        }
    }
    
    updateHistory() {
        this.historyList.innerHTML = '';
        
        this.history.slice(0, 10).forEach(calc => {
            const historyItem = document.createElement('div');
            historyItem.className = 'history-item';
            historyItem.innerHTML = `
                <div class="expression">${calc.expression} = ${calc.result}</div>
                <div class="timestamp">${new Date(calc.timestamp).toLocaleTimeString()}</div>
            `;
            
            historyItem.addEventListener('click', () => {
                this.currentInput = String(calc.result);
                this.updateDisplay();
            });
            
            this.historyList.appendChild(historyItem);
        });
    }
    
    async loadHistory() {
        try {
            const savedHistory = await WorkzSDK.kv.get('calculator_history');
            if (savedHistory) {
                this.history = JSON.parse(savedHistory);
            }
        } catch (error) {
            console.error('Failed to load history:', error);
        }
    }
    
    async saveHistory() {
        try {
            // Keep only last 50 calculations
            const historyToSave = this.history.slice(0, 50);
            await WorkzSDK.kv.set('calculator_history', JSON.stringify(historyToSave));
        } catch (error) {
            console.error('Failed to save history:', error);
        }
    }
    
    async clearHistory() {
        this.history = [];
        await this.saveHistory();
        this.updateHistory();
    }
}

// Global calculator instance
let calculator;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    calculator = new Calculator();
});