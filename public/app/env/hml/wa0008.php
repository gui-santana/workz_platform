<head>
	<script src="https://alcdn.msauth.net/browser/2.14.2/js/msal-browser.min.js"></script>
    <script>
        const msalConfig = {
            auth: {
                clientId: "YOUR_CLIENT_ID",
                authority: "https://login.microsoftonline.com/YOUR_TENANT_ID",
                redirectUri: "http://localhost"
            }
        };

        const msalInstance = new msal.PublicClientApplication(msalConfig);

        async function signIn() {
            const loginRequest = {
                scopes: ["User.Read", "Calendars.Read"]
            };
            const loginResponse = await msalInstance.loginPopup(loginRequest);
            return loginResponse.accessToken;
        }

        async function getCalendarEvents(accessToken) {
            const response = await fetch("https://graph.microsoft.com/v1.0/me/events", {
                method: "GET",
                headers: {
                    "Authorization": `Bearer ${accessToken}`,
                    "Content-Type": "application/json"
                }
            });

            if (!response.ok) {
                console.error("Error fetching calendar events:", response.statusText);
                return;
            }

            const data = await response.json();
            console.log(data); // Process and display the events
        }

        async function initialize() {
            const accessToken = await signIn();
            await getCalendarEvents(accessToken);
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("signInButton").addEventListener("click", initialize);
        });
    </script>
	<style>
		#calendar th{
			width: 50px;
		}	
	</style>
</head>
<div class="container position-relative height-100 large-12 medium-12 small-12">
    <div class="card blur height-100 large-12 medium-12 small-12" style="background-image: url(https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US);
			background-position: center;
			background-repeat: no-repeat;
			background-size: cover;
			background-attachment: fixed;
			">
	</div>        
	<div class="position-absolute height-100 abs-t-0 abs-l-0 large-12 medium-12 small-12 background-white-transparent overflow-auto">
		<?
		if($_SESSION['wz'] == 1){		
		?>
		 <style>
			/* Estilos básicos */
			.container {
				display: flex;
				flex-wrap: wrap;
			}
			.goal-column {
				width: 25%;
				padding: 10px;
				box-sizing: border-box;
			}
			.goal-column h3 {
				margin: 0;
				padding: 10px;
				background: #ddd;
				text-align: center;
			}
			.goal-column ul {
				list-style-type: none;
				padding: 0;
			}
			.goal-column li {
				padding: 5px 10px;
				background: #f9f9f9;
				margin: 5px 0;
			}
			.nav-buttons {
				text-align: center;
				margin: 20px 0;
			}					
		</style>
		<div class="nav-buttons">
			<button id="prev-week">Previous Week</button>
			<button id="current-week">Current Week</button>
			<button id="next-week">Next Week</button>
		</div>
		<button id="signInButton">Sign in with Microsoft</button>
		<div class="container" id="goals-container">
			<!-- Conteúdo carregado dinamicamente -->
		</div>
		
		
		<!-- Formulário para criar metas -->
		<form id="goalForm">
			<h2>Create</h2>
			<label for="goalType">Type:</label>
			<select id="goalType" name="goalType" required>
				<option value="0">Long Term Goal</option>
				<option value="1">Annual Goal</option>
				<option value="2">Monthly Goal</option>
				<option value="3">Weekly Goal</option>
				<option value="4">Task</option>
				<option value="5">Event</option>
				<option value="6">Routine</option>				
			</select>
			<br>
			<label for="parentGoal">Parent Goal:</label>
			<select id="parentGoal" name="parentGoal" required>
				<!-- Metas pai serão carregadas aqui -->
			</select>
			<br>
			<label for="description">Name:</label>
			<input type="text" id="name" name="name" required>
			<br>
			<label for="description">Description:</label>
			<input type="text" id="description" name="description" required>
			<br>
			<label for="startDate">Date</label>
			<input type="datetime-local" id="startDate" name="startDate" required>
			<br>
			<label for="deadline">Deadline:</label>
			<input type="datetime-local" id="deadline" name="deadline" required>
			<br>		
			<div id="frequencyWrapper" style="display:none;">
				<label for="frequency">Frequency:</label>
				<select id="frequency" name="frequency">
					<option value="daily">Daily</option>
					<option value="weekly">Weekly</option>
					<option value="monthly">Monthly</option>
					<option value="annually">Annually</option>
					<option value="semiannually">Semiannually</option>
					<option value="every_x_days">Every X Days</option>
					<option value="specific_days">Specific Days (e.g., Tue and Thu)</option>
				</select>
				<br>
				<div id="frequencyDetails">
					<!-- Campos adicionais para detalhes de frequência -->
					<label for="everyXDays">Every X Days:</label>
					<input type="number" id="everyXDays" name="everyXDays" min="1">
					<br>
					<label for="specificDays">Specific Days:</label>
					<input type="text" id="specificDays" name="specificDays" placeholder="e.g., Tue, Thu">
				</div>
			</div>
			<button type="submit">Create Goal</button>
		</form>

		<script>
		$(document).ready(function() {
			let currentDate = new Date();

			function loadGoals(date) {
				$.ajax({
					url: 'core/backengine/wa0008/load_goals.php',
					type: 'POST',
					data: { date: date.toISOString().split('T')[0] },
					success: function(response) {
						$('#goals-container').html(response);
					}
				});
			}

			$('#prev-week').click(function() {
				currentDate.setDate(currentDate.getDate() - 7);
				loadGoals(currentDate);
			});

			$('#next-week').click(function() {
				currentDate.setDate(currentDate.getDate() + 7);
				loadGoals(currentDate);
			});

			$('#current-week').click(function() {
				currentDate = new Date();
				loadGoals(currentDate);
			});

			// Carregar a semana atual ao carregar a página
			loadGoals(currentDate);					
		
		
			//AJAX FORM
			
			 // Mostrar campos de frequência com base no tipo selecionado
			$('#goalType').change(function() {
				var goalType = $(this).val();
				if (goalType == 6){
					$('#frequencyWrapper').show();					
				} else {
					$('#frequencyWrapper').hide();
				}

				// Carregar metas pai dinamicamente com base no tipo selecionado
				$.ajax({
					url: 'core/backengine/wa0008/load_parent_goals.php',
					method: 'GET',
					data: { goalType: goalType },
					success: function(data) {
						$('#parentGoal').html(data);
					}
				});
			});							
		
		
			// Mostrar opções adicionais de frequência com base na seleção
			$('#frequency').change(function() {
				var frequency = $(this).val();
				if (frequency === 'every_x_days') {
					$('#everyXDays').show();
					$('#specificDays').hide();
				} else if (frequency === 'specific_days') {
					$('#specificDays').show();
					$('#everyXDays').hide();
				} else {
					$('#everyXDays, #specificDays').hide();
				}
			});
		
			// Função para criar uma nova meta
			$('#goalForm').submit(function(event) {
				event.preventDefault();
				var formData = $(this).serialize();
				$.ajax({
					url: 'core/backengine/wa0008/create_goal.php',
					method: 'POST',
					data: formData,
					success: function(response) {
						alert(response);
						loadGoals();
						$('#goalForm')[0].reset();
						$('#frequencyDetails').hide();
					}
				});
			});

			// Função para carregar o formulário de edição ao clicar em uma meta
			$(document).on('click', '.edit-goal', function() {
				var goalId = $(this).data('id');
				$.ajax({
					url: 'core/backengine/wa0008/load_goal_form.php',
					method: 'GET',
					data: {id: goalId},
					success: function(data) {
						$('#goals').html(data);
					}
				});
			});

			// Função para salvar edições ao clicar em "Salvar"
			$(document).on('submit', '#editGoalForm', function(event) {
				event.preventDefault();
				var formData = $(this).serialize();
				$.ajax({
					url: 'core/backengine/wa0008/edit_goal.php',
					method: 'POST',
					data: formData,
					success: function(response) {
						alert(response);
						loadGoals();
					}
				});
			});
		});
		
		
		   
		</script>

		<?
		}else{
		?>
		<div class="cm-pad-15 large-12 medium-12 small-12 fs-b font-weight-600 text-ellipsis uppercase z-index-1 background-gray-1" style="display: inline-block;">	
			<div class="float-left large-8 medium-8 small-6 text-ellipsis">
				<span class="fa-stack gray" style="vertical-align: middle;">
					<i class="fas fa-square fa-stack-2x"></i>
					<i class="fas fa-calendar fa-stack-1x fa-inverse"></i>
				</span>
				<a class="card-header uppercase" id="monthAndYear" style="vertical-align: middle;"></a>		
			</div>		
			<div class="float-right large-4 medium-4 small-6 text-right">				
								
				<span id="previous" onclick="previous()" class="fa-stack pointer w-color-bl-to-or pointer" style="vertical-align: middle;" title="Quadro de Tarefas">
					<i class="fas fa-square fa-stack-2x"></i>
					<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
				</span>
				<span id="next" onclick="next()" class="fa-stack pointer w-color-bl-to-or pointer" style="vertical-align: middle;" title="Quadro de Tarefas">
					<i class="fas fa-square fa-stack-2x"></i>
					<i class="fas fa-arrow-right fa-stack-1x fa-inverse"></i>
				</span>
				<div class="clear"></div>
			</div>
		</div>
		
		<div class="float-left cm-pad-10 large-4 medium-6 small-12">
			<table id="calendar" class="centered large-12 medium-12 small-12">
				<thead>
					<tr class="uppercase text-center">
						<th class="cm-pad-10">Dom</th>
						<th class="cm-pad-10">Seg</th>
						<th class="cm-pad-10">Ter</th>
						<th class="cm-pad-10">Qua</th>
						<th class="cm-pad-10">Qui</th>
						<th class="cm-pad-10">Sex</th>
						<th class="cm-pad-10">Sáb</th>
					</tr>
				</thead>
				<tbody id="calendar-body" class="">
				</tbody>
			</table>
		</div>
		<div class="float-left large-8 medium-6 small-12">
			<div class="large-12 medium-12 small-12 cm-pad-20" 	style="height: calc(100% - 53.03px)">				
				<div id="dateInfo" class="height-100 large-12 medium-12 small-12 overflow-auto" >
				</div>
				<div id="day_response" class="" ></div>
			</div>
		</div>
	   <div class="clear"></div>
	   
	   
	   <!--<button name="jump" onclick="jump()">Go</button>-->
		<script src="core/backengine/wa0008/scripts.js"></script>

		<!-- Optional JavaScript for bootstrap -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
				integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
				crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"
				integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ"
				crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"
				integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm"
				crossorigin="anonymous"></script>
		<script>	
			window.onload = function(){
				goTo('core/backengine/wa0008/dateInfo.php', 'dateInfo', '<? echo date('Y-m-d'); ?>', '');
			};
		</script>
	   <?
		}
	   ?>
   </div>
    
</div>


