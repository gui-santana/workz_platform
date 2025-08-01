function send_comment(rs, dsc, tt){
	if(rs == ''){
		alert('O campo está em branco.');
	}else{
		if(window.XMLHttpRequest){
			req = new XMLHttpRequest();
		}
		else if(window.ActiveXObject){
			req = new ActiveXObject("Microsoft.XMLHTTP");
		}							
		var url = "backengine/enviacomentario.php?rs="+rs+"&dsc="+dsc+"&tt="+tt;			
		req.open("Get", url, true);
		req.onreadystatechange = function() {
			if(req.readyState == 1) {
				document.getElementById(ds).innerHTML = 'Carregando...';
			}
			if(req.readyState == 4 && req.status == 200){
				if(tt != ''){
					goTo('backengine/vertopico.php', 'modal-comment-content', '1', dsc+'|'+tt);
				}else{
					goTo('backengine/vercomentarios.php', 'modal-comment-content', '1', dsc);
				}
			}
		}
		req.send(null);
	}					
}