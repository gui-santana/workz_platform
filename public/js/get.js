function get(url){
	if(window.XMLHttpRequest){
		req = new XMLHttpRequest();
	}
	else if(window.ActiveXObject){
		req = new ActiveXObject("Microsoft.XMLHTTP");
	}	
	req.open("Get", url, true);
	req.onreadystatechange = function(){
		if(req.readyState == 4 && req.status == 200){			
			var resposta = req.responseText;
			document.getElementById('callback').innerHTML = resposta;			
		}
	}
	req.send(null);
	setTimeout(() => {
		document.getElementById('callback').innerHTML = '';		
	}, 501);
	
}