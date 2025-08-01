function like(pl, uv){
	if(window.XMLHttpRequest){
		req = new XMLHttpRequest();
	}
	else if(window.ActiveXObject){
		req = new ActiveXObject("Microsoft.XMLHTTP");
	}
	var url = 'backengine/like.php?pl='+pl;
	req.open("Get", url, true);
	req.onreadystatechange = function(){
		if(req.readyState == 4 && req.status == 200){
			goTo('backengine/dynamic_bottom_post.php', 'dynamic_bottom_post_' + pl, '', pl);
		}
	}
	req.send(null);
}