<div id="postContent" class="<?if($mobile == 0){?>w-rounded-10<?}else{?>overflow-auto<?}?> background-white cm-pad-20 large-12 w-shadow" style="min-height: 68.79px">		
	<?
	include('apps/core/backengine/wa0006/mini_box.php');
	?>
</div>
<script>
function addImage(el){
	var mediaInput = document.createElement('input');
	mediaInput.type = 'file';
	mediaInput.className = 'display-none';		
	el.parentNode.appendChild(mediaInput);
	mediaInput.accept = 'image/*';
	mediaInput.click();
	mediaInput.onchange = function(){
		const [file] = mediaInput.files;
		if(file){
			toDataURL(URL.createObjectURL(file), function(dataUrl){
				var mediaPreview = document.createElement('img');
				mediaPreview.width = 200;
				mediaPreview.src = dataUrl;		
				el.appendChild(mediaPreview);
				goPost('apps/core/backengine/wa0006/mini_box.php', 'postContent', btoa(dataUrl));
			});
			mediaInput.remove();				
		}
	}	
}
	
function toDataURL(url, callback){
	var xhr = new XMLHttpRequest();
	xhr.onload = function(){
		var reader = new FileReader();
		reader.onloadend = function(){
			callback(reader.result);
		}
		reader.readAsDataURL(xhr.response);
	};
	xhr.open('GET', url);
	xhr.responseType = 'blob';
	xhr.send();
}
</script>