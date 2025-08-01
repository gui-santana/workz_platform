<div class="large-12 medium-12 small-12 position-absolute height-100" style="z-index: 0;">
	<div style="height: calc(100% - 100.34px);" class="cm-pad-20-h large-12 medium-12 small-12 overflow-y-auto overflow-x-hidden position-relative">				
		
		<div class="row w-rounded-20 background-white w-shadow-1 cm-pad-25 position-relative cm-mg-50-t">
			<div style="height: 40px; width: 40px; top: -15px" class="text-center position-absolute abs-l-30 display-center-general-container fs-f display-block white"><div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white w-shadow-2 z-index-0" style="transform: rotate(20deg); background: <? echo $colours[0]; ?>;"></div><i class="fas fa-university pointer z-index-1 centered"></i></div>
		
			<div class="large-12 medium-12 small-12 cm-mg-20-b cm-mg-25-t">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label class="font-weight-500">Código IF</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">
					<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" id="field" type="text" placeholder="CODI12" required></input>
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label class="font-weight-500">Data inicial</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">					
					<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="date" id="dataInicio" min="<?php echo date('Y-m-d', strtotime('-8 days')); ?>" max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" name="dataInicio">
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 medium-12 small-12 cm-mg-20-b">
				<div class="large-3 medium-3 small-12 float-left cm-pad-20-r">
					<label class="font-weight-500">Data final</label>
				</div>
				<div class="large-9 medium-9 small-12 float-right">										
					<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="date" id="dataFim" min="<?php echo date('Y-m-d', strtotime('-8 days')); ?>" max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" name="dataFim">
				</div>
				<div class="clear"></div>
			</div>
			<div class="large-12 text-right">				
				<div class="large-9 medium-9 small-12 float-right">
					<div onclick="goTo('core/backengine/wa0005/consulta.php', 'consulta', 1, $(`#field`).val() + '&ini=' + $(`#dataInicio`).val() + '&fim=' + $(`#dataFim`).val());" id="pesquisar" class="w-all-or-to-bl display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">
						<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container float-left display-block white">
							<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
							<i class="fas fa-search pointer z-index-1 fs-c centered"></i>
						</div>
						<a class="font-weight-500 cm-pad-10-l">Consultar</a>
					</div>
				</div>
				<div class="clear cm-mg-20-b"></div>
				<small>Fonte: <a href="https://data.anbima.com.br/debentures" target="_blank">https://data.anbima.com.br/debentures</a></small>
			</div>
		</div>		
		<div id="consulta" class="large-12 row cm-pad-15">
		</div>
		<div class="row cm-pad-25">
			<h3>Passo a Passo para Utilização do Aplicativo Debdata</h3>
			<ol>
				<li>
					<p><strong>Insira o Código IF da Debênture</strong>:<br>No campo designado, digite o Código IF da debênture que deseja pesquisar. Este código é o identificador único utilizado para localizar as informações relacionadas à debênture no sistema.</p>
				</li>
				<li>
					<p><strong>Selecione a Data Inicial e a Data Final da Pesquisa</strong>:<br>Informe as datas no formato adequado para definir o período da pesquisa. A data inicial corresponde ao início do intervalo temporal desejado e a data final marca o término do período a ser pesquisado.</p>
				</li>				
				<li>
					<p><strong>Confirme a Pesquisa</strong>:<br>Após o preenchimento de todos os campos, confirme a operação para que o aplicativo processe a busca e retorne os resultados pertinentes à debênture informada.</p>
				</li>				
			</ol>			
		</div>		
	</div>
</div>