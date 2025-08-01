<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maze Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            height: 100vh;
            background-color: #f0f0f0;
        }

        #maze-container {
            display: grid;
            gap: 2px;
            background-color: #000;
            margin: 20px 0;
        }

        .cell {
            background-color: #fff;
        }

        .wall {
            background-color: #000;
        }

        .player {
            background-color: #007bff;
        }

        .goal {
            background-color: #28a745;
        }

        button, select {
            margin: 5px;
            padding: 10px 20px;
            font-size: 1em;
        }

        .info {
            margin-top: 10px;
            font-size: 1.2em;
        }
		.visited {
			background-color: gray; /* Cor para células visitadas */
			transition: background-color 0.3s; /* Transição suave */
		}
    </style>
</head>
<body>
    <h1>Maze Game</h1>
    <div class="info">Tempo: <span id="timer">0</span> segundos</div>
    <div id="maze-container"></div>
    <select id="difficulty">
        <option value="10">Fácil (10x10)</option>
        <option value="15">Médio (15x15)</option>
        <option value="20">Difícil (20x20)</option>
    </select>
    <button onclick="generateMaze()">Gerar Novo Labirinto</button>
    <button onclick="toggleTheme()">Alternar Tema</button>

    <script>
        const mazeContainer = document.getElementById('maze-container');
        const timerElement = document.getElementById('timer');
        const difficultySelect = document.getElementById('difficulty');
        let maze = [];
        let playerPosition = { x: 0, y: 0 };
        let goalPosition = { x: 0, y: 0 };
        let mazeSize = parseInt(difficultySelect.value);
        let timer;
        let timeElapsed = 0;

        function calculateCellSize() {
            const containerSize = Math.min(window.innerWidth, window.innerHeight) * 0.8;
            return Math.floor(containerSize / mazeSize);
        }

        function updateCellSize(cellSize) {
            mazeContainer.style.gridTemplateColumns = `repeat(${mazeSize}, ${cellSize}px)`;
            document.querySelectorAll('.cell').forEach(cell => {
                cell.style.width = `${cellSize}px`;
                cell.style.height = `${cellSize}px`;
            });
        }

function addFakePaths() {
    const maxFakePaths = Math.floor(mazeSize / 2); // Número máximo de caminhos falsos
    let fakePathsAdded = 0;

    while (fakePathsAdded < maxFakePaths) {
        // Escolher uma célula aleatória que já é parte do caminho
        const randomY = Math.floor(Math.random() * mazeSize);
        const randomX = Math.floor(Math.random() * mazeSize);

        if (maze[randomY][randomX] === 'path') {
            const directions = [
                { x: 0, y: -1 }, // Cima
                { x: 0, y: 1 },  // Baixo
                { x: -1, y: 0 }, // Esquerda
                { x: 1, y: 0 }   // Direita
            ];

            // Escolher uma direção aleatória
            const randomDir = directions[Math.floor(Math.random() * directions.length)];
            const newX = randomX + randomDir.x;
            const newY = randomY + randomDir.y;

            // Verificar se a nova célula está dentro dos limites e é uma parede
            if (
                newX >= 0 && newX < mazeSize &&
                newY >= 0 && newY < mazeSize &&
                maze[newY][newX] === 'wall' &&
                !createsLargeOpenArea(newX, newY)
            ) {
                maze[newY][newX] = 'path'; // Criar o novo caminho

                // Opcionalmente, prolongar o caminho para criar um beco sem saída
                const nextX = newX + randomDir.x;
                const nextY = newY + randomDir.y;

                if (
                    nextX >= 0 && nextX < mazeSize &&
                    nextY >= 0 && nextY < mazeSize &&
                    maze[nextY][nextX] === 'wall' &&
                    !createsLargeOpenArea(nextX, nextY)
                ) {
                    maze[nextY][nextX] = 'path';
                }

                fakePathsAdded++;
            }
        }
    }
}

// Função para verificar se um novo caminho cria áreas abertas grandes
function createsLargeOpenArea(x, y) {
    const surroundings = [
        { x: -1, y: -1 }, { x: 0, y: -1 }, { x: 1, y: -1 }, // Cima
        { x: -1, y: 0 },               { x: 1, y: 0 },      // Laterais
        { x: -1, y: 1 }, { x: 0, y: 1 }, { x: 1, y: 1 }     // Baixo
    ];

    let pathCount = 0;

    for (const dir of surroundings) {
        const nx = x + dir.x;
        const ny = y + dir.y;

        if (
            nx >= 0 && nx < mazeSize &&
            ny >= 0 && ny < mazeSize &&
            maze[ny][nx] === 'path'
        ) {
            pathCount++;
        }
    }

    // Se mais de 3 caminhos estão ao redor, isso pode formar uma área aberta
    return pathCount > 3;
}




        function generateMaze() {
			clearInterval(timer);
			timeElapsed = 0;
			timerElement.textContent = timeElapsed;

			mazeSize = parseInt(difficultySelect.value);
			maze = Array.from({ length: mazeSize }, () =>
				Array.from({ length: mazeSize }, () => 'wall')
			);

			const visited = Array.from({ length: mazeSize }, () =>
				Array(mazeSize).fill(false)
			);

			const stack = [];
			playerPosition = { x: 0, y: 0 };
			goalPosition = { x: mazeSize - 1, y: mazeSize - 1 };

			visited[0][0] = true;
			maze[0][0] = 'path';
			stack.push(playerPosition);

			while (stack.length > 0) {
				const current = stack[stack.length - 1];
				const neighbors = getUnvisitedNeighbors(current.x, current.y, visited);

				if (neighbors.length > 0) {
					const next = neighbors[Math.floor(Math.random() * neighbors.length)];
					visited[next.y][next.x] = true;
					removeWall(current, next);
					maze[next.y][next.x] = 'path';
					stack.push(next);
				} else {
					stack.pop();
				}
			}

			connectGoalToPath(); // Conecta o destino ao caminho principal
			addFakePaths();      // Adiciona caminhos falsos
			drawMaze();
			startTimer();
		}

        function removeWall(current, next) {
            const x = (current.x + next.x) / 2;
            const y = (current.y + next.y) / 2;
            maze[y][x] = 'path';
        }

		function connectGoalToPath() {
			// Ajusta o destino baseado no tamanho da grade
			goalPosition = {
				x: mazeSize % 2 === 0 ? mazeSize - 2 : mazeSize - 1,
				y: mazeSize % 2 === 0 ? mazeSize - 2 : mazeSize - 1
			};

			// Verifica se o destino está conectado a um caminho válido
			const directions = [
				{ x: 0, y: -1 }, // Cima
				{ x: -1, y: 0 }  // Esquerda
			];

			let connected = false;

			for (const dir of directions) {
				const nx = goalPosition.x + dir.x;
				const ny = goalPosition.y + dir.y;

				if (
					nx >= 0 && nx < mazeSize &&
					ny >= 0 && ny < mazeSize &&
					maze[ny][nx] === 'path'
				) {
					connected = true;
					break;
				}
			}

			// Conecta o destino ao caminho, se necessário
			if (!connected) {
				if (goalPosition.x > 0 && maze[goalPosition.y][goalPosition.x - 1] === 'path') {
					maze[goalPosition.y][goalPosition.x - 1] = 'path'; // Conecta à esquerda
				} else if (goalPosition.y > 0 && maze[goalPosition.y - 1][goalPosition.x] === 'path') {
					maze[goalPosition.y - 1][goalPosition.x] = 'path'; // Conecta acima
				}
			}

			// Define o destino como parte do caminho
			maze[goalPosition.y][goalPosition.x] = 'path';
		}





        function getUnvisitedNeighbors(x, y, visited) {
            const deltas = [
                { x: 0, y: -2 },
                { x: 0, y: 2 },
                { x: -2, y: 0 },
                { x: 2, y: 0 }
            ];

            return deltas
                .map(delta => ({
                    x: x + delta.x,
                    y: y + delta.y
                }))
                .filter(
                    neighbor =>
                        neighbor.x >= 0 &&
                        neighbor.x < mazeSize &&
                        neighbor.y >= 0 &&
                        neighbor.y < mazeSize &&
                        !visited[neighbor.y][neighbor.x]
                );
        }

        function drawMaze() {
			mazeContainer.innerHTML = '';
			const cellSize = calculateCellSize();
			mazeContainer.style.gridTemplateColumns = `repeat(${mazeSize}, ${cellSize}px)`;

			maze.forEach((row, y) => {
				row.forEach((cell, x) => {
					const cellElement = document.createElement('div');
					cellElement.className = 'cell ' + cell;

					if (x === playerPosition.x && y === playerPosition.y) {
						cellElement.classList.add('player');
					} else if (x === goalPosition.x && y === goalPosition.y) {
						cellElement.classList.add('goal');
					}

					mazeContainer.appendChild(cellElement);
				});
			});

			updateCellSize(cellSize);
		}

		function movePlayer(dx, dy) {
			const newX = playerPosition.x + dx;
			const newY = playerPosition.y + dy;

			// Verificar se a nova posição está dentro dos limites e é um caminho
			if (
				newX >= 0 && newX < mazeSize &&
				newY >= 0 && newY < mazeSize &&
				maze[newY][newX] === 'path'
			) {
				// Marcar a célula atual como visitada
				const currentCell = document.querySelector(
					`.cell[data-x="${playerPosition.x}"][data-y="${playerPosition.y}"]`
				);
				if (currentCell) {
					currentCell.classList.add('visited');
				}

				// Atualizar posição do jogador
				playerPosition.x = newX;
				playerPosition.y = newY;

				// Redesenhar o labirinto com o jogador na nova posição
				drawMaze();

				// Verificar se o jogador chegou ao destino
				if (playerPosition.x === goalPosition.x && playerPosition.y === goalPosition.y) {
					clearInterval(timer);
					alert(`Parabéns! Você completou o labirinto em ${timeElapsed} segundos.`);
					generateMaze();
				}
			}
		}




        function startTimer() {
            timer = setInterval(() => {
                timeElapsed++;
                timerElement.textContent = timeElapsed;
            }, 1000);
        }

		 document.addEventListener('keydown', (event) => {
			switch (event.key) {
				case 'ArrowUp':
					movePlayer(0, -1); // Move para cima
					break;
				case 'ArrowDown':
					movePlayer(0, 1); // Move para baixo
					break;
				case 'ArrowLeft':
					movePlayer(-1, 0); // Move para a esquerda
					break;
				case 'ArrowRight':
					movePlayer(1, 0); // Move para a direita
					break;
				default:
					break;
			}
		});

        generateMaze();
    </script>
</body>
</html>
