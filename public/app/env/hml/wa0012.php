<?
$username = search('hnw', 'hus', 'un', "id = '".$_SESSION['wz']."'")[0]['un'];
/*
?>
<iframe id="wFrame" class="large-12 medium-12 small-12 height-100" src="https://view.officeapps.live.com/op/embed.aspx?src=https://graph.microsoft.com/v1.0/me/drive/items/{$excelDocumentId}/workbook" name="" frameborder="0" scrolling="no"></iframe>


<script>

document.addEventListener('DOMContentLoaded', function () {
    // Crie um elemento script
    var script = document.createElement('script');

    // Adicione o conteúdo do script (substitua com o seu script)
    script.innerHTML = `
        // Seu script inline aqui        
		window.parent.postMessage({ action: 'iframeLoaded' }, '*');
    `;

    // Obtenha o elemento do iframe
    var iframeElement = document.getElementById('wFrame');

    // Verifique se o elemento do iframe foi encontrado
    if (iframeElement) {
        // Adicione o script ao documento do iframe
        var iframeDocument = iframeElement.contentDocument || iframeElement.contentWindow.document;
        iframeDocument.body.appendChild(script);
    } else {
        console.error("Elemento do iframe não encontrado.");
    }
});

// Função para verificar mudanças na URL do iframe
function checkURLChange() {
    console.log("checkURLChange started");
    var iframe = document.getElementById('wFrame');

    // Verifica se o iframe está carregado
    if (iframe && !iframe.hasExecuted) {
        console.log("iframe is loaded");

        // Marca o iframe como executado
        iframe.hasExecuted = true;

        // Aguarda o evento DOMContentLoaded dentro do iframe
        setTimeout(function() {
            // Obtém o documento dentro do iframe
            var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;

            // Preenche os campos com os valores desejados
            var userField = iframeDocument.querySelector("#userxx");
            var passwordField = iframeDocument.querySelector("#passwordxx");
            var sendButton = iframeDocument.querySelector("#send");

            if (userField && passwordField && sendButton) {
                userField.value = '<?php echo $username; ?>';
                console.log("#userxx is filled: <?php echo $username; ?>");
                passwordField.value = 'wZ@cesso.222!@#';
                console.log("#passwordxx is filled with password");

                // Clica no botão
                sendButton.click();
            } else {
                console.error("One or more elements not found in the iframe.");
            }
        }, 2000); // Ajuste o tempo conforme necessário
    }
}

// Adiciona um ouvinte para receber mensagens do iframe
window.addEventListener('message', function (event) {
    // Verifica se a mensagem é válida
    if (event.data && event.data.action === 'iframeLoaded') {
        // Execute o código desejado após o iframe ser carregado
        checkURLChange();
    }
});

</script>
*/
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Drive API Quickstart</title>
		<meta charset="utf-8" />
		<style>
		/* Adicionei algum estilo para os ícones e a paginação */
		.file-container {
			display: flex;
			flex-wrap: wrap;
			gap: 20px;
		}

		.file-icon {
			width: 80px;
			height: 80px;
			background-color: #f0f0f0;
			border: 1px solid #ccc;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 20px;
		}

		.pagination {
			display: flex;
			list-style: none;
			gap: 10px;
		}

		.page-link {
			padding: 5px 10px;
			background-color: #007bff;
			color: #fff;
			text-decoration: none;
			border-radius: 5px;
		}
		</style>
	</head>
  <body>
    <p>Drive API Quickstart</p>

    <!--Add buttons to initiate auth sequence and sign out-->
    <button id="authorize_button" onclick="handleAuthClick()">Authorize</button>
    <button id="signout_button" onclick="handleSignoutClick()">Sign Out</button>

    <pre id="content" style="white-space: pre-wrap;"></pre>

	<div id="fileContainer" class="file-container">
    <!-- Aqui serão adicionados os ícones dos arquivos dinamicamente -->
  </div>

  <ul id="pagination" class="pagination">
    <!-- Aqui será adicionada a paginação dinamicamente -->
  </ul>

    <script type="text/javascript">
      /* exported gapiLoaded */
      /* exported gisLoaded */
      /* exported handleAuthClick */
      /* exported handleSignoutClick */

      // TODO(developer): Set to client ID and API key from the Developer Console
      const CLIENT_ID = '427324182547-ovu8udbtl9r6j0a2idcaehr1qq62rtbs.apps.googleusercontent.com';
      const API_KEY = 'wYsS5w6OKw332kpA769QL5Ic';

      // Discovery doc URL for APIs used by the quickstart
      const DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/drive/v3/rest';

      // Authorization scopes required by the API; multiple scopes can be
      // included, separated by spaces.
      const SCOPES = 'https://www.googleapis.com/auth/drive.metadata';

      let tokenClient;
      let gapiInited = false;
      let gisInited = false;

      document.getElementById('authorize_button').style.visibility = 'hidden';
      document.getElementById('signout_button').style.visibility = 'hidden';

      /**
       * Callback after api.js is loaded.
       */
      function gapiLoaded() {
        gapi.load('client', initializeGapiClient);
      }

      /**
       * Callback after the API client is loaded. Loads the
       * discovery doc to initialize the API.
       */
      async function initializeGapiClient() {
        await gapi.client.init({
          apiKey: API_KEY,
          discoveryDocs: [DISCOVERY_DOC],
        });
        gapiInited = true;
        maybeEnableButtons();
      }

      /**
       * Callback after Google Identity Services are loaded.
       */
      function gisLoaded() {
        tokenClient = google.accounts.oauth2.initTokenClient({
          client_id: CLIENT_ID,
          scope: SCOPES,
          callback: '', // defined later
        });
        gisInited = true;
        maybeEnableButtons();
      }

      /**
       * Enables user interaction after all libraries are loaded.
       */
      function maybeEnableButtons() {
        if (gapiInited && gisInited) {
          document.getElementById('authorize_button').style.visibility = 'visible';
        }
      }

      /**
       *  Sign in the user upon button click.
       */
      function handleAuthClick() {
        tokenClient.callback = async (resp) => {
          if (resp.error !== undefined) {
            throw (resp);
          }
          document.getElementById('signout_button').style.visibility = 'visible';
          document.getElementById('authorize_button').innerText = 'Refresh';
          await listFiles();
        };

        if (gapi.client.getToken() === null) {
          // Prompt the user to select a Google Account and ask for consent to share their data
          // when establishing a new session.
          tokenClient.requestAccessToken({prompt: 'consent'});
        } else {
          // Skip display of account chooser and consent dialog for an existing session.
          tokenClient.requestAccessToken({prompt: ''});
        }
      }

      /**
       *  Sign out the user upon button click.
       */
      function handleSignoutClick() {
        const token = gapi.client.getToken();
        if (token !== null) {
          google.accounts.oauth2.revoke(token.access_token);
          gapi.client.setToken('');
          document.getElementById('content').innerText = '';
          document.getElementById('authorize_button').innerText = 'Authorize';
          document.getElementById('signout_button').style.visibility = 'hidden';
        }
      }

      /**
       * Print metadata for first 10 files.
       */
      async function listFiles() {
        let response;
        try {
          response = await gapi.client.drive.files.list({
            'pageSize': 1000,
            'fields': 'files(id, name, size, mimeType, modifiedTime, thumbnailLink, webViewLink, owners)',
          });
        } catch (err) {
          document.getElementById('content').innerText = err.message;
          return;
        }
        const files = response.result.files;
        if (!files || files.length == 0) {
          document.getElementById('content').innerText = 'No files found.';
          return;
        }
		
		
		var itemsPerPage = 20;
		var currentPage = 1;

		function showFiles(page) {
		  var container = document.getElementById('fileContainer');
		  var pagination = document.getElementById('pagination');

		  // Limpa o conteúdo atual
		  container.innerHTML = '';
		  pagination.innerHTML = '';

		  // Calcula o índice inicial e final para a página atual
		  var startIndex = (page - 1) * itemsPerPage;
		  var endIndex = startIndex + itemsPerPage;

		  // Exibe os arquivos para a página atual
		  for (var i = startIndex; i < endIndex && i < files.length; i++) {
			var fileIcon = document.createElement('div');
			fileIcon.className = 'file-icon';
			fileIcon.textContent = files[i].name;
			
			console.log(files[i].webViewLink);
			
			 // Adiciona imagem da miniatura como fundo do ícone, se disponível
			  if (files[i].thumbnailLink) {
				fileIcon.style.backgroundImage = 'url(' + files[i].thumbnailLink + ')';
			  } else {
				// Se não houver miniatura, você pode adicionar um ícone padrão aqui
				fileIcon.style.backgroundImage = 'url("icone-padrao.png")';
			  }
		  			  
			container.appendChild(fileIcon);
			
			
		  }

		  // Adiciona os botões de paginação
		  var totalPages = Math.ceil(files.length / itemsPerPage);
		  for (var i = 1; i <= totalPages; i++) {
			var pageLink = document.createElement('a');
			pageLink.href = '#';
			pageLink.className = 'page-link text-ellipsis';
			pageLink.textContent = i;
			pageLink.addEventListener('click', function (event) {
			  event.preventDefault();
			  currentPage = parseInt(event.target.textContent);
			  showFiles(currentPage);
			});
			pagination.appendChild(pageLink);
		  }
		}

		 function openFile(file) {
			  if (file.type === 'pasta') {
				alert('Abrindo a pasta: ' + file.name);
				// Aqui você pode adicionar lógica para abrir a pasta correspondente
			  } else if (file.type === 'documento') {
				alert('Abrindo o documento no Google Docs: ' + file.name);
				// Aqui você pode adicionar lógica para abrir o documento no Google Docs
				// por exemplo, redirecionando para o URL do Google Docs com o arquivo
			  }
			}
	
    // Exibe os arquivos na página inicial
    showFiles(currentPage);
		
		/*
        // Flatten to string to display
        const output = files.reduce(
            (str, file) => `${str}${file.name} (${file.id})\n`,
            'Files:\n');
        document.getElementById('content').innerText = output;
		*/
      }
    </script>
    <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
    <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>
  </body>
</html>