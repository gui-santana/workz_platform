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
     * Adiciona uma nova rota ao roteador.
     *
     * @param string $method O método HTTP (GET, POST, etc.)
     * @param string $uri A URI da rota (ex: /api/users)
     * @param array $action O Controller e o método a serem executados [Controller::class, 'methodName']
     */
    public function add(string $method, string $uri, array $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action
        ];
    }

    /**
     * Tenta encontrar e executar a rota correspondente à requisição atual.
     *
     * @param string $uri A URI da requisição atual.
     * @param string $method O método da requisição atual.
     */
    public function dispatch(string $uri, string $method): void
    {
        foreach ($this->routes as $route) {
            // Verifica se o método e a URI da rota registrada correspondem à requisição atual.
            if ($route['uri'] === $uri && $route['method'] === $method) {
                
                // Extrai o nome da classe do Controller e o método a ser chamado.
                $controllerClass = $route['action'][0];
                $controllerMethod = $route['action'][1];

                // Cria uma nova instância do Controller.
                $controller = new $controllerClass();

                // Chama o método do Controller.
                $controller->$controllerMethod();
                return; // Para a execução após encontrar a rota.
            }
        }

        // Se o loop terminar e nenhuma rota for encontrada, retorna um erro 404.
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}
