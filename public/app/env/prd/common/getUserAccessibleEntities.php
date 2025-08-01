<?php
function getUserAccessibleEntities($userSession) {
    // Empresas das quais o usuário tem acesso
    $companies = array_column(
        search('cmp', 'employees', 'em', "us = '" . $userSession . "' AND nv > 0"), 
        'em'
    );

    // Empresas bloqueadas (pg = 0)
    $blocked_companies = array_column(
        search('cmp', 'companies', 'id', "id IN (" . implode(',', $companies) . ") AND pg = 0"), 
        'id'
    );

    // Filtrar empresas acessíveis
    $companies = array_values(array_diff($companies, $blocked_companies));

    // Equipes das quais o usuário tem acesso
    $teams = search('cmp', 'teams_users', 'cm,st', "us = '" . $userSession . "'");
    
    foreach ($teams as $key => $team) {
        $cm = $team['cm'];
        $st = $team['st'];
        
        $teamData = search('cmp', 'teams', 'pg,em', "id = '" . $cm . "'");
        
        if (
            $cm == 0 || 
            $teamData[0]['pg'] == 0 || 
            !in_array($teamData[0]['em'], $companies) || 
            $st == 0
        ) {
            unset($teams[$key]);
        }
    }

    // Lista final de IDs das equipes acessíveis
    $teams = array_values(array_unique(array_column($teams, 'cm')));

    // 🔹 Empresas nas quais o usuário é moderador (nv >= 2)
    $companies_manager = array_filter(array_column(
        search('cmp', 'employees', 'em', "us = '" . $userSession . "' AND nv >= 2"), 
        'em'
    ));

    // 🔹 Equipes nas quais o usuário é moderador (nv >= 2)
    $teams_manager = array_filter(array_column(
        search('cmp', 'teams_users', 'cm', "us = '" . $userSession . "' AND nv >= 2"), 
        'cm'
    ));

    return [
        'companies' => $companies,             // Empresas acessíveis
        'teams' => $teams,                     // Equipes acessíveis
        'companies_manager' => $companies_manager, // Empresas onde é moderador
        'teams_manager' => $teams_manager      // Equipes onde é moderador
    ];
}
?>