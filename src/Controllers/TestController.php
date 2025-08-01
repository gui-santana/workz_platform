<?php

// src/Controllers/TestController.php

namespace Workz\Platform\Controllers;

class TestController
{
    /**
     * Um método de exemplo para nosso endpoint de teste.
     */
    public function index(): void
    {
        // Define o código de status da resposta para 200 (OK).
        http_response_code(200);
        
        // Retorna uma simples mensagem JSON.
        echo json_encode([
            'message' => 'API is working!',
            'status' => 'success',
            'timestamp' => time()
        ]);
    }
}
