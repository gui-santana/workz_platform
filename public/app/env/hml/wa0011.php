  
  <link href="core/backengine/wa0011/style/main.css" rel="stylesheet" type="text/css"/>
  <div class="container_2048">
    <div class="heading">
      <h1 class="title">2048</h1>
      <div class="scores-container">
        <div class="score-container">0</div>
        <div class="best-container">0</div>
      </div>
    </div>

    <div class="above-game">      
      <a class="restart-button">Novo Jogo</a>
    </div>

    <div class="game-container">
      <div class="game-message">
        <p></p>
        <div class="lower">
	        <a class="keep-playing-button">Continue</a>
          <a class="retry-button">Tente novamente</a>
        </div>
      </div>

      <div class="grid-container">
        <div class="grid-row">
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
        </div>
        <div class="grid-row">
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
        </div>
        <div class="grid-row">
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
        </div>
        <div class="grid-row">
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
          <div class="grid-cell"></div>
        </div>
      </div>

      <div class="tile-container">

      </div>
    </div>              
  </div>

  <script src="core/backengine/wa0011/js/bind_polyfill.js"></script>
  <script src="core/backengine/wa0011/js/classlist_polyfill.js"></script>
  <script src="core/backengine/wa0011/js/animframe_polyfill.js"></script>
  <script src="core/backengine/wa0011/js/keyboard_input_manager.js"></script>
  <script src="core/backengine/wa0011/js/html_actuator.js"></script>
  <script src="core/backengine/wa0011/js/grid.js"></script>
  <script src="core/backengine/wa0011/js/tile.js"></script>
  <script src="core/backengine/wa0011/js/local_storage_manager.js"></script>
  <script src="core/backengine/wa0011/js/game_manager.js"></script>
  <script src="core/backengine/wa0011/js/application.js"></script>