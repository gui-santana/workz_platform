<link href="https://workz.com.br/css/style_large.css" rel="Stylesheet" type="text/css" />
<link href="https://workz.com.br/css/style_medium.css" rel="Stylesheet" type="text/css" />
<link href="https://workz.com.br/css/style_small.css" rel="Stylesheet" type="text/css" />
<link href="https://workz.com.br/css//isometric.css" rel="Stylesheet" type="text/css" />
<div class="large-6 medium-8 small-10 position-relative centered" style="height: 30vw;">
	<div class="box box-border w-rounded-15 position-relative background-dark large-6 centered" style="top: 2.5vw; height: 30vw; width: 30vw; background: url('https://th.bing.com/th/id/R.00401c62a26c9668fc339e2ab0721e7b?rik=r8fnF5ccC8hCRQ&riu=http%3a%2f%2fwww.3dtexture.eu%2fasphalt%2fthumbnails%2fasphalt_16.JPG&ehk=aaOqhG2pjXGScOCWw60IyEOJLVq9dmI%2bfxp2mm%2fxY9k%3d&risl=&pid=ImgRaw&r=0');">
	<?php
	$companies = search('cmp', 'employees', 'em', "us = {$_SESSION['wz']}");		
	$quarteiroes = array_chunk($companies, 4);				
	foreach($quarteiroes as $i => $quarteirao){
		// De acordo com $i, posicionamos Q2, Q0, Q3, Q1
		switch ($i) {
			case 0: $row=-0.5; $col=1; break; // Q0
			case 1: $row=1; $col=1; break; // Q1
			case 2: $row=-0.5; $col=-0.5; break; // Q2
			case 3: $row=1; $col=-0.5; break; // Q3							
			default:
				// se houver quarteiroes extras
				$row=0; $col=0; break;
		}
		// Calcular offset
		$topOffset  = 8 + ($row * 9);
		$leftOffset = 8 + ($col * 9);
		?>
		<!-- QUARTEIRÃO -->
		<div class="box-border rounded-1 width-10 height-10 position-absolute" style='top: <?= $topOffset ?>vw; left: <?= $leftOffset ?>vw; background: url("https://media.gettyimages.com/photos/grass-texture-seamless-picture-id524820473?b=1&k=6&m=524820473&s=170x170&h=DiZt7Mr00zU7B7jUFCHP-d4IG05fERIgZ6qNZxlRsPA=");'>
			<div class="rounded-1 width-9-5 height-9-5 position-absolute abs-0-r abs-0-t border-solid border-white border-width-0-25">
			<?php
			foreach ($quarteirao as $i => $predio) {
			
			$predio = $predio['em'];					
					
			$companyInfo = search('cmp', 'companies', 'tt,im', "id = '{$predio}'");			
			$employees = search('cmp', 'employees', 'id', "em = '{$predio}'");

				// “mapeamento” manual do índice para row e col
				switch ($i) {
					case 0: // Colocar no top-right
						$row = 0; $col = 1;
						break;
					case 1: // bottom-right
						$row = 1; $col = 1;
						break;
					case 2: // top-left
						$row = 0; $col = 0;
						break;
					case 3: // bottom-left
						$row = 1; $col = 0;
						break;
					default:
						// Caso existam mais de 4 itens, você pode ignorar, ou tratar
						$row = 0; $col = 0;
						break;
				}

				// Calcular offset
				$topOffset  = -0.25 + ($row * 4);
				$leftOffset = -0.25 + ($col * 4);
				?>
				<style>
				.cube-content-<?= $predio ?>::before{
					background: url(data:image/jpeg;base64,<?= $companyInfo[0]['im'] ?>), linear-gradient(to bottom right, rgba(1, 1, 1, 0.1), rgba(1, 1, 1, 0.2)); background-size: cover; background-position: center; background-repeat: no-repeat;					
				}			
				</style>
				<div class="position-absolute width-4-5 height-4-5 pad-1" style='top: <?= $topOffset ?>vw; left: <?= $leftOffset ?>vw'>											
					<div class="cube cube-texture cube-<?= (count($employees) > 10) ? '10' : count($employees) ?>" style="">
						<div class="cube-content cube-content-<?= $predio ?>" style="background: url(data:image/jpeg;base64,<?= $companyInfo[0]['im'] ?>); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>						
					</div>												
				</div>
				<?php
			}
			?>
			</div>
			<!-- POSTE 1 -->
			<div class="position-absolute" style="top: 7vw; left: 6.25vw; transform: rotate(45deg) skewY(35deg);">
				<div class="lamppost-wrapper" style="width: 1vw; height: 1vw">
					<div class="lamppost">																			
						<div class="light-cap"></div>									
						<div class="street-light"></div>									
					</div>
				</div>
			</div>									
			<!-- POSTE 2 -->
			<div class="position-absolute" style="top: 1.75vw; left: 2vw; transform: scaleX(-1) rotate(-45deg) skewY(35deg); ">
				<div class="lamppost-wrapper" style="width: 1vw; height: 1vw">
					<div class="lamppost">																			
						<div class="light-cap"></div>									
						<div class="street-light"></div>									
					</div>
				</div>
			</div>
		</div>
		<?php
	}	
	?>
	</div>
</div>