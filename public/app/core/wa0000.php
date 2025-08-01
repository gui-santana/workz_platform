<?php
// Inclui as funções necessárias (search, etc.)
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

// Busca categorias no banco de dados
$categories = [
	['id' => 0, 'name' => 'Originais'],
	['id' => 1, 'name' => 'Produtividade'],
	['id' => 2, 'name' => 'Jogos'],
	['id' => 3, 'name' => 'Mídias'],
	['id' => 4, 'name' => 'Saúde']
];
?>
<style>
header {
    background-color: #6200ea;
    color: white;
    padding: 1rem;
    text-align: center;
}

header input {
    margin-top: 1rem;
    padding: 0.5rem;
    width: 90%;
    max-width: 400px;
    border-radius: 5px;
    border: none;
}

nav {
    display: flex;
    overflow-x: auto;
    padding: 1rem;
    background: #eee;
    gap: 0.5rem;
}

nav button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 5px;
    background: #6200ea;
    color: white;
    cursor: pointer;
}

nav button:hover {
    background: #4e00c7;
}

main {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.card {
    background: white;
    padding: 1rem;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.card img {
    width: 100px;
    height: 100px;
    border-radius: 10px;
    margin-bottom: 1rem;
}

.card h2 {
    font-size: 1.25rem;
    margin: 0;
}

.card p {
    font-size: 0.9rem;
    color: #666;
}

</style>
<header>
	<h1>Minha Loja de Aplicativos</h1>
	<input type="text" id="search" placeholder="Buscar aplicativos..." oninput="searchApps(this.value)">
</header>

<nav id="categories">
	<?php foreach ($categories as $category): ?>
		<button onclick="loadCategory('<?php echo $category['id']; ?>')">
			<?php echo $category['name']; ?>
		</button>
	<?php endforeach; ?>
</nav>

<main id="app-list">
	<!-- Os aplicativos serão carregados dinamicamente aqui -->
</main>
<script>


window.onload = function () {
    loadMainPage()
};

function loadMainPage(){
	const categories = <?php echo json_encode($categories); ?>; // Converte as categorias em um array JS
    const appListContainer = document.getElementById('app-list');
    
    // Gera o HTML inicial de todas as categorias
    let html = '';
    categories.forEach((category) => {
        html += '<h2>' + category.name + '</h2><div id="cat' + category.id + '"></div>';
    });
    appListContainer.innerHTML = html;

    // Carrega os dados para cada categoria
    categories.forEach((category) => {
        const categoryId = category.id; // Garante que o ID seja único
        goTo('core/backengine/wa0000/views/load_apps.php', 'cat' + categoryId, '1', categoryId);
    });
}

function loadCategory(categoryId) {
    goTo('core/backengine/wa0000/views/load_apps.php', 'app-list', '1', categoryId);
}

function searchApps(query) {
	if (query === ''){
		loadMainPage()
	}else{
		goTo('core/backengine/wa0000/views/search_apps.php', 'app-list', '1', query);
	}
    
}
</script>