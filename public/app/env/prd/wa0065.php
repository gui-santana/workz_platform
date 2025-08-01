<style>
	.number-buttons {
		display: flex;
		flex-wrap: wrap; /* Permite que os botões fiquem em várias linhas, caso necessário */
		justify-content: center; /* Centralizar os botões horizontalmente */
		gap: 10px; /* Espaço entre os botões */
		margin-bottom: 20px; /* Espaço inferior */
	}

	.number-buttons button {
		flex: 1 1 calc(33.33% - 20px); /* Largura dinâmica: 3 botões por linha, menos o espaço entre eles */
		max-width: 70px; /* Limite máximo de largura para cada botão */
		padding: 10px;
		font-size: 1.2em;
		text-align: center;
		border: none;
		cursor: pointer;
		border-radius: 10px;
		background-color: #f5f5f5; /* Cor de fundo para os botões */
		box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2); /* Sombra para um efeito 3D */
	}

	.number-buttons button:disabled {
		background-color: #ddd; /* Cor diferente para botões desativados */
		cursor: not-allowed;
	}

	canvas {				
		margin-bottom: 20px;
	}
	.number-buttons {		
		
	}
	.status {
		margin-bottom: 20px;
		font-size: 1.2em;
	}
	.level-selection {
		display: flex;
		flex-direction: column;
		gap: 10px;
		margin-bottom: 20px;
	}
</style>
<div class="row">     
	<div id="response"></div>
    <!-- Seletor de nível -->
    <div class="level-selection cm-pad-20 text-center" id="levelSelection">
		<?php			
			if(isset($_SESSION['wz'])){				
				include($_SERVER['DOCUMENT_ROOT'].'/protspot/userGetIdClient.php');
				?>
				<div class="large-12 medium-12 small-12 text-center background-white w-rounded-15 w-shadow-1 cm-pad-20 cm-mg-10-b">
					<h3>Melhor pontuação:</h3>
					<?php					
					$theBest = search('app', 'wa0065_regs', 'us,sc', 'sc = (SELECT MAX(sc) FROM wa0065_regs)')[0];
					$user = search('hnw', 'hus', 'ps,tt', "id = '{$theBest['us']}'")[0];
					?>
					<img class="w-circle cm-mg-10 cm-mg-0-h" style="height: 90px;" src="data:image/jpeg;base64,<? echo base64_encode(getByProtSpot($user['ps'], 'picture')); ?>" />					
					<p><?php echo $user['tt'].': '.$theBest['sc']; ?></p>
				</div>
				<?php
			}
		?>
        <h3>Escolha o nível de dificuldade:</h3>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('basic')">Básico</button>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('medium')">Médio</button>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('hard')">Difícil</button>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('advanced')">Experiente</button>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('master')">Master</button>
        <button class="fs-f w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-mg-0-h cm-pad-15" onclick="startGame('extreme')">Extremo</button>
    </div>

    <!-- Sudoku Canvas e Controles -->
    <div class="status large-12 medium-12 small-12 text-center cm-pad-20" id="statusContainer" style="display:none;">
        Tentativas: <span id="attemptsCounter">0/3</span> | Tempo: <span id="timer">0:00</span>
    </div>
	<!-- Botão para encerrar o jogo -->
	<div class="text-center" id="endGameContainer" style="display:none;">
		<button class="w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-pad-10" onclick="endGameAndReturnToStart()">Encerrar Jogo</button>
	</div>	
	<!-- Botão para pausar o jogo -->
	<div class="text-center" id="pauseGameContainer" style="display:none;">
		<button class="w-rounded-15 border-none pointer w-shadow w-all-bl-to-or cm-mg-5 cm-pad-10" onclick="togglePauseGame()" id="pauseButton">Pausar Jogo</button>
	</div>
	<!-- Tabuleiro -->
	<canvas id="sudokuCanvas" style="width: 97.5vw; height: auto; max-width: 450px; max-height: 450px; display: none;" class="centered cm-mg-20-b cm-pad-10 w-rounded-10 w-shadow-1 background-white"></canvas>	
	<!-- Botões de número de 1 a 9 -->
    <div class="number-buttons centered" id="numberButtonsContainer" style="display:none;"></div>	
	<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-10-t cm-pad-10-h fs-c text-center">									
		<img src="https://guilhermesantana.com.br/images/50x50.png" style="height: 35px; width: 35px" alt="Logo de Guilherme Santana"></img><br />											
		<a class="font-weight-500" target="_blank">Guilherme Santana © <?php echo date('Y'); ?></a>
	</div>
    <script>
		const canvas = document.getElementById('sudokuCanvas');
		const ctx = canvas.getContext('2d');
		const attemptsCounterElement = document.getElementById('attemptsCounter');
		const statusContainer = document.getElementById('statusContainer');
		const numberButtonsContainer = document.getElementById('numberButtonsContainer');
		let cellSize = 50; // Tamanho de cada célula (deve ser 'let' porque será atualizado durante o redimensionamento)

		// Outras variáveis...
		let selectedCell = null;
		let board = [];
		let initialBoard = [];
		let solutionBoard = [];
		let attempts = 0; // Contador de tentativas
		let timerInterval; // Intervalo para o contador de tempo
		let startTime; // Hora de início
		let difficultyLevel; // Armazena o nível de dificuldade selecionado
		let gameCompleted = false; // Indica se o jogo foi completado
		let isPaused = false; // Variável que controla se o jogo está pausado

		// Função para redimensionar o canvas de acordo com a tela
		function resizeCanvas() {
			// Definir a largura do canvas com base em 95% da largura da janela, limitando a um máximo de 450px
			const viewportWidth = window.innerWidth;
			const viewportHeight = window.innerHeight;
			const canvasSize = Math.min(viewportWidth * 0.95, viewportHeight * 0.8, 450);

			// Definir o tamanho do canvas
			canvas.width = canvasSize;
			canvas.height = canvasSize;

			// Atualizar o tamanho das células
			cellSize = canvas.width / 9;

			drawBoard(); // Redesenhar o tabuleiro com o novo tamanho
		}

		// Chamar o redimensionamento na inicialização e em mudanças de tamanho da janela
		window.addEventListener('resize', resizeCanvas);
		window.addEventListener('load', resizeCanvas);
		
        // Função para criar os botões de número de 1 a 9
        function createNumberButtons() {
			numberButtonsContainer.innerHTML = ''; // Limpar botões antigos
			for (let i = 1; i <= 9; i++) {
				const button = document.createElement('button');
				button.textContent = i;
				button.id = `button-${i}`;
				button.onclick = () => handleNumberButtonClick(i);
				button.classList.add('number-button');
				numberButtonsContainer.appendChild(button);
			}
		}
				
		// Função para encerrar o jogo e voltar para a tela inicial de seleção de nível
		function endGameAndReturnToStart() {
			clearInterval(timerInterval); // Parar o contador de tempo
			gameCompleted = true;

			// Limpar o canvas e esconder elementos do jogo
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			canvas.style.display = 'none';
			statusContainer.style.display = 'none';
			numberButtonsContainer.style.display = 'none';
			document.getElementById('endGameContainer').style.display = 'none';
			document.getElementById('pauseGameContainer').style.display = 'none';

			// Restaurar o estado original dos botões de nível
			const levelButtons = document.querySelectorAll('#levelSelection button');
			levelButtons.forEach(button => {
				button.classList.remove('w-shadow');
				button.classList.add('large-12', 'medium-12', 'small-12', 'fs-f', 'w-rounded-15', 'border-none', 'pointer', 'w-shadow', 'w-all-bl-to-or', 'cm-mg-10', 'cm-mg-0-h', 'cm-pad-15');
			});

			// Mostrar novamente a seleção de nível
			document.getElementById('levelSelection').style.display = 'block';
		}
		
		// Função para pausar ou retomar o jogo
		function togglePauseGame() {
			if (isPaused) {
				// Retomar o jogo
				isPaused = false;
				startTime = new Date() - pausedTime; // Ajustar o tempo para descontar o período de pausa
				pausedTime = 0;

				// Retomar o contador de tempo
				timerInterval = setInterval(updateTimer, 1000);

				// Reativar todos os controles do jogo
				canvas.addEventListener('click', handleCanvasClick);
				for (let i = 1; i <= 9; i++) {
					const button = document.getElementById(`button-${i}`);
					if (button) {
						button.disabled = false;
					}
				}

				// Atualizar texto do botão de pausa
				document.getElementById('pauseButton').textContent = 'Pausar Jogo';
			} else {
				// Pausar o jogo
				isPaused = true;

				// Parar o contador de tempo e salvar o momento em que pausou
				clearInterval(timerInterval);
				pausedTime = new Date() - startTime;

				// Desativar todos os controles do jogo
				canvas.removeEventListener('click', handleCanvasClick);
				for (let i = 1; i <= 9; i++) {
					const button = document.getElementById(`button-${i}`);
					if (button) {
						button.disabled = true;
					}
				}

				// Atualizar texto do botão de pausa
				document.getElementById('pauseButton').textContent = 'Retomar Jogo';
			}
		}

		
        // Função para começar o jogo com o nível selecionado (atualizada)
		function startGame(level) {
			difficultyLevel = level; // Armazenar o nível de dificuldade

			const difficulties = {
				basic: 0.4,      // 40% de remoção (nível básico)
				medium: 0.5,     // 50% de remoção
				hard: 0.6,       // 60% de remoção
				advanced: 0.7,   // 70% de remoção
				master: 0.75,    // 75% de remoção
				extreme: 0.8     // 80% de remoção
			};

			const difficulty = difficulties[level];
			initializeNewBoard(difficulty);

			// Esconder seleção de nível e mostrar canvas e controles do jogo
			document.getElementById('levelSelection').style.display = 'none';
			canvas.style.display = 'block';
			statusContainer.style.display = 'block';
			numberButtonsContainer.style.display = 'flex';
			document.getElementById('endGameContainer').style.display = 'block'; // Mostrar botão de encerrar jogo
			document.getElementById('pauseGameContainer').style.display = 'block'; // Mostrar botão de pausar jogo

			// Adiciona o evento de clique ao canvas para selecionar células
			canvas.addEventListener('click', handleCanvasClick);
		}


        // Inicializar um novo tabuleiro aleatório
        function initializeNewBoard(difficulty) {
            solutionBoard = generateFullBoard();
            initialBoard = generatePuzzle(solutionBoard, difficulty); // Definir dificuldade
            board = JSON.parse(JSON.stringify(initialBoard)); // Cria uma cópia do tabuleiro inicial
            attempts = 0; // Reinicia as tentativas ao começar um novo jogo
            startTime = new Date(); // Marca o tempo de início
            gameCompleted = false; // Define que o jogo não foi completado ainda

            createNumberButtons();  // Criar os botões primeiro
            drawBoard();            // Depois desenhar o tabuleiro

            // Iniciar o contador de tempo
            if (timerInterval) clearInterval(timerInterval);
            timerInterval = setInterval(updateTimer, 1000);

            // Atualizar contador de tentativas na tela
            updateAttemptsDisplay();
        }

		// Capturar o clique no canvas para selecionar células
		function handleCanvasClick(event) {
			const rect = canvas.getBoundingClientRect();

			// Ajustar as coordenadas do clique/toque para a escala do canvas
			let clientX = event.clientX || (event.touches ? event.touches[0].clientX : 0);
			let clientY = event.clientY || (event.touches ? event.touches[0].clientY : 0);

			const x = ((clientX - rect.left) / rect.width) * canvas.width;
			const y = ((clientY - rect.top) / rect.height) * canvas.height;

			const col = Math.floor(x / cellSize);
			const row = Math.floor(y / cellSize);

			// Verificar se as coordenadas estão dentro dos limites do tabuleiro
			if (row >= 0 && row < 9 && col >= 0 && col < 9) {
				// Permitir a seleção de qualquer célula, inclusive as que já têm valor
				selectedCell = { row, col };
				drawBoard();
			}
		}

        // Função para gerar um tabuleiro completo aleatório de Sudoku
        function generateFullBoard() {
            const board = Array.from({ length: 9 }, () => Array(9).fill(0));
            fillBoard(board);
            return board;
        }

        // Função para preencher o tabuleiro
        function fillBoard(board) {
            const numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9];

            // Função para tentar preencher o tabuleiro usando recursão e backtracking
            function fillCell(row, col) {
                if (col === 9) {
                    row++;
                    col = 0;
                    if (row === 9) {
                        return true; // Tabuleiro completo
                    }
                }

                const shuffledNumbers = numbers.sort(() => Math.random() - 0.5);
                for (const num of shuffledNumbers) {
                    if (isValidPlacement(board, row, col, num)) {
                        board[row][col] = num;
                        if (fillCell(row, col + 1)) {
                            return true;
                        }
                        board[row][col] = 0;
                    }
                }
                return false;
            }

            fillCell(0, 0);
        }

        // Função para verificar se uma colocação é válida
        function isValidPlacement(board, row, col, num) {
            for (let i = 0; i < 9; i++) {
                if (board[row][i] === num || board[i][col] === num) {
                    return false;
                }
                const boxRow = Math.floor(row / 3) * 3 + Math.floor(i / 3);
                const boxCol = Math.floor(col / 3) * 3 + (i % 3);
                if (board[boxRow][boxCol] === num) {
                    return false;
                }
            }
            return true;
        }

        // Função para remover números do tabuleiro de acordo com o nível de dificuldade
        function generatePuzzle(board, difficulty) {
            const puzzle = JSON.parse(JSON.stringify(board));
            for (let row = 0; row < 9; row++) {
                for (let col = 0; col < 9; col++) {
                    if (Math.random() < difficulty) {
                        puzzle[row][col] = 0;
                    }
                }
            }
            return puzzle;
        }

        // Atualizar o contador de tentativas
        function updateAttemptsDisplay() {
            attemptsCounterElement.textContent = `${attempts}/3`;
        }

        // Atualizar o contador de tempo
        function updateTimer() {
            if (gameCompleted) return; // Não atualiza o tempo se o jogo estiver completo

            const timerElement = document.getElementById('timer');
            const currentTime = new Date();
            const elapsedTime = Math.floor((currentTime - startTime) / 1000);
            const minutes = Math.floor(elapsedTime / 60);
            const seconds = elapsedTime % 60;
            timerElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        }

		// Função para desenhar o tabuleiro de Sudoku
		function drawBoard() {
			// Verificar se o tabuleiro está inicializado corretamente
			if (!board || !Array.isArray(board) || board.length === 0) {
				return; // Se o tabuleiro não estiver definido ou estiver vazio, não desenhar
			}

			ctx.clearRect(0, 0, canvas.width, canvas.height);

			// Preencher o fundo de linha e coluna da célula selecionada
			if (selectedCell) {
				ctx.fillStyle = '#F5F5F5'; // Cor de fundo para destacar linha e coluna
				// Destacar a linha
				ctx.fillRect(0, selectedCell.row * cellSize, canvas.width, cellSize);
				// Destacar a coluna
				ctx.fillRect(selectedCell.col * cellSize, 0, cellSize, canvas.height);

				// Destacar a célula selecionada com borda mais grossa na cor #f2571c
				ctx.strokeStyle = '#222'; // Cor da borda destacada
				ctx.lineWidth = 3;
				ctx.strokeRect(selectedCell.col * cellSize, selectedCell.row * cellSize, cellSize, cellSize);
			}

			// Desenhar as linhas do tabuleiro
			for (let i = 0; i <= 9; i++) {
				ctx.beginPath();
				ctx.lineWidth = (i % 3 === 0) ? 2 : 1;
				ctx.strokeStyle = '#CACACA'; // Cor das linhas da grade
				ctx.moveTo(i * cellSize, 0);
				ctx.lineTo(i * cellSize, canvas.height);
				ctx.moveTo(0, i * cellSize);
				ctx.lineTo(canvas.width, i * cellSize);
				ctx.stroke();
			}

			// Preencher os números do tabuleiro
			for (let row = 0; row < 9; row++) {
				for (let col = 0; col < 9; col++) {
					if (board[row][col] !== 0) {
						let isBold = false;

						// Verificar se há uma célula selecionada e se o número é igual ao da célula selecionada
						if (selectedCell && board[selectedCell.row][selectedCell.col] === board[row][col]) {
							isBold = true; // Todos os números iguais ao selecionado ficam em negrito
						}

						// Definir estilo e fonte para os números
						ctx.fillStyle = initialBoard[row][col] !== 0 ? '#222' :
							(board[row][col] === solutionBoard[row][col] ? 'green' : 'red');
						ctx.font = isBold ? `700 ${cellSize * 0.75}px Arial` : `100 ${cellSize * 0.75}px Arial`;
						ctx.textAlign = 'center';
						ctx.textBaseline = 'middle';
						
						// Ajuste da posição para garantir a centralização precisa
						const xPosition = col * cellSize + cellSize / 2;
						const yPosition = (row * cellSize + cellSize / 2) + 2;
						
						ctx.fillText(board[row][col], xPosition, yPosition);
					}
				}
			}

			// Atualizar os botões dos números
			updateNumberButtons();
			
			// Verificar se o jogo foi completado automaticamente
			if (isBoardComplete()) {
				endGame(); // Encerrar o jogo automaticamente quando estiver completo
			}
		}

        // Função para verificar se o tabuleiro está completo
        function isBoardComplete() {
            for (let row = 0; row < 9; row++) {
                for (let col = 0; col < 9; col++) {
                    if (board[row][col] === 0 || board[row][col] !== solutionBoard[row][col]) {
                        return false;
                    }
                }
            }
            return true;
        }
		
		// Função para encerrar o jogo
		function endGame() {
			clearInterval(timerInterval); // Parar o contador de tempo
			gameCompleted = true;

			// Calcular e exibir a pontuação final
			calculateAndShowScore();
		}

        // Lidar com o clique dos botões numéricos
        function handleNumberButtonClick(value) {
            if (selectedCell) {
                const row = selectedCell.row;
                const col = selectedCell.col;

                // Verifica se a célula pode ser editada
                if (initialBoard[row][col] === 0) {
                    board[selectedCell.row][selectedCell.col] = value;

                    // Verificar se a inserção foi correta
                    if (board[row][col] !== solutionBoard[row][col]) {
                        attempts++;
                        updateAttemptsDisplay(); // Atualiza a contagem de tentativas na tela
                        if (attempts >= 3) {
                            alert('Você errou 3 vezes. O jogo terminou.');
                            disableAllInputs();
                            return;
                        } else {
                            alert(`Número incorreto! Você tem mais ${3 - attempts} tentativas.`);
                        }
                    }

                    drawBoard();
                }
            }
        }

        // Desabilitar todas as entradas quando o jogo terminar
        function disableAllInputs() {
            // Desabilitar todos os botões numéricos
            for (let i = 1; i <= 9; i++) {
                const button = document.getElementById(`button-${i}`);
                if (button) {
                    button.disabled = true;
                }
            }

            // Remover o evento de clique do canvas
            canvas.removeEventListener('click', handleCanvasClick);

            // Parar o contador de tempo
            clearInterval(timerInterval);
        }

        // Atualizar o estado dos botões dos números
        function updateNumberButtons() {
            for (let i = 1; i <= 9; i++) {
                const button = document.getElementById(`button-${i}`);
                if (button) { // Garantir que o botão existe antes de tentar alterar
                    if (isNumberComplete(i)) {
                        button.disabled = true; // Desativar o botão se o número estiver completo
                    } else {
                        button.disabled = false; // Ativar o botão caso contrário
                    }
                }
            }
        }

        // Verificar se um número está completo no tabuleiro
        function isNumberComplete(value) {
            let count = 0;
            for (let row = 0; row < 9; row++) {
                for (let col = 0; col < 9; col++) {
                    if (board[row][col] === value) {
                        count++;
                    }
                }
            }
            return count === 9; // O número está completo se aparecer exatamente 9 vezes
        }

        // Função para calcular e exibir a pontuação final
        function calculateAndShowScore() {
            // Pontuação base por nível de dificuldade
            const baseScores = {
                basic: 1000,
                medium: 1500,
                hard: 2000,
                advanced: 3000,
                master: 4000,
                extreme: 5000
            };

            const baseScore = baseScores[difficultyLevel];

            // Tempo decorrido em segundos
            const currentTime = new Date();
            const elapsedTime = Math.floor((currentTime - startTime) / 1000);

            // Pontuação ajustada pelo tempo e número de erros
            let score = baseScore;
            score -= elapsedTime; // Cada segundo reduz 1 ponto
            score -= attempts * 100; // Cada erro reduz 100 pontos

            // Garantir que a pontuação não seja negativa
            score = Math.max(score, 0);

            // Mostrar pontuação ao usuário
            alert(`Parabéns! Você completou o jogo.\nSua pontuação: ${score} pontos.`);

            // Enviar pontuação ao banco de dados via AJAX
            submitScore(score, elapsedTime);
        }

        // Função para enviar a pontuação ao banco de dados via AJAX
        function submitScore(score, elapsedTime) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'core/backengine/wa0065/save_score.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            const date = new Date().toISOString().slice(0, 19).replace('T', ' ');
            const data = `lv=${encodeURIComponent(difficultyLevel)}&tm=${encodeURIComponent(elapsedTime)}&sc=${encodeURIComponent(score)}&dt=${encodeURIComponent(date)}`;
            
            xhr.send(data);

            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {					
					document.getElementById('response').innerHTML = xhr.responseText;
                    console.log('Pontuação salva com sucesso!');
                }
            };
        }
    </script>
</div>
