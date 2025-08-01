function goTo(rq, ds, qt, vr) {
    let req;

    if (window.XMLHttpRequest) {
        req = new XMLHttpRequest();
    } else if (window.ActiveXObject) {
        req = new ActiveXObject("Microsoft.XMLHTTP");
    }

    let url = rq + '?qt=' + qt + (vr !== '' ? '&vr=' + vr : '');
    const container = document.getElementById(ds);

    if (!container) {
        console.error(`Elemento de destino (#${ds}) não encontrado.`);
        return;
    }

    container.classList.add('fade');

    req.open("GET", url, true);
    req.onreadystatechange = function () {
        if (req.readyState === 4) {
            if (req.status === 200) {
                //console.log('Resposta AJAX:', req.responseText);

                // Criação de um contêiner temporário
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = req.responseText;

                // Extrai scripts da resposta AJAX
                const scripts = tempDiv.getElementsByTagName('script');
                const scriptContents = [];

                // Remove scripts do HTML antes de injetar
                while (scripts.length) {
                    scriptContents.push(scripts[0].innerText || scripts[0].textContent);
                    scripts[0].parentNode.removeChild(scripts[0]);
                }

                // Injeta o conteúdo HTML (sem scripts)
                container.innerHTML = tempDiv.innerHTML;

                // Reexecuta scripts inline capturados
                reexecuteScripts(scriptContents);

                // Remove efeito de loading
                setTimeout(() => {
                    container.classList.remove('fade');
                }, 250);
            } else {
                console.error(`Erro na requisição AJAX: ${req.status} - ${req.statusText}`);
            }
        }
    };

    req.onerror = function () {
        console.error('Erro de conexão AJAX.');
    };

    req.timeout = 5000;
    req.ontimeout = function () {
        console.error('Requisição AJAX excedeu o tempo limite.');
    };

    try {
        req.send(null);
    } catch (e) {
        console.error('Erro ao enviar requisição AJAX:', e);
    }
}



// Função para reexecutar scripts inline e externos
function reexecuteScripts(scriptContents) {
    scriptContents.forEach(scriptContent => {
        if (scriptContent) {
            try {
                // Cria um novo elemento <script> e executa
                const newScript = document.createElement('script');
                newScript.text = scriptContent;
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);  // Remove após execução

                //console.log('Script inline executado com sucesso.');
            } catch (e) {
                console.error('Erro ao executar script inline:', e, scriptContent);
            }
        }
    });
}

