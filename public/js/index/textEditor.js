//EMOJI AND GIF ADDED INTO TEXT EDITOR
function tlopn(a){
	//console.log(a.parentNode.childNodes);
	if(a.id == 'wEmoji'){
		a.parentNode.childNodes[7].classList.toggle("show");
	}else if(a.id == 'wGif'){
		a.parentNode.childNodes[10].classList.toggle("show");
		document.getElementById('searchGIF').focus();
		grab_data('');
	}else if(a.id == 'wHeader'){
		a.parentNode.childNodes[3].classList.toggle("show");
	}else if(a.id = 'wPblOptions'){
		a.parentNode.childNodes[7].classList.toggle("show");
	}
}
/*
// Close the dropdown menu if the user clicks outside of it
window.onclick = function(event){									
	if(!$(event.target).is('.show')){					
		if(!event.target.parentNode.classList.contains('tlopn')){						
			var dropdowns = $('.show');														
			var p = dropdowns.map(function () {
				return this.id
			}).get();												
			$.each(p, function(key, value){												
				document.getElementById(value).classList.remove('show');							
			});
		}
		
	}								
}
*/
function linkify(text){
	var urlRegex =/(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
	return text.replace(urlRegex, function(url){
		return '<a href="' + url + '">' + url + '</a>';
	});
}		