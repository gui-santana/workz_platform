function goPost(rq, ds, vr, tr) {
    if (typeof vr === 'object') {
        vr = JSON.stringify(vr);
    }
	
	// Certificar que os dados são corretamente codificados
    vr = encodeURIComponent(vr);

    let xhr = new XMLHttpRequest();
    xhr.open("POST", rq);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    if (ds !== "") {
        const container = document.getElementById(ds);

        if (!container) {
            console.error(`Elemento de destino (#${ds}) não encontrado.`);
            return;
        }

        container.classList.add('fade');

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    // Captura a resposta do servidor
                    var resposta = xhr.responseText;

                    // Criação de um contêiner temporário
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = resposta;

                    // Extrai scripts da resposta AJAX
                    const scripts = tempDiv.getElementsByTagName('script');
                    const scriptContents = [];

                    // Remove scripts do HTML antes de injetar
                    while (scripts.length) {
                        scriptContents.push(scripts[0].innerText || scripts[0].textContent);
                        scripts[0].parentNode.removeChild(scripts[0]);
                    }

                    // Injeta o conteúdo HTML (sem scripts)
                    if (tr === 1) {
                        container.innerHTML += tempDiv.innerHTML;
                    } else {
                        container.innerHTML = tempDiv.innerHTML;
                    }

                    // Reexecuta scripts inline capturados
                    reexecuteScripts(scriptContents);

                    // Remove efeito de loading
                    setTimeout(() => {
                        container.classList.remove('fade');
                    }, 250);
                } else {
                    console.error(`Erro na requisição AJAX: ${xhr.status} - ${xhr.statusText}`);
                }
            }
        };
    }

    xhr.send('vr=' + vr);
}

/**
 * Reexecuta scripts inline capturados da resposta AJAX.
 * 
 * @param {string[]} scriptContents - Array com o conteúdo dos scripts capturados.
 */
function reexecuteScripts(scriptContents) {
    scriptContents.forEach(scriptContent => {
        if (scriptContent) {
            try {
                // Cria um novo elemento <script> e executa
                const newScript = document.createElement('script');
                newScript.text = scriptContent;
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);  // Remove após execução

                console.log('Script inline executado com sucesso.');
            } catch (e) {
                console.error('Erro ao executar script inline:', e, scriptContent);
            }
        }
    });
}
