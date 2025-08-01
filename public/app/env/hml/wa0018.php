<link href="core/backengine/wa0018/css/reset.css" rel="stylesheet">
<link href="core/backengine/wa0018/css/main.css" rel="stylesheet">
<div id="gamecontainer">
	<div id="gamescreen">
		<div id="sky" class="animated">
			<div id="flyarea">
				<div id="ceiling" class="animated"></div>
				<!-- This is the flying and pipe area container -->
				<div id="player" class="bird animated"></div>

				<div id="bigscore"></div>

				<div id="splash"></div>

				<div id="scoreboard">
					<div id="medal"></div>
					<div id="currentscore"></div>
					<div id="highscore"></div>
					<div id="replay"><img src="core/backengine/wa0018/assets/replay.png" alt="replay"></div>
				</div>

				<!-- Pipes go here! -->
			</div>
		</div>
		<div id="land" class="animated"><div id="debug"></div></div>
	</div>
</div>
<div class="boundingbox" id="playerbox"></div>
<div class="boundingbox" id="pipebox"></div>
<script src="core/backengine/wa0018/js/jquery.min.js"></script>
<script src="core/backengine/wa0018/js/jquery.transit.min.js"></script>
<script src="core/backengine/wa0018/js/buzz.min.js"></script>
<script src="core/backengine/wa0018/js/main.js"></script>
