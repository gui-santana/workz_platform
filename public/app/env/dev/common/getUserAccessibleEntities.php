<?php
function getUserAccessibleEntities($userSession) {
    
	//ACESSO
	
    $companies = array_column(
        search('cmp', 'employees', 'em', "us = '" . $userSession . "' AND nv > 0"), 
        'em'
    );

    //NEGÓCIOS INATIVADOS (pg = 0)
    $blocked_companies = array_column(
        search('cmp', 'companies', 'id', "id IN (" . implode(',', $companies) . ") AND pg = 0"), 
        'id'
    );

    //NEGÓCIOS ACESSÍVEIS
    $companies = array_values(array_diff($companies, $blocked_companies));

    //EQUIPES ACESSÍVEIS
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

    //LISTA DE EQUIPES ACESSÍVEIS FILTRADAS
    $teams = array_values(array_unique(array_column($teams, 'cm')));

	//MODERAÇÃO

	//LISTA DE NEGÓCIOS EM QUE O USUÁRIO É MODERADOR OU CRIADOR
	$mod_companies = search('cmp', 'companies', 'id,usmn,us', "JSON_CONTAINS(usmn, '\"$userSession\"') OR (usmn = '' AND us = '$userSession')");
	// Se houver resultados, armazena os IDs das equipes em um array
	$companies_manager = (!empty($mod_companies)) ? array_column($mod_companies, 'id') : [];
	
	
	//LISTA DE EQUIPES EM QUE O USUÁRIO É MODERADOR OU CRIADOR
	$mod_teams = search('cmp', 'teams', 'id,usmn,us', "JSON_CONTAINS(usmn, '\"$userSession\"') OR (usmn = '' AND us = '$userSession')");
	// Se houver resultados, armazena os IDs das equipes em um array
	$teams_manager = (!empty($mod_teams)) ? array_column($mod_teams, 'id') : [];

    return [
        'companies' => $companies,             // Empresas acessíveis
        'teams' => $teams,                     // Equipes acessíveis
        'companies_manager' => $companies_manager, // Empresas onde é moderador
        'teams_manager' => $teams_manager      // Equipes onde é moderador
    ];
}
?>