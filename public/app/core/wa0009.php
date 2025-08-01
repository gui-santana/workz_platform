<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carreiras & Conquistas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        #game-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 20px;
        }

        #board {
            display: grid;
            grid-template-columns: repeat(11, 60px);
            grid-template-rows: repeat(11, 60px);
            gap: 0;
            margin: 20px auto;
            border: 2px solid #333;
            background-color: #fff;
            position: relative;
        }

        .cell {
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ccc;
            height: 60px;
            width: 60px;
            text-align: center;
            font-size: 12px;
        }

        .start {
            background-color: #8bc34a;
            font-weight: bold;
        }

        .company {
            background-color: #ffc107;
        }

        .government {
            background-color: #9c27b0;
            color: #fff;
        }

        .opportunity {
            background-color: #03a9f4;
        }

        .challenge {
            background-color: #f44336;
            color: #fff;
        }

        #controls {
            display: flex;
            justify-content: center;
            margin: 10px;
        }

        button {
            margin: 5px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }

        #log {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            width: 100%;
            max-width: 600px;
            height: 200px;
            overflow-y: auto;
            margin: 10px auto;
        }

        .player {
            height: 15px;
            width: 15px;
            border-radius: 50%;
            position: absolute;
        }

        .player1 {
            background-color: red;
        }

        .player2 {
            background-color: blue;
        }
    </style>
</head>
<body>
    <div id="game-container">
        <h1>Carreiras & Conquistas</h1>
        <div id="board">
            <!-- Creating a Monopoly-like board with cells around the edges -->
            <!-- Bottom Row -->
            <div class="cell start" style="grid-column: 1 / 2; grid-row: 11 / 12;">Início</div>
            <div class="cell company" style="grid-column: 2 / 3; grid-row: 11 / 12;">Empresa X</div>
            <div class="cell company" style="grid-column: 3 / 4; grid-row: 11 / 12;">Empresa Y</div>
            <div class="cell government" style="grid-column: 4 / 5; grid-row: 11 / 12;">Receita Federal</div>
            <div class="cell challenge" style="grid-column: 5 / 6; grid-row: 11 / 12;">Desafio</div>
            <div class="cell government" style="grid-column: 6 / 7; grid-row: 11 / 12;">Agente Regulatório</div>
            <div class="cell opportunity" style="grid-column: 7 / 8; grid-row: 11 / 12;">Oportunidade</div>
            <div class="cell challenge" style="grid-column: 8 / 9; grid-row: 11 / 12;">Desafio</div>
            <div class="cell government" style="grid-column: 9 / 10; grid-row: 11 / 12;">Governo</div>
            <div class="cell challenge" style="grid-column: 10 / 11; grid-row: 11 / 12;">Desafio</div>

            <!-- Left Column -->
            <div class="cell opportunity" style="grid-column: 1 / 2; grid-row: 10 / 11;">Oportunidade</div>
            <div class="cell company" style="grid-column: 1 / 2; grid-row: 9 / 10;">Empresa Z</div>
            <div class="cell government" style="grid-column: 1 / 2; grid-row: 8 / 9;">Fórum</div>
            <div class="cell challenge" style="grid-column: 1 / 2; grid-row: 7 / 8;">Desafio</div>
            <div class="cell company" style="grid-column: 1 / 2; grid-row: 6 / 7;">Empresa W</div>
            <div class="cell opportunity" style="grid-column: 1 / 2; grid-row: 5 / 6;">Oportunidade</div>

            <!-- Top Row -->
            <div class="cell government" style="grid-column: 2 / 3; grid-row: 1 / 2;">Tribunal</div>
            <div class="cell challenge" style="grid-column: 3 / 4; grid-row: 1 / 2;">Desafio</div>
            <div class="cell company" style="grid-column: 4 / 5; grid-row: 1 / 2;">Empresa Q</div>
            <div class="cell opportunity" style="grid-column: 5 / 6; grid-row: 1 / 2;">Oportunidade</div>
            <div class="cell government" style="grid-column: 6 / 7; grid-row: 1 / 2;">Agente Público</div>
            <div class="cell challenge" style="grid-column: 7 / 8; grid-row: 1 / 2;">Desafio</div>
            <div class="cell company" style="grid-column: 8 / 9; grid-row: 1 / 2;">Empresa P</div>
            <div class="cell opportunity" style="grid-column: 9 / 10; grid-row: 1 / 2;">Oportunidade</div>

            <!-- Right Column -->
            <div class="cell company" style="grid-column: 11 / 12; grid-row: 2 / 3;">Empresa O</div>
            <div class="cell challenge" style="grid-column: 11 / 12; grid-row: 3 / 4;">Desafio</div>
            <div class="cell government" style="grid-column: 11 / 12; grid-row: 4 / 5;">Receita Estadual</div>
            <div class="cell opportunity" style="grid-column: 11 / 12; grid-row: 5 / 6;">Oportunidade</div>
            <div class="cell challenge" style="grid-column: 11 / 12; grid-row: 6 / 7;">Desafio</div>
            <div class="cell government" style="grid-column: 11 / 12; grid-row: 7 / 8;">Controle Interno</div>
        </div>

        <div id="controls">
            <button onclick="rollDice()">Rolar Dado</button>
        </div>

        <div id="log">Log do Jogo:</div>

        <!-- Adding player pieces -->
        <div class="player player1" id="player1" style="left: 5px; top: 615px;"></div>
        <div class="player player2" id="player2" style="left: 25px; top: 615px;"></div>
    </div>

    <script>
        const players = [
            { id: "player1", position: 0, type: "empreendedor" },
            { id: "player2", position: 0, type: "carreirista" }
        ];

        const boardCells = document.querySelectorAll(".cell");
        const logDiv = document.getElementById("log");
        let currentPlayerIndex = 0;

        function rollDice() {
            const diceRoll = Math.floor(Math.random() * 6) + 1;
            const currentPlayer = players[currentPlayerIndex];

            log(`Jogador ${currentPlayerIndex + 1} rolou um ${diceRoll}.`);

            currentPlayer.position += diceRoll;
            if (currentPlayer.position >= boardCells.length) {
                currentPlayer.position = boardCells.length - 1;
                log(`Jogador ${currentPlayerIndex + 1} alcançou o final do tabuleiro!`);
            }

            const playerElement = document.getElementById(currentPlayer.id);
            const cell = boardCells[currentPlayer.position];
            playerElement.style.left = cell.getBoundingClientRect().left - boardCells[0].getBoundingClientRect().left + 5 + "px";
            playerElement.style.top = cell.getBoundingClientRect().top - boardCells[0].getBoundingClientRect().top + 5 + "px";

            handleCell(cell, currentPlayer);
            currentPlayerIndex = (currentPlayerIndex + 1) % players.length;
        }

        function handleCell(cell, player) {
            const cellType = cell.classList[1];

            if (cellType === "company") {
                handleCompanyCell(cell, player);
            } else if (cellType === "government") {
                handleGovernmentCell(cell, player);
            } else {
                log("Nada especial nesta célula.");
            }
        }

        function handleCompanyCell(cell, player) {
            const companyName = cell.textContent;

            if (player.type === "empreendedor") {
                log(`${player.id} pode comprar a ${companyName} por $200.`);
            } else if (player.type === "carreirista") {
                log(`${player.id} pode trabalhar na ${companyName} e receber $50.`);
            } else if (player.type === "concurseiro") {
                log(`${player.id} paga $20 para a ${companyName}.`);
            }
        }

        function handleGovernmentCell(cell, player) {
            const governmentName = cell.textContent;

            if (player.type === "empreendedor") {
                log(`${player.id} deve pagar impostos ao ${governmentName}.`);
            } else if (player.type === "carreirista") {
                log(`${player.id} cumpre obrigações no ${governmentName}.`);
            } else if (player.type === "concurseiro") {
                log(`${player.id} tenta um benefício no ${governmentName}.`);
            }
        }

        function log(message) {
            const newLogEntry = document.createElement("div");
            newLogEntry.textContent = message;
            logDiv.appendChild(newLogEntry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }
    </script>
</body>
</html>
