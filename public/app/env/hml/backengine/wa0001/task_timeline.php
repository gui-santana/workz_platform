<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL,'pt_BR.UTF8');
$now = date('Y-m-d H:i:s');	

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}

if(isset($_GET['wp'])){
    //Busca timeline via API
    $wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['wp']}' AND us = '{$_SESSION['wz']}'");
    $api_url = 'https://'.$wpConn[0]['ur'].'/wp-json/tarefaswp/v1/listar?colunas=historico&campo[]=id&valor[]='.$_GET['vr'];
    
    include('wp_consult_folder.php');
    $tmln = $tgtsk[0];
    
}else{
    // Busca a timeline da tarefa no banco
    $tmln = search('app', 'wa0001_wtk', 'tml', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'")[0] ?? null;    
}

if (!$tmln) {
    echo "<p>Timeline não encontrada.</p>";
    exit;
}

function corrigirImagensBase64NoHtml($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $imagens = $dom->getElementsByTagName('img');

    foreach ($imagens as $img) {
        $srcOriginal = $img->getAttribute('src');
        $src = trim($srcOriginal);

        // Se já tiver o prefixo "data:", pula
        if (strpos($src, 'data:') === 0) {
            continue;
        }

        // Se já começa com "image/jpeg;base64," ou similares, remove
        if (preg_match('/^image\/[a-z]+;base64,/', $src)) {
            $src = preg_replace('/^image\/[a-z]+;base64,/', '', $src);
        }

        // Detecta o tipo MIME básico pelo início do conteúdo base64
        $mime = 'jpeg'; // padrão
        if (strpos($src, '/iVBOR') === 0) {
            $mime = 'png';
        } elseif (strpos($src, '/9j/') === 0) {
            $mime = 'jpeg';
        } elseif (strpos($src, 'R0lGOD') === 0) {
            $mime = 'gif';
        }

        // Adiciona o prefixo final e atualiza o atributo
        $novoSrc = "data:image/{$mime};base64,{$src}";
        $img->setAttribute('src', $novoSrc);
    }

    // Extrai apenas o conteúdo limpo do <body>
    $body = $dom->getElementsByTagName('body')->item(0);
    $novoHtml = '';
    foreach ($body->childNodes as $child) {
        $novoHtml .= $dom->saveHTML($child);
    }

    return $novoHtml;
}

$tml_json = $tmln['tml'];
$arr = json_decode($tml_json, true); 
if (!is_array($arr)) {
    $arr = []; // Garante que não há erro se a timeline estiver vazia
}

// Ordena os registros pelo timestamp mais recente
if (!empty($arr)) {
    $dt = array_column($arr, 'timestamp');
    array_multisort($dt, SORT_DESC, $arr);
    
    foreach ($arr as $key => $tml_content) {
		
        $tml_time = ucfirst(strftime('%A, %e de %B de %Y, às %H:%M', strtotime($tml_content['timestamp'])));
        $tml_text = corrigirImagensBase64NoHtml(base64_decode($tml_content['descrição']));
		if(isset($tml_content['user'])){
			$user = search('hnw', 'hus', 'tt', "id = {$tml_content['user']}")[0]['tt'].' ';
		}else{
			$user = '';
		}
        ?>
        <div class="w-task-tl-container w-task-tl">
            <div class="w-rounded-20 background-white w-shadow-1 position-relative overflow-x-auto clear">
                <div class="large-12 medium-12 small-12 cm-pad-10 cm-pad-15-h border-b-input display-center-general-container">
                    <div class="fs-c float-left text-ellipsis" style="width: calc(100% - 30px)">
                        <p class="font-weight-600"><?= $user ?></p>
						<small><?= $tml_time ?></small>
                    </div>
                    <div class="fs-b float-right font-weight-600" style="width: 30px">
                        <?php if (isset($tml_content['user']) && $tml_content['user'] == $_SESSION['wz']): ?>
                            <span onclick="deleteTimelineEntry('<?= $tml_content['timestamp'] ?>')" class="text-center fa-stack pointer w-color-bl-to-or pointer float-right" style="vertical-align: middle;" title="Excluir comentário">
                                <i class="fas fa-circle fa-stack-2x"></i>
                                <i class="fas fa-trash fa-stack-1x fa-inverse"></i>
                            </span>                                                    
                        <?php endif; ?>
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="large-12 medium-12 small-12 cm-pad-15 break-word <?= strpos($tml_text, '(***!WORKZ!***)') !== false ? 'gray background-faded-green' : '' ?>" id="tml<?php echo $tml_content['timestamp']; ?>">
                    <?= str_replace('(***!WORKZ!***)', '', $tml_text); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<script>
(function(){
    'use strict';

    function sendToTimeline() {			
        let textbox = document.getElementById('textBox');
        if (textbox.innerHTML.trim() !== '') {
            let comment = textbox.innerHTML;						
            goPost('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', {
                action: 'timeline',
                task_id: '<?= $_GET['vr'] ?>',  
                comment: comment
            }, '');
        }										
    }
    window.sendToTimeline = sendToTimeline;

    function deleteTimelineEntry(timestamp){
		var question = 'Tem certeza que deseja excluir este comentário?';
		var successMsg = 'Comentário excluído com sucesso.';
		var failMsg = 'O Comentário não foi excluído';
		
		sAlert(function() {
			goPost('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', {
				action: 'timeline_delete',
				task_id: '<?= $_GET['vr'] ?>',
				timestamp: timestamp // Agora usamos o timestamp como identificador único
			}, '');
		}, question, successMsg, failMsg);
	}
    window.deleteTimelineEntry = deleteTimelineEntry;
		
	const galeria = document.getElementById('task_timeline');
    const imagens = galeria.querySelectorAll('img');

    imagens.forEach(img => {
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', () => abrirZoom(img.src));
    });

    function abrirZoom(src) {
		const overlay = document.createElement('div');
		overlay.classList.add('ease-all-2s', 'zoom-overlay');

		const imgZoom = document.createElement('img');
		imgZoom.src = src;
		imgZoom.classList.add('ease-all-2s', 'zoom-img');

		overlay.appendChild(imgZoom);
		document.body.appendChild(overlay);
		
		// Garante que o CSS já aplicou o DOM antes de ativar a transição
		setTimeout(() => {
			overlay.classList.add('active');
			imgZoom.classList.add('active');
			overlay.classList.remove('ease-all-2s');
			imgZoom.classList.remove('ease-all-2s');
		}, 10); // Pequeno delay para o browser aplicar o DOM antes do CSS trigger

		let scale = 1;
		let translateX = 0;
		let translateY = 0;
		let isDragging = false;
		let startX, startY;
		let dragMoved = false; // 👈 Flag para detectar movimento

		const updateTransform = () => {
			imgZoom.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
		};

		// Zoom com scroll
		overlay.addEventListener('wheel', (e) => {
			e.preventDefault();
			const delta = -e.deltaY * 0.001;
			scale = Math.min(Math.max(scale + delta, 0.3), 5);
			updateTransform();
		});

		// Pointer (mouse + touch unificado)
		overlay.addEventListener('pointerdown', (e) => {
			isDragging = true;
			dragMoved = false;
			startX = e.clientX - translateX;
			startY = e.clientY - translateY;
			overlay.setPointerCapture(e.pointerId);
			overlay.style.cursor = 'grabbing';
		});

		overlay.addEventListener('pointermove', (e) => {
		if (!isDragging) return;
			const dx = e.clientX - startX;
			const dy = e.clientY - startY;

			// Se mover mais de 3px, conta como drag
			if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
			  dragMoved = true;
			}

			translateX = dx;
			translateY = dy;
			updateTransform();
		});

		overlay.addEventListener('pointerup', (e) => {
			isDragging = false;
			overlay.releasePointerCapture(e.pointerId);
			overlay.style.cursor = 'grab';
		});	

		// Pinça no mobile
		let startDist = 0;
		let initialScale = 1;
		let pinchCenter = { x: 0, y: 0 };
		let initialTranslate = { x: 0, y: 0 };

		overlay.addEventListener('touchstart', (e) => {
		  if (e.touches.length === 2) {
			e.preventDefault();
			startDist = getDistance(e.touches[0], e.touches[1]);
			initialScale = scale;

			// Centro da pinça
			pinchCenter = {
			  x: (e.touches[0].clientX + e.touches[1].clientX) / 2,
			  y: (e.touches[0].clientY + e.touches[1].clientY) / 2
			};

			initialTranslate = { x: translateX, y: translateY };
		  }
		});

		overlay.addEventListener('touchmove', (e) => {
		  if (e.touches.length === 2) {
			e.preventDefault();
			const newDist = getDistance(e.touches[0], e.touches[1]);
			const newScale = Math.min(Math.max((newDist / startDist) * initialScale, 0.3), 5);

			// Calcula o deslocamento da imagem para manter o centro da pinça fixo
			const deltaScale = newScale - scale;
			const offsetX = (pinchCenter.x - window.innerWidth / 2) * deltaScale;
			const offsetY = (pinchCenter.y - window.innerHeight / 2) * deltaScale;

			scale = newScale;
			translateX = initialTranslate.x - offsetX;
			translateY = initialTranslate.y - offsetY;

			updateTransform();
		  }
		});

		function getDistance(t1, t2) {
			return Math.hypot(t2.pageX - t1.pageX, t2.pageY - t1.pageY);
		}
		window.getDistance = getDistance;

		// Quando quiser fechar
		const fecharZoom = () => {
			overlay.classList.remove('active');
			imgZoom.classList.remove('active');

			setTimeout(() => {
				overlay.remove(); // só remove após a transição acabar
			}, 300); // corresponde ao transition do CSS
		};

		// Fechar só se for clique real E fora da imagem
		overlay.addEventListener('click', (e) => {
			// Se foi um clique real (sem drag) e o alvo foi o overlay (não a imagem), fecha
			if (!dragMoved && e.target === overlay) {
				imgZoom.classList.add('ease-all-2s');
				overlay.classList.add('ease-all-2s');
				fecharZoom();
			}
		});
		
		// Prevenir clique na imagem de propagar
		imgZoom.addEventListener('click', (e) => {
			e.stopPropagation(); // 👈 Garante que o clique na imagem não chegue no overlay
		});

		// Também prevenir pointerup na imagem de causar efeitos indesejados
		imgZoom.addEventListener('pointerup', (e) => {
			e.stopPropagation();
		});

		// Evita comportamento de arrasto nativo da imagem
		imgZoom.addEventListener('dragstart', (e) => e.preventDefault());
    }
	window.abrirZoom = abrirZoom;
	
})();
</script>
