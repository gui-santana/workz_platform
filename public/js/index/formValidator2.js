function formValidator2(el, rq, ds, tr) { 
    var divForm = document.getElementById(el);
    var alList = divForm.querySelectorAll('input, select, textarea, div');
    var i;
    var vr = {};
    var n = 0;	

    for (i = 0; i < alList.length; i++) {
        // Para campos obrigatórios
        if (alList[i].classList.contains('required')) {	
            if (alList[i].type === 'file' || alList[i].type === 'files') {
                var label = alList[i].parentNode.querySelectorAll('label')[0];
                if (alList[i].files.length === 0) {
                    n++;
                    label.classList.add("invalid");
                } else {
                    label.classList.remove("invalid");	
                    var b64i = alList[i].parentNode.querySelectorAll('img')[0];
                    vr[alList[i].name] = b64i.src;
                }
            } else if (alList[i].type === 'radio' || alList[i].type === 'checkbox') {
                // Apenas adiciona valores selecionados
                if (alList[i].checked) {
                    if (vr[alList[i].name]) {
                        if (!Array.isArray(vr[alList[i].name])) {
                            vr[alList[i].name] = [vr[alList[i].name]];
                        }
                        vr[alList[i].name].push(alList[i].value);
                    } else {
                        vr[alList[i].name] = alList[i].value;
                    }
                }
            } else {
                if (alList[i].value === '') {
                    n++;
                    alList[i].classList.add("invalid");
                } else {
                    alList[i].classList.remove("invalid");
                    if (vr[alList[i].name]) {
                        if (!Array.isArray(vr[alList[i].name])) {
                            vr[alList[i].name] = [vr[alList[i].name]];
                        }
                        vr[alList[i].name].push(alList[i].value);
                    } else {
                        vr[alList[i].name] = alList[i].value;
                    }
                }
            }
        } else {
            // Para campos não obrigatórios
            if (alList[i].type === 'radio' || alList[i].type === 'checkbox') {
                // Apenas adiciona valores selecionados
                if (alList[i].checked) {
                    if (vr[alList[i].name]) {
                        if (!Array.isArray(vr[alList[i].name])) {
                            vr[alList[i].name] = [vr[alList[i].name]];
                        }
                        vr[alList[i].name].push(alList[i].value);
                    } else {
                        vr[alList[i].name] = alList[i].value;
                    }
                }
            } else if (alList[i].value !== '') {
                if (vr[alList[i].name]) {
                    if (!Array.isArray(vr[alList[i].name])) {
                        vr[alList[i].name] = [vr[alList[i].name]];
                    }
                    vr[alList[i].name].push(alList[i].value);
                } else {
                    vr[alList[i].name] = alList[i].value;
                }
            }
        }
    }

    // Apenas chama a função de envio se todos os campos obrigatórios forem preenchidos
    if (n === 0) {
        goPost(rq, ds, vr, tr);
    }
}


