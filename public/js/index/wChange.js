function wchange(js, vr, qt){
	if(js == 'wdel'){
		var url = "backengine/excluir_publicacao.php?vr="+vr;
		var tit = 'A publicação será excluída permanentemente!';
	}else if(js == 'wjoin'){
		var url = "backengine/acessar_comunidade.php?vr="+vr;
		var tit = 'Essa ação poderá ser revista por um moderador.';
	}else if(js == 'wjoin_company'){
		var url = "backengine/acessar_empresa.php?vr="+vr;
		var tit = 'Essa ação poderá ser revista por um moderador.';
	}else if(js == 'wcdel'){
		var url = "backengine/excluir_comunidade.php?vr="+vr;
		var tit = 'A comunidade e todas as suas publicações serão permanentemente excluídas.';
	}else if(js == 'wedel'){
		var url = "backengine/excluir_empresa.php?vr="+vr;
		var tit = 'Você deverá entrar em contato com o suporte Workz! para habilitá-lo novamente.';
	}else if(js == 'scdel'){
		var url = "backengine/excluir_linksocial.php?vr="+vr;
		var tit = 'Este link será permanentemente excluído.';
	}else if(js == 'pidel'){
		var url = "backengine/ideia.php?vr="+vr;
		var tit = 'O Post-It® será excluído permanentemente.';
	}else if(js == 'task_action'){
		var url = "backengine/tasks/process.php?vr="+vr;
		var tit = 'A ação alterará o status da tarefa.';
	}else if(js == 'tskdt'){
		var url = "backengine/tasks/edit.php?vr="+vr;
		var tit = 'O período de revisão será alterado.';
	}else if(js == 'tskrv'){
		var url = "backengine/tasks/process.php?vr="+vr;
		var tit = 'Você não poderá excluir ou arquivar suas tarefas até a próxima Revisão Semanal.';
	}else if(js == 'follow'){
		var url = "backengine/seguir.php?vr="+vr;
		var tit = 'Isso alterará a sua relação de seguidos.';
	}else if(js == 'changePost'){
		var url = "backengine/editar_publicacao.php?vr="+vr;
		var tit = 'Você alterará o conteúdo desta publicação';
	}
	swal({
		title: "Tem certeza?",
		text: tit,
		icon: "warning",
		buttons: true,
		dangerMode: true,
	})
	.then((result) => {
		if (result){		
			if(window.XMLHttpRequest){
				req = new XMLHttpRequest();
			}else if(window.ActiveXObject){
				req = new ActiveXObject("Microsoft.XMLHTTP");
			}
			req.open("Get", url, true);
			req.onreadystatechange = function(){
				if(req.readyState == 4 && req.status == 200){								
					if(js == 'wdel'){	
						var resposta = req.responseText;
						setTimeout(location.reload(), 100000);
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
					}else if(js == 'wjoin' || js == 'wjoin_company'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'wcdel'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'wedel'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'scdel'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'pidel'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'task_action'){
						var resposta = req.responseText;									
						goTo('backengine/tasks/main.php', 'wz_tasks', '0', '');
						zera('');
						var txts = resposta;									
						swal(
							'',
							txts,
							'success'
						);
					}else if(js == 'tskdt'){
						var resposta = req.responseText;
						goTo('backengine/tasks/main.php', 'wz_tasks', '0', '');
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
					}else if(js == 'tskrv'){
						var resposta = req.responseText;
						goTo('backengine/tasks/main.php', 'wz_tasks', '0', '');
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
					}else if(js == 'follow'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						setTimeout(location.reload(), 100000);
					}else if(js == 'changePost'){
						var resposta = req.responseText;
						var txts = resposta;
						swal(
							'',
							txts,
							'success'
						);
						//setTimeout(location.reload(), 100000);
					}
					
				}
			}
			req.send(null);						
		}else{
			/*
			if(js == 'wdel'){
				swal("A publicação foi mantida.");
			}
			*/
		}
	});
}


function wAlert(rq, ds, qt, vr, tt, ss, cl){
	//tt - Mensagem exibida.
	//ss - Mensagem de sucesso.
	//cl - Mensagem de cancelamento.
	var url = rq;
	var tit = tt;
	swal({
		title: "Tem certeza?",
		text: tt,
		icon: "warning",
		buttons: true,
		dangerMode: true,
	})
	.then((result) => {
		if(result){
			if(window.XMLHttpRequest){
				req = new XMLHttpRequest();
			}
			else if(window.ActiveXObject){
				req = new ActiveXObject("Microsoft.XMLHTTP");
			}
			var arq = rq+"?qt="+qt;
			if(vr == ''){
				var url = arq;
			}else{
				var url = arq+"&vr="+vr;
			}
			document.getElementById(ds).classList.add('fade');
			req.open("Get", url, true);
			req.onreadystatechange = function(){
				if(req.readyState == 4 && req.status == 200){			
					var resposta = req.responseText;
					document.getElementById(ds).innerHTML = resposta;
					setTimeout(document.getElementById(ds).classList.remove('fade'), 250000);	
					swal(
						'',
						ss,
						'success'
					);						
				}
			}
			req.send(null);			
		}else{
			//goTo(rq, ds, '', '');
			swal(cl);
		}
	});
}	