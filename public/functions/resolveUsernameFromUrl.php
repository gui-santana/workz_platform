<?php
function resolveUsernameFromUrl() {
    if (!isset($_GET['username'])) return;

    $username = str_replace('/', '', $_GET['username']);

    // Realiza as buscas
    $user = search('hnw', 'hus', 'id', "un = '$username'");
    $company = search('cmp', 'companies', 'id', "un = '$username'");
    $team = search('cmp', 'teams', 'id', "un = '$username'");

    // Atribui o ID encontrado à chave correspondente em $_GET
    if (!empty($user)) {
        $_GET['profile'] = $user[0]['id'];
    } elseif (!empty($company)) {
        $_GET['company'] = $company[0]['id'];
    } elseif (!empty($team)) {
        $_GET['team'] = $team[0]['id'];
    }

    unset($_GET['username']);
}
?>