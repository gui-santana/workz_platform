function formValidator(){
	var x = document.getElementById('wForm1');
	var myNodelist = document.querySelectorAll(".required");
	
	var i;
	var n = 0;
	for (i = 0; i < myNodelist.length; i++) {
		if(myNodelist[i].type == 'file' || myNodelist[i].type == 'files'){			
			if(myNodelist[i].files.length == 0){
				n = (n + 1);
				myNodelist[i].className += " invalid";
			}else{
				myNodelist[i].classList.remove("invalid");
			}
		}else{
			if(myNodelist[i].value == ''){
				n = (n + 1);
				myNodelist[i].className += " invalid";
			}else{
				myNodelist[i].classList.remove("invalid");
			}
		}		
	}
	
	if(n > 0){

	}else{		
		x.submit();
	}
};	