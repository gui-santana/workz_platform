<style>        
	.memory-game {			          
		display: flex;
		flex-wrap: wrap;            
	}
	.memory-card {
		width: 100%;		         
		position: relative;		
		transform-style: preserve-3d;
		transition: transform 0.5s;
	}
	.memory-card.flip {
		transform: rotateY(180deg); /* Quando virar, mostrar a parte de trás */
	}
	.front, .back {
		position: absolute;
		width: 100%;
		height: 100%;
		backface-visibility: hidden;
	}
	.front {		       
		transform: rotateY(0deg); /* Parte frontal começa visível */
	}
	.back {
		background: #fff;
		
		justify-content: center;
		align-items: center;		
		transform: rotateY(180deg); /* Parte traseira começa de costas */		
		background-size: cover;
		background-position: center;
	}
	#win-message {
		display: none;
		font-size: 2em;
		margin-bottom: 20px;
	}
</style>
<div class="row">
	<div class="large-12 medium-12 small-12">
		<div id="win-message">Parabéns! Você ganhou! Reiniciando o jogo...</div>
		<div class="memory-game large-8 medium-12 small-12 centered cm-pad-10">
			<?
			$level = 6;
			for($i = 1; $i <= $level; $i++){
				?>
				<div class="w-square large-3 medium-3 small-4 cm-pad-10"><div class="memory-card w-square-content" data-framework="<?php echo $i; ?>">
					<div class="back w-shadow w-rounded-10"></div>
					<div class="front w-shadow w-rounded-10 text-center cm-pad-20 background-orange">
						<img src="https://workz.com.br/images/icons/workz_wh/90x37.png"></img>
					</div>
				</div></div>
				<div class="w-square large-3 medium-3 small-4 cm-pad-10"><div class="memory-card w-square-content" data-framework="<?php echo $i; ?>">
					<div class="back w-shadow w-rounded-10"></div>
					<div class="front w-shadow w-rounded-10 text-center cm-pad-20 background-orange">
						<img src="https://workz.com.br/images/icons/workz_wh/90x37.png"></img>
					</div>
				</div></div>	
				<?php
			}
			?>							
		</div>
	</div>
</div>

<script>
	const cards = document.querySelectorAll('.memory-card');
	let hasFlippedCard = false;
	let lockBoard = false;
	let firstCard, secondCard;
	let matchedPairs = 0;
	let imageUrls = [];
	
	async function fetchImages() {
		const response = await fetch('https://api.unsplash.com/photos/random?count=<?php echo $level; ?>&client_id=2Ga8Xz1jKi-kFXFhQ3mYEKn50FZz9KTnqSwF7UxOhLM');
		const data = await response.json();
		return data.map(image => image.urls.small);
	}

	 async function setCardImages() {
		imageUrls = []; // Limpar as URLs anteriores
		const urls = await fetchImages();
		urls.forEach((url, index) => {
			imageUrls.push({ url: url, pairId: String.fromCharCode(97 + index) }); // Cada par de cartas tem a mesma imagem e um identificador
			imageUrls.push({ url: url, pairId: String.fromCharCode(97 + index) });
		});
		shuffleArray(imageUrls);
		cards.forEach((card, index) => {
			card.dataset.framework = imageUrls[index].pairId; // Atribui o identificador correto
			card.querySelector('.back').style.backgroundImage = `url(${imageUrls[index].url})`;
		});
	}

	
	function flipCard() {
		if (lockBoard) return;
		if (this === firstCard) return;

		this.classList.add('flip');

		if (!hasFlippedCard) {
			hasFlippedCard = true;
			firstCard = this;
			return;
		}

		secondCard = this;
		checkForMatch();
	}

	function checkForMatch() {
		let isMatch = firstCard.dataset.framework === secondCard.dataset.framework;
		isMatch ? disableCards() : unflipCards();
	}

	function disableCards() {
		firstCard.removeEventListener('click', flipCard);
		secondCard.removeEventListener('click', flipCard);

		matchedPairs++;
		if (matchedPairs === cards.length / 2) {
			setTimeout(showWinMessage, 500);
		}

		resetBoard();
	}

	function unflipCards() {
		lockBoard = true;

		setTimeout(() => {
			firstCard.classList.remove('flip');
			secondCard.classList.remove('flip');

			resetBoard();
		}, 1500);
	}

	function resetBoard() {
		[hasFlippedCard, lockBoard] = [false, false];
		[firstCard, secondCard] = [null, null];
	}

	function showWinMessage() {
		const winMessage = document.getElementById('win-message');
		winMessage.style.display = 'block';
		setTimeout(() => {
			winMessage.style.display = 'none';
			resetGame();
		}, 3000);
	}

	async function resetGame() {
		matchedPairs = 0;
		await setCardImages();				            		
		shuffle();
		setTimeout(() => {
			cards.forEach(card => {
				card.classList.remove('flip');
				card.addEventListener('click', flipCard);
			});
		}, 5000);
		
	}

	function shuffle() {
		cards.forEach(card => {
			let randomPos = Math.floor(Math.random() * 16);
			card.parentElement.style.order = randomPos;
		});
	}
	
	function shuffleArray(array) {
		for (let i = array.length - 1; i > 0; i--) {
			const j = Math.floor(Math.random() * (i + 1));
			[array[i], array[j]] = [array[j], array[i]];
		}
	}

	
	 // Função para mostrar todas as cartas por um tempo e depois escondê-las
	window.addEventListener('load', async () => {
		await setCardImages();
		shuffle();
		setTimeout(() => {
			cards.forEach(card => card.classList.add('flip')); // Mostrar todas as cartas
			setTimeout(() => {
				cards.forEach(card => card.classList.remove('flip')); // Esconder todas as cartas após o tempo de memorizacao
			}, 5000); // Tempo inicial de 5 segundos para memorizar as cartas
		}, 500); // Pequeno atraso para garantir que a página esteja completamente carregada
	});
	
	cards.forEach(card => card.addEventListener('click', flipCard));
</script>
