function wPush(){
	if(window.XMLHttpRequest){
		req = new XMLHttpRequest();
	}
	else if(window.ActiveXObject){
		req = new ActiveXObject("Microsoft.XMLHTTP");
	}
	var url = 'backengine/push.php';
	req.open("Get", url, true);
	req.onreadystatechange = function(){
		if(req.readyState == 4 && req.status == 200){
			var resposta = req.responseText;
			if(resposta != '0'){
				pushNotification(req.responseText);
			}
		}
	}
	req.send(null);
}
setInterval("wPush()", 10000);