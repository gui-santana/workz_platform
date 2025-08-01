<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');
include('../../config/app_config.php');
include('../../auth/token_storage.php');

if(!isset($_SESSION)){
	session_start();
	//FUNCTIONS
	include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
}

setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');	
mb_internal_encoding('UTF8'); 
mb_regex_encoding('UTF8');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Fortaleza');

// Função para calcular a diferença em dias
function calculateDays($startDate, $endDate) {
    $interval = $startDate->diff($endDate);    
	$days = $interval->days;
    // Retorna o valor negativo se a data de início for maior que a data de fim
    if ($startDate > $endDate) {
        $days = -$days;
    }
    return $days;
}


// Função para calcular a diferença em meses e semanas
function calculateMonthsAndWeeks($startDate, $endDate) {
    $interval = $startDate->diff($endDate);
    
    // Calculando meses completos
    $months = $interval->m + ($interval->y * 12);

    // Calculando dias restantes após subtrair meses completos
    $remainingDays = $interval->days - ($months * 30);

    // Calculando semanas restantes dentro do mês atual
    $weeks = floor($remainingDays / 7);
    
    return [$months, $weeks];
}

// Recebe a data do AJAX
if (isset($_POST['date'])) {
    $currentDate = new DateTime($_POST['date']);
} else {
    $currentDate = new DateTime(date('Y-m-d H:i:s'));
}

$currentWeekStart = (clone $currentDate)->modify('monday this week');
$currentWeekEnd = (clone $currentDate)->modify('sunday this week');
$currentMonthStart = (clone $currentDate)->modify('first day of this month');
$currentMonthEnd = (clone $currentDate)->modify('last day of this month');

// Obtenção das metas de longo prazo do banco de dados
$LongTermGoals = search('app', 'wa0008_goals', '', 
    "us = '".$_SESSION['wz']."' 
	AND tp = 0 
    AND dt <= '".$currentWeekEnd->format('Y-m-d')."' 
    AND '".$currentWeekStart->format('Y-m-d')."' <= tg"
);	
?>
<style>
	.height-3p{
		height: 300px;
	}
</style>
<div class="large-12 medium-12 small-12">
	<div class="large-3 medium-6 small-12 float-left cm-pad-10 height-3p">		
		<!-- METAS / SUBMETAS COM PERÍODOS MENORES OU IGUAIS A 4 SEMANAS -->
		<div class="large-6 medium-6 small-6 float-left">
			<h3>Weekly</h3>
		</div>
		<div class="large-6 medium-6 small-6 float-left text-right">
			<p><?php echo $currentWeekStart->format('d/m') . ' a ' . $currentWeekEnd->format('d/m'); ?></p>
		</div>
		<div class="clear"></div>
		<div class="cm-mg-20-t w-shadow-1 large-12 medium-12 small-12 background-white w-rounded-25 height-100 cm-pad-20 overflow-y-auto">				
		<?php
		foreach($LongTermGoals as $Goals) {
			$YearlyGoals = search('app', 'wa0008_goals', '', "pa = '".$Goals['id']."' AND tp = 1");
			foreach($YearlyGoals as $Yearly) {
				$MonthlyGoals = search('app', 'wa0008_goals', '', "pa = '".$Yearly['id']."' AND  tp = 2");
				foreach($MonthlyGoals as $Monthly) {
					$WeeklyGoals = search('app', 'wa0008_goals', '', "pa = '".$Monthly['id']."' AND  tp = 3 AND tg BETWEEN '".$currentWeekStart->format('Y-m-d')."' AND '".$currentWeekEnd->format('Y-m-d')."'");
					foreach($WeeklyGoals as $Weekly) {
						// Calculando a diferença em meses e semanas
						list($months, $weeks) = calculateMonthsAndWeeks($currentDate, new DateTime($Weekly['tg']));
						?>
						<div class="large-12 medium-12 small-12 w-rounded-10 w-bkg-tr-gray w-shadow-1 cm-pad-10 cm-mg-10-t"><?php echo $Weekly['ds'].' <small>('.$weeks.' weeks left)</small>'; ?></div>
						<?php
					}
				}
			}
		}
		?>	
		</div>
	</div>
	<div class="large-3 medium-6 small-12 float-left cm-pad-10 height-3p">
		<!-- METAS / SUBMETAS COM PERÍODOS MENORES DO QUE 12 MESES -->
		<div class="large-6 medium-6 small-6 float-left">
			<h3>Monthly</h3>
		</div>
		<div class="large-6 medium-6 small-6 float-left text-right">
			<p><?php echo $currentDate->format('F'); ?></p>
		</div>
		<div class="clear"></div>
		<div class="cm-mg-20-t w-shadow-1 large-12 medium-12 small-12 background-white w-rounded-25 height-100 cm-pad-20 overflow-y-auto">	
		<ul>
		<?php
		foreach($LongTermGoals as $Goals) {
			// Ajustar a consulta para usar a data fornecida
			$yearlyGoals = search('app', 'wa0008_goals', '', 
				"pa = '".$Goals['id']."' 
				AND tp = 1 
				AND DATE_ADD(tg, INTERVAL -1 YEAR) <= '".$currentWeekStart->format('Y-m-d H:i:s')."' 
				AND '".$currentWeekEnd->format('Y-m-d H:i:s')."' <= tg"
			);
			foreach($YearlyGoals as $Yearly) {				
				$MonthlyGoals = search('app', 'wa0008_goals', '', 
				"pa = '".$Yearly['id']."' 
				AND tp = 2 
				AND tg BETWEEN '".$currentMonthStart->format('Y-m-d H:i:s')."' 
				AND dt <= '".$currentWeekStart->format('Y-m-d H:i:s')."' 
				AND '".$currentWeekEnd->format('Y-m-d H:i:s')."' <= tg"				
				);				
				foreach($MonthlyGoals as $Monthly) {
					// Calculando a diferença em meses e semanas				
					list($months, $weeks) = calculateMonthsAndWeeks($currentDate, new DateTime($Monthly['tg']));
					?>
					<div class="large-12 medium-12 small-12 w-rounded-10 w-bkg-tr-gray w-shadow-1 cm-pad-10 cm-mg-10-t display-block"><?php echo $Monthly['nm'].' <small>('.$weeks.' weeks left)</small>'; ?></div>
					<?php
				}
			}
		}
		?>
		</ul>
		</div>
	</div>
	<div class="large-3 medium-6 small-12 float-left cm-pad-10 height-3p">
		<!-- METAS COM PERÍODOS MAIORES OU IGUAIS A 1 ANO -->
		<div class="large-6 medium-6 small-6 float-left">
			<h3>Yearly</h3>
		</div>
		<div class="large-6 medium-6 small-6 float-left text-right">
			<p><?php echo $currentWeekEnd->format('Y'); ?></p>
		</div>
		<div class="clear"></div>
		<div class="cm-mg-20-t w-shadow-1 floar-left large-12 medium-12 small-12 background-white w-rounded-25 height-100 cm-pad-20 overflow-y-auto">		
		<ul>
		<?php
		foreach($LongTermGoals as $Goals) {        				
			// Ajustar a consulta para usar a data fornecida - Alterar DATE_ADD(tg, INTERVAL -1 YEAR) para dt caso queira mostrar a meta com base na sua data de início
			$yearlyGoals = search('app', 'wa0008_goals', '', 
				"pa = '".$Goals['id']."' 
				AND tp = 1 
				AND DATE_ADD(tg, INTERVAL -1 YEAR) <= '".$currentWeekStart->format('Y-m-d H:i:s')."' 
				AND '".$currentWeekEnd->format('Y-m-d H:i:s')."' <= tg"
			);
			
			foreach($yearlyGoals as $Yearly) {
				$goalEndDate = new DateTime($Yearly['tg']);
				// Calculando a diferença em meses e semanas
				list($months, $weeks) = calculateMonthsAndWeeks($currentDate, $goalEndDate);
				?>
				<div class="large-12 medium-12 small-12 w-rounded-10 w-bkg-tr-gray w-shadow-1 cm-pad-10 cm-mg-10-t"><?php echo $Yearly['nm'].' <small>('.$months.' months and '.$weeks.' weeks left)</small>'; ?></div>
				<?php
			}
		}
		?>
		</ul>
		</div>
	</div>
	<!-- METAS COM PERÍODOS MAIORES OU IGUAIS A 5 ANOS -->
	<div class="large-3 medium-6 small-12 float-left cm-pad-10 height-3p">
		<h3>Long Term</h3>
		<div class="cm-mg-20-t w-shadow-1 large-12 medium-12 small-12 background-white w-rounded-25 height-100 cm-pad-20 overflow-y-auto">				
		<?php
		foreach($LongTermGoals as $Goals) {
			// Data limite da meta
			$goalDeadline = new DateTime($Goals['tg']);
			$interval = $currentDate->diff($goalDeadline);
			?>
			<div class="large-12 medium-12 small-12 w-rounded-10 w-bkg-tr-gray w-shadow-1 cm-pad-10 cm-mg-10-t"><?php echo $Goals['nm'].' <small>('.$interval->y.' years and '.$interval->m.' months left)</small>'; ?></div>
			<?php
		}
		?>
		</div>
	</div>
	<div class="clear"></div>
</div>
<?
$weekDay = $currentWeekStart;
?>
<div class="large-12 medium-12 small-12 cm-pad-10">
	<div class="large-12 medium-12 small-12 background-white w-rounded-25 float-left cm-pad-10">
		<table class="large-12 medium-12 small-12">
			<thead>
				<tr>
					<th rowspan="2" class="actions">Actions</th>
					<th colspan="7">Week</th>
				</tr>
				<tr>					
					<th>Mon<br><span id="monDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Tue<br><span id="tueDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Wed<br><span id="wedDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Thu<br><span id="thuDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Fri<br><span id="friDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Sat<br><span id="satDate"><? echo $weekDay->format('d/m/Y'); $weekDay->modify('+1 day'); ?></span></th>
					<th>Sun<br><span id="sunDate"><? echo $weekDay->format('d/m/Y'); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<tr class="days">
					<td>Tasks</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr class="days">
					<td>Events</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr class="days">
					<td>Routines</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr class="days">
					<td>Habits</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr class="days">
					<td>Daily Free Notes</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			</tbody>
		</table>	
	</div>
</div>	