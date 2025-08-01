<?
// Sanitização de subdomínios
include_once($_SERVER['DOCUMENT_ROOT'] . '/sanitize.php');
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
header('Content-type: text/html; charset=utf-8');
setlocale( LC_ALL, 'pt_BR.utf-8', 'pt_BR', 'Portuguese_Brazil');
date_default_timezone_set('America/Sao_Paulo');

$office_id = $_GET['vr'];

$pn = $_GET['pg'];

$kb = ($pn * 20) - 1;
$ka = ($kb - 19);

$scds = array_column(search('cmp', 'companies_groups', 'emC', "emP = '{$office_id}'"),'emC');

if(count($scds) > 0){
	
	$scid = array();
	$ctid = array();
	$lgtp = array();
	$lgds = array();
	$lgfl = array();
	$dtch = array();
	$vlch = array();
	$lgus = array();
	$lgst = array();

	foreach($scds as $company){
		$regs = search('app', 'wa0002_regs_alterado', '', "scid = {$company}");
		foreach($regs as $rg){
			$scid[] = $rg['scid'];
			$ctid[] = $rg['ctid'];
			$lgtp[] = $rg['lgtp'];
			$lgds[] = $rg['lgds'];
			$lgfl[] = $rg['lgfl'];
			$dtch[] = $rg['dtch'];
			$vlch[] = $rg['vlch'];
			$lgus[] = $rg['lgus'];
			$lgst[] = $rg['lgst'];
		}
	}	
	
	$np = ceil(sizeof($scid) / 20); //Número de paginas
	
	?>
	<div class="card position-relative">
		<div class="card-body">
			<div class="w-page-header">							
				<h4 class="fs-c uppercase cm-pad-20-b cm-pad-5-t">Registros</h4>			
				<div class="position-absolute abs-r-0 abs-t-0 w-nav">					
					<div title="Adicionar" class="cm-mg-5-r w-nav-button w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-h font-weight-600 ubuntu float-left">
						<i class="fas fa-plus"></i>
					</div>
				</div>
				<div style="clear: both"></div>
			</div>
			<?
			if(count($scid) > 0){
			?>
			<div class="table-responsive background-gray w-rounded-10 cm-mg-20-t cm-mg-15-b cm-pad-20">
			<table id="myTable" class="table large-12 medium-12 small-12">
				<thead>
					<tr>
						<th class="cm-pad-5-b large-2 medium-2 small-4">Sociedade</th>
						<th class="cm-pad-5-b large-2 medium-2 hide-for-small-only">Período</th>
						<th class="cm-pad-5-b large-2 medium-2 small-4">Descrição</th>
						<th class="cm-pad-5-b large-2 medium-2 small-4">Status</th>
					</tr>
				</thead>
				<tbody class="text-center fs-c">
				<?
				for($i=$ka;$i<=$kb && $i >= 0;$i++){
					if(array_key_exists($i, $scid)){
						?>
						<tr>
							<?
							$scrs = search('cmp', 'companies', 'tt', "id = {$scid[$i]}")[0];							
							?>
							<td class="large-2 medium-2 small-4 cm-pad-5 text-ellipsis"><? echo $scrs['tt'];?></td>
							<td class="large-2 medium-2 hide-for-small-only cm-pad-5 text-ellipsis"><? echo date('d/m/Y', strtotime($dtch[$i])); ?></td>
							<td class="large-2 medium-2 small-4 cm-pad-5 text-ellipsis" title="<? echo $lgds[$i]; ?>"><? echo $lgds[$i]; ?></td>
							<td class="large-2 medium-2 small-4 cm-pad-5 text-ellipsis"><? if($lgst[$i] == 0){?>Ativo<?}elseif($lgst[$i] == 1){?>Inativo<?} ?></td>
						</tr>
						<?
					}
				}
				?>
				</tbody>
			</table>
			</div>
				<div class="btn-group w-btn-group" role="group" aria-label="Basic example">
				<?
				if($pn <= 5){
					$w = 1;
					$z = 9;
				}else{
					$w = ($pn - 4);
					$z = ($pn + 5);
					if($z > $np){
						$z = $np;
					}
				}
				?>
				<button style="height: 25px; width: 25px;" <?if($pn == 1){ echo 'disabled'; }?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wtebc.php', 'wteba', '&pg=1', '<? echo $_GET['vr']; ?>');"><i class="fas fa-chevron-left"></i></button>
				<?
				for($a=$w;$a<=$z && $a > 0;$a++){
				if($pn == $a){
				?>		
				<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" disabled><? echo $a; ?></button>
				<?
				}else{
				?>
				<button style="height: 25px; width: 25px;" class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wtebc.php', 'wteba', '&pg=<? echo $a; ?>', '<? echo $_GET['vr']; ?>');"><? echo $a; ?></button>			
				<?
				}
				}
				?>
				<button style="height: 25px; width: 25px;" <? if($pn == $np){ echo 'disabled'; }?> class="w-rounded-5 w-bkg-tr-gray border-like-input border-none pointer cm-pad-5-t cm-pad-5-b font-weight-600 ubuntu fs-b" onclick="goTo('core/backengine/wa0002/wtebc.php', 'wteba', '&pg=<? echo $np; ?>', '<? echo $_GET['vr']; ?>');"><i class="fas fa-chevron-right"></i></button>
			</div>	
			<?
			}else{
			?>
			<div class="cm-mg-10-t cm-mg-0-h cm-pad-10 w-rounded-5 background-gray"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>Esta conta ainda não possui registros.</div>
			<?
			}
			?>
		</div>
		
	</div>
	<?
	
}else{
	echo 'Para ver ou incluir registros, a conta precisa ter o cadastro de uma ou mais sociedades.';
}
?>