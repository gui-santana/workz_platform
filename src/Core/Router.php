<?php

// src/Core/Router.php

namespace Workz\Platform\Core;

class Router
{
    /**
     * Array para armazenar todas as rotas registradas.
     * @var array
     */
    private array $routes = [];

    /**
     * Adiciona uma nova rota. O URI agora pode ser um padrão de regex.
     * @param string $method
     * @param string $pattern O padrão de URL (ex: '/api/users/(\d+)/follow')
     * @param array $action
     * @param array|null $middleware
     */
    public function add(string $method, string $pattern, array $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern, // Mudámos de 'uri' para 'pattern'
            'action' => $action
        ];
    }
    
    /**
     * Procura uma rota que corresponda ao padrão da URI e executa a sua ação.
     *
     * @param string $uri A URI da requisição atual.
     * @param string $method O método da requisição atual.
     */
    public function dispatch(string $uri, string $method): void
    {
        foreach ($this->routes as $route) {
            // Converte o padrão simples para uma expressão regular completa
            $regex = "#^" . $route['pattern'] . "$#";

            // Verifica se o método corresponde e se o padrão da URI corresponde
            if ($route['method'] === $method && preg_match($regex, $uri, $matches)) {
                
                // Remove a correspondência completa da URL do array de 'matches'
                // para que fiquem apenas os parâmetros capturados (ex: o ID).
                array_shift($matches);
                $params = $matches;

                $payload = null;                

                $controllerClass = $route['action'][0];
                $controllerMethod = $route['action'][1];
                $controller = new $controllerClass();

                // Passa o payload e os parâmetros da URL para o método do controller
                $controller->$controllerMethod($payload, ...$params);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Endpoint Not Found']);
    }
}
