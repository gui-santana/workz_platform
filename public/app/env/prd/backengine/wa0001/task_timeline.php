<?php
// Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once('../../../sanitize.php');
require_once('../../common/getUserAccessibleEntities.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

session_start();
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL,'pt_BR.UTF8');
$now = date('Y-m-d H:i:s');	

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}

// Busca a timeline da tarefa no banco
$tmln = search('app', 'wa0001_wtk', 'tml', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'")[0] ?? null;

if (!$tmln) {
    echo "<p>Timeline não encontrada.</p>";
    exit;
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
        $tml_text = base64_decode($tml_content['descrição']);
        ?>
        <div class="w-task-tl-container w-task-tl-left">
            <div class="w-rounded-20 background-white w-shadow-1 position-relative overflow-x-auto clear">
                <div class="large-12 medium-12 small-12 cm-pad-15 border-b-input">
                    <div class="fs-c large-6 medium-6 small-6 float-left font-weight-600 text-ellipsis">
                        <?= $tml_time ?>
                    </div>
                    <div class="fs-b large-6 medium-6 small-6 float-right font-weight-600">
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
            goPost('core/backengine/wa0001/process.php', 'ckb_response', {
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
			goPost('core/backengine/wa0001/process.php', 'ckb_response', {
				action: 'timeline_delete',
				task_id: '<?= $_GET['vr'] ?>',
				timestamp: timestamp // Agora usamos o timestamp como identificador único
			}, '');
		}, question, successMsg, failMsg);
	}
    window.deleteTimelineEntry = deleteTimelineEntry;
})();
</script>
