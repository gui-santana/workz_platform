<style>
	:root {
		--digit-keys: #505050;
		--operator-keys: #ef865f;
		--function-keys: #a2a2a2;
	}
	.calculator {      
		border-radius: 1rem;      
		overflow: hidden;
		width: min(100vw, 350px);
		padding: 2vw 0;
	}
	.display {      
		color: #fff;
		font-size: calc(4rem + 4vw);
		text-align: right;
		padding: 1rem 0;
		min-height: 4rem;
		word-wrap: break-word;
	}
	.calculator-keys {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 0.25rem;
		padding: 1rem;
		background: var(--background-color);
	}
	.key {
		width: 100%;
		aspect-ratio: 1 / 1;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 1.2rem;
		border: none;
		border-radius: 50%;
		background: var(--digit-keys);
		color: #fff;
		cursor: pointer;
		transition: filter 0.2s;
	}
	.key:active {
		filter: brightness(0.8);
	}
	.key.operator {
		background: var(--operator-keys);
	}
	.key.function {
		background: var(--function-keys);
		color: var(--background-color);
	}
	.key.zero {
		grid-column: span 2;
		aspect-ratio: 2 / 1;
		border-radius: 3rem;
		justify-content: flex-start;
		padding-left: 1.5rem;
	}
	@media (min-width: 400px) {
		.key { font-size: 1.4rem; }
	}
</style>
<div class="calculator centered">
	<div id="display" class="display">0</div>
	<div class="calculator-keys">
		<button class="key function" data-action="clear">AC</button>
		<button class="key function" data-action="plus-minus">±</button>
		<button class="key function" data-action="percentage">%</button>
		<button class="key operator" data-action="divide">÷</button>

		<button class="key" data-action="digit" data-value="7">7</button>
		<button class="key" data-action="digit" data-value="8">8</button>
		<button class="key" data-action="digit" data-value="9">9</button>
		<button class="key operator" data-action="multiply">×</button>

		<button class="key" data-action="digit" data-value="4">4</button>
		<button class="key" data-action="digit" data-value="5">5</button>
		<button class="key" data-action="digit" data-value="6">6</button>
		<button class="key operator" data-action="subtract">−</button>

		<button class="key" data-action="digit" data-value="1">1</button>
		<button class="key" data-action="digit" data-value="2">2</button>
		<button class="key" data-action="digit" data-value="3">3</button>
		<button class="key operator" data-action="add">+</button>

		<button class="key zero" data-action="digit" data-value="0">0</button>
		<button class="key" data-action="decimal">,</button>
		<button class="key operator" data-action="equals">=</button>
	</div>
</div>
<script>
	// Mapeamento de símbolos para operadores
	const opSymbols = { add: '+', subtract: '−', multiply: '×', divide: '÷' };

	class Calculator {
		constructor(displayElement) {
			this.displayElement = displayElement;
			this.clear();
		}
		clear() {
			this.currentValue = '0';
			this.previousValue = null;
			this.operator = null;
			this.shouldReset = false;
			this.updateDisplay();
		}
		inputDigit(digit) {
			if (this.shouldReset) {
				this.currentValue = digit;
				this.shouldReset = false;
			} else {
				this.currentValue = this.currentValue === '0' ? digit : this.currentValue + digit;
			}
		}
		inputDecimal() {
			if (!this.currentValue.includes('.')) this.currentValue += '.';
		}
		toggleSign() {
			this.currentValue = (parseFloat(this.currentValue) * -1).toString();
		}
		percent() {
			this.currentValue = (parseFloat(this.currentValue) / 100).toString();
		}
		operate(nextOperator) {
			const inputValue = parseFloat(this.currentValue);
			if (this.operator && !this.shouldReset) {
				let result;
				switch (this.operator) {
					case 'add': result = this.previousValue + inputValue; break;
					case 'subtract': result = this.previousValue - inputValue; break;
					case 'multiply': result = this.previousValue * inputValue; break;
					case 'divide': result = this.previousValue / inputValue; break;
					default: result = inputValue;
				}
				this.currentValue = result.toString();
				this.previousValue = result;
			} else {
				this.previousValue = inputValue;
			}
			this.shouldReset = true;
			this.operator = nextOperator === 'equals' ? null : nextOperator;
		}
		updateDisplay() {
			this.displayElement.textContent = this.currentValue.replace('.', ',');
		}
	}

	const display = document.getElementById('display');
	const calculator = new Calculator(display);

	document.querySelector('.calculator-keys').addEventListener('click', e => {
		if (!e.target.matches('button')) return;
		const { action, value } = e.target.dataset;
		switch (action) {
			case 'digit': calculator.inputDigit(value); calculator.updateDisplay(); break;
			case 'decimal': calculator.inputDecimal(); calculator.updateDisplay(); break;
			case 'clear': calculator.clear(); break;
			case 'plus-minus': calculator.toggleSign(); calculator.updateDisplay(); break;
			case 'percentage': calculator.percent(); calculator.updateDisplay(); break;
			case 'add':
			case 'subtract':
			case 'multiply':
			case 'divide':
				calculator.operate(action);
				// mostra símbolo de operação
				display.textContent = opSymbols[action];
				break;
			case 'equals': calculator.operate(action); calculator.updateDisplay(); break;
		}
	});

	document.addEventListener('keydown', e => {
		const keyMap = {
			'0':'digit','1':'digit','2':'digit','3':'digit','4':'digit','5':'digit','6':'digit','7':'digit','8':'digit','9':'digit',
			'.':'decimal',',':'decimal','+':'add','-':'subtract','*':'multiply','/':'divide','=':'equals','Enter':'equals','Backspace':'clear','%':'percentage','n':'plus-minus','N':'plus-minus'
		};
		const action = keyMap[e.key];
		if (!action) return;
		e.preventDefault();
		if (action === 'digit') { calculator.inputDigit(e.key === ',' ? ',' : e.key); calculator.updateDisplay(); }
		else if (action === 'decimal') { calculator.inputDecimal(); calculator.updateDisplay(); }
		else if (action === 'clear') calculator.clear();
		else if (action === 'plus-minus') { calculator.toggleSign(); calculator.updateDisplay(); }
		else if (action === 'percentage') { calculator.percent(); calculator.updateDisplay(); }
		else if (['add','subtract','multiply','divide'].includes(action)) {
			calculator.operate(action); display.textContent = opSymbols[action];
		} else if (action === 'equals') { calculator.operate(action); calculator.updateDisplay(); }
	});
</script>