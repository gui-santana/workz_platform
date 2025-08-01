
<style>
	:root {
		--digit-keys: #505050;
		--operator-keys: #FF9500;
		--background-color: #1C1C1C;
		--function-keys: #a2a2a2;
	}
	
	* {
		margin: 0;
		padding: 0;
		box-sizing: border-box;
		user-select: none;
		font-family: Helvetica, sans-serif;
	}

	body { 
		background-color: var(--background-color);
		color: #ffffff;
		height: 100%;
		width: 100%;
	}

	a, a:visited {
		color: #ffffff;
	}

	li {
		list-style: none;
	}

	span {
		font-weight: bold;
	}

	hr {
		margin: 0;
		align-self: stretch;
	}

	/* mobile first */
	.col:first-child {
		order: 2;
	}

	.calculator {
		display: flex;
		flex-direction: column;
		min-height: 500px;
		min-width: 290px;
		font-size: 1.3rem;
	}

	.main-content {
		display: flex;
		flex-direction: column;
		padding: 0 3rem 0 3rem;
	}

	.main-content main, .main-content section {
		padding: 1rem 0 1rem;
		width: 100%;
	}

	.main-content header, .main-content section h2 {
		padding-bottom: 1rem;
		width: 100%;
	}


	.calculator .screen {
		display: flex;
		justify-content: end;
		align-items: flex-end;
		padding: 0 0 15px 0;
		font-size: 5.5rem;
		width: 290px;
		height: 150px;
		overflow-x: hidden;
	}

	.calculator .keyboard {
		width: 290px;
		height: 350px;
	}

	/* applying background colors */

	/* first row */
	.keyboard .row:first-child .sub-col:not(:last-child) {
		background-color: var(--function-keys);
		color: var(--background-color);
	}

	.keyboard .row:first-child .sub-col:last-child {
		background-color: var(--operator-keys);
		color: #ffffff;
	}

	/* second row, third row, fourth row and fifth row */
	.keyboard .row:nth-child(2) .sub-col:not(:last-child), 
	.keyboard .row:nth-child(3) .sub-col:not(:last-child), 
	.keyboard .row:nth-child(4) .sub-col:not(:last-child), 
	.keyboard .row:nth-child(5) .sub-col:not(:last-child) {
		background-color: var(--digit-keys);
		color: #ffffff;
	}

	.keyboard .row:nth-child(2) .sub-col:last-child, 
	.keyboard .row:nth-child(3) .sub-col:last-child, 
	.keyboard .row:nth-child(4) .sub-col:last-child, 
	.keyboard .row:nth-child(5) .sub-col:last-child {
		background-color: var(--operator-keys);
		color: #ffffff;
	}

	.selected_operation[data-value='clear'], 
	.selected_operation[data-value='%'], 
	.selected_operation[data-value='+/-'] {
		animation: background-anim-fn .8s ease-out;
	}

	.selected_operation[data-value='='] {
		animation: background-anim-equals .8s ease-out;
	}

	/* Select every operator button except clear, %, +/- and = */
	.selected_operation[data-button-type='operator']:not(.selected_operation[data-value='clear']):not(.selected_operation[data-value='%']):not(.selected_operation[data-value='+/-']):not(.selected_operation[data-value='=']) {
		background-color: #d4d4d2;
		color: var(--operator-keys);   
		transition: background-color, color .8s ease-out;
	}

	@keyframes background-anim-fn { 
		0% { background-color: var(--function-keys); }
		50% { background-color: #fff;  }
		100% { background-color: var(--function-keys); }
	}

	@keyframes background-anim-equals {
		0% { background-color: var(--operator-keys); }
		50% { background-color: #fff;  }
		100% { background-color: var(--operator-keys); }
	}
	/* utilities */

	.row {
		display: -webkit-box;
		display: -ms-flexbox;
		display: flex;
		-ms-flex-wrap: wrap;
		flex-wrap: wrap;
	}

	.col { 
		display: flex;
		position: relative;
		width: 100%;
		-webkit-box-flex: 0;
		-ms-flex: 0 0 100%;
		flex: 0 0 100%;
		max-width: 100%;
		height: 100vh;
	}

	@media only screen and (min-width: 992px) {
		.col { 
			-ms-flex: 0 0 50%;
			flex: 0 0 50%;
			max-width: 50%;
		}
	}

	.flex-center {
		justify-content: center;
		align-items: center;
	}

	.sub-col {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 21%;
		height: 100%;
		-webkit-box-flex: 0;
		-ms-flex: 0 0 21%;
		flex: 0 0 21%;
		max-width: 21%;
		border-radius: 50%;
		transition: background-color .8s ease-out;
		
	}

	.row:last-child > .sub-col:first-child {
		display: flex;
		justify-content: start;
		padding: 25px;
		width: 47%;
		-ms-flex: 0 0 47%;
		flex: 0 0 47%;
		max-width: 47%;
		border-radius: 100px 100px 100px 100px;
		transition: background-color .8s ease-out;
	}

	.keyboard .row {
		justify-content: space-between;
		height: 20%;
	}

	/* responsive settings */

	@media only screen and (min-width: 992px) {
		.col:first-child {
			order: 0;
		}

		.main-content {
			display: flex;
			justify-content: center;
			align-items: center;
			padding: 0;
			max-width: 350px;
		}
	}

	@media only screen and (min-width: 1500px) {
		.main-content {
			font-size: 1.4rem;
			max-width: 450px
		}

		.calculator {
			min-height: calc(500px / .7);
			min-width: calc(290px / .7);
			font-size: 1.7rem;
		}

		.calculator .screen {
			display: flex;
			justify-content: end;
			align-items: flex-end;
			padding: 0 0 15px 0;
			font-size: 7rem;
			width: calc(290px / .7);
			height: calc(150px / .7);
			overflow-x: hidden;
		}
		
		.calculator .keyboard {
			width: calc(290px / .7);
			height: calc(350px / .7);
		}

	}

</style>
<div class="row" style="background-color: #1C1C1C;">
	<div class="col flex-center">
		<div class="calculator">
			<div class="screen">
				0
			</div>
			<div class="keyboard">
				<div class="row">
					<div class="sub-col" data-button-type="operator" data-value="clear" id="clear_button">AC</div>
					<div class="sub-col" data-button-type="operator" data-value="+/-">+/-</div>
					<div class="sub-col" data-button-type="operator" data-value="%">%</div>
					<div class="sub-col" data-button-type="operator" data-value="/">÷</div>
				</div>
				<div class="row">
					<div class="sub-col" data-button-type="digit" data-value="7">7</div>
					<div class="sub-col" data-button-type="digit" data-value="8">8</div>
					<div class="sub-col" data-button-type="digit" data-value="9">9</div>
					<div class="sub-col" data-button-type="operator" data-value="*">x</div>
				</div>
				<div class="row">
					<div class="sub-col" data-button-type="digit" data-value="4">4</div>
					<div class="sub-col" data-button-type="digit" data-value="5">5</div>
					<div class="sub-col" data-button-type="digit" data-value="6">6</div>
					<div class="sub-col" data-button-type="operator" data-value="-">-</div>
				</div>
				<div class="row">
					<div class="sub-col" data-button-type="digit" data-value="1">1</div>
					<div class="sub-col" data-button-type="digit" data-value="2">2</div>
					<div class="sub-col" data-button-type="digit" data-value="3">3</div>
					<div class="sub-col" data-button-type="operator" data-value="+">+</div>
				</div>
				<div class="row">
					<div class="sub-col" data-button-type="digit" data-value="0">0</div>
					<div class="sub-col" data-button-type="digit" data-value=".">,</div>
					<div class="sub-col" data-button-type="operator" data-value="=">=</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	let first_number = "0";
	let second_number = "0";
	let result = "0";
	let current_operator;
	let evaluation = [];
	const screen = document.querySelector(".screen");
	const keyboard = document.querySelector(".keyboard");

	keyboard.addEventListener('click', function(e) {
		e.stopImmediatePropagation()
		onButtonPress(e);
	});

	function onButtonPress (e) {
		switch(e.target.getAttribute('data-button-type')) {
			case "digit":
				AssignNumber(e)
				break;
			case "operator":
				AssignOperation(e)
				break;
		}

		Render(e);
	}

	function AssignNumber(e) {

		if(evaluation.length <= 1) {
			first_number = first_number == "0" 
				? e.target.getAttribute("data-value")
				: first_number + e.target.getAttribute("data-value")

			if(evaluation.length == 1) evaluation.shift();
			evaluation.push(first_number)
			result = first_number;
			return;
		}

		if (evaluation.length >= 2) {
			second_number = second_number == "0"
				? e.target.getAttribute("data-value")
				: second_number + e.target.getAttribute("data-value");

			if(evaluation.length == 3) evaluation.pop();
			evaluation.push(second_number);
			result = second_number;

		}
	}

	function AssignOperation(e) {
		current_operator = e.target.getAttribute('data-value');

		// Exclusive operations that can be performed with one number, in the case of clear it can be executed even when the evaluation array is empty
		if(current_operator == "%" || current_operator == "+/-" || current_operator == "clear" || current_operator == "=") return Operate();

		if(evaluation.length == 3) Operate();
		if(evaluation.length == 2) evaluation.pop();
		evaluation.splice(1, 1, current_operator);
	}

	function Operate() { 
		if(current_operator == "%" && evaluation.length) {
			let number = parseInt(evaluation[evaluation.length - 1])
			result =  (number / 100).toString();
			evaluation.splice(evaluation.length - 1, 1, result);
			return;
		}

		if(current_operator == "+/-" && evaluation.length) {
			result = (evaluation[evaluation.length - 1] * -1).toString();
			evaluation.splice(evaluation.length - 1, 1, result);
			return;
		}

		if(current_operator == "clear") {
			
			if(evaluation.length <= 2) {
				first_number = "0";
				evaluation = [];
				result = "0";
				return;
			}
		
			if(evaluation.length == 3) {
				second_number = "0";
				evaluation = [first_number.toString()]
				result = first_number.toString();
				return;
			}

		}

		if(evaluation.length == 3) {
			result = (eval(evaluation.join().replace(/,/g, ""))).toString();
			first_number = result;
			second_number = "0";
			evaluation = [first_number]
		}
	}

	function Render(e) {
		const clear_button = document.querySelector('div[data-value="clear"]');

		let new_operator_button = e.target;

		let last_operator_button = document.querySelector('.selected_operation');

		last_operator_button ? last_operator_button.classList.remove('selected_operation') : null;
		new_operator_button ? new_operator_button.classList.add('selected_operation') : null;

		// change screen's font-size
		switch(result.toString().length) {
			case 7:
				screen.style.fontSize = "4.7rem"
				break;
			case 8:
				screen.style.fontSize = "4.1rem"
				break;
			case 9: 
				screen.style.fontSize = "3.65rem"
				break
		}

		if(result.toString().length > 9) {
			screen.textContent = parseFloat(result).toPrecision(3);
		} else {
			screen.textContent = result;
		}

		evaluation.length == "0"
			? clear_button.textContent = 'AC'
			: clear_button.textContent = 'C'

		
	}
	
	document.addEventListener('keydown', function(e) {
		let key = e.key;
		let button;

		// Verifica se a tecla pressionada é um dígito ou ponto decimal
		if ((key >= '0' && key <= '9') || key === '.' || key === ',') {
			// Trata ',' como '.'
			if (key === ',') key = '.';
			button = document.querySelector(`div[data-button-type="digit"][data-value="${key}"]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
		// Verifica se a tecla pressionada é um operador
		else if (key === '+' || key === '-' || key === '*' || key === '/') {
			button = document.querySelector(`div[data-button-type="operator"][data-value="${key}"]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
		// Verifica se a tecla pressionada é 'Enter' ou '=' para a operação de igual
		else if (key === 'Enter' || key === '=') {
			button = document.querySelector(`div[data-button-type="operator"][data-value="="]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
		// Verifica se a tecla pressionada é 'Backspace' ou 'c' para a operação de limpar
		else if (key === 'Backspace' || key.toLowerCase() === 'c') {
			button = document.querySelector(`div[data-button-type="operator"][data-value="clear"]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
		// Verifica se a tecla pressionada é '%' para a operação de porcentagem
		else if (key === '%') {
			button = document.querySelector(`div[data-button-type="operator"][data-value="%"]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
		// Verifica se a tecla pressionada é 'n' para a operação de mais/menos
		else if (key.toLowerCase() === 'n') {
			button = document.querySelector(`div[data-button-type="operator"][data-value="+/-"]`);
			if (button) {
				e.preventDefault();
				button.click();
			}
		}
	});

</script>