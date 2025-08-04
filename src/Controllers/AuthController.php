<?php
// src/Controllers/AuthController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Firebase\JWT\JWT;
use League\OAuth2\Client\Provider\Google;
use TheNetworg\OAuth2\Client\Provider\Azure;

class AuthController
{

    private General $generalModel;
    private Google $googleProvider;
    private Azure $microsoftProvider;

    public function __construct()
    {
        $this->generalModel = new General();

        // Configura o provedor do Google com as suas credenciais
        $this->googleProvider = new Google([            
            'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
            'redirectUri'  => 'http://localhost:8080/api/auth/google/callback',
        ]);

        // Configuração para a Microsoft
        $this->microsoftProvider = new Azure([
            'clientId'          => $_ENV['MICROSOFT_CLIENT_ID'],
            'clientSecret'      => $_ENV['MICROSOFT_CLIENT_SECRET'],
            'redirectUri'       => 'http://localhost:8080/api/auth/microsoft/callback',
            'scopes'            => ['openid', 'profile', 'email', 'User.Read'],
            'defaultEndPointVersion' => '2.0'
        ]);
    }

    // ===================================================================
    // Login com Microsoft
    // ===================================================================
    
    public function redirectToMicrosoft(): void
    {
        session_start();
        $authUrl = $this->microsoftProvider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->microsoftProvider->getState();
        header('Location: ' . $authUrl);
        exit();
    }

    public function handleMicrosoftCallback(): void
    {
        session_start();

        if (empty($_GET['state']) || (empty($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            http_response_code(400);
            echo json_encode(['error' => 'Estado OAuth inválido.']);
            exit();
        }

        try {
            $token = $this->microsoftProvider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            $microsoftUser = $this->microsoftProvider->getResourceOwner($token);
                        
            // Acedemos ao array de dados e pegamos o valor da chave 'email'.
            $userDataArray = $microsoftUser->toArray();
            $name = $userDataArray['name'] ?? null;
            $email = $userDataArray['email'] ?? null;

            if (!$email) {
                // Se ainda assim não encontrarmos, lançamos o erro.
                throw new \Exception('Não foi possível obter o email do utilizador da Microsoft a partir dos dados recebidos.');
            }

            // A partir daqui, a lógica é a mesma de antes.
            //$user = $this->generalModel->find(['email' => $email]);
            $user = $this->generalModel->search('workz_data','hus', ['ml' => $email]);


            if (!$user) {
                $newUserId = $this->generalModel->insert('workz_data','hus', [
                    'tt' => $name,
                    'ml' => $email,                    
                    'pd' => $microsoftUser->getId(),
                    'dt' => date('Y-m-d H:i:s')
                ]);
                $user = $this->generalModel->search('workz_data','hus', ['id' => $newUserId]);
            }

            $this->generateAndSendToken($user);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha na autenticação com a Microsoft.', 'details' => $e->getMessage()]);
            exit();
        }
    }

    // ===================================================================
    // Login com Google
    // ===================================================================

    public function redirectToGoogle(): void
    {        
        session_start();

        $authUrl = $this->googleProvider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->googleProvider->getState();
        
        header('Location: ' . $authUrl);
        exit();
    }

    public function handleGoogleCallback(): void
    {        
        session_start();

        if (empty($_GET['state']) || (empty($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            http_response_code(400);
            echo json_encode(['error' => 'Estado OAuth inválido ou a sessão expirou.']);
            exit();
        }

        try {
            $token = $this->googleProvider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            $googleUser = $this->googleProvider->getResourceOwner($token);
            $user = $this->generalModel->search('workz_data','hus', ['email' => $googleUser->getEmail()]);

            if (!$user) {
                $newUserId = $this->generalModel->insert('workz_data','hus', $data = [
                    'tt' => $googleUser->getName(),
                    'ml' => $googleUser->getEmail(),
                    'pd' => $googleUser->getId(),
                    'dt' => date('Y-m-d H:i:s')
                ]);                
                $user = $this->generalModel->search('workz_data','hus', ['id' => $newUserId]);
            }
            
            // O método generateAndSendToken redireciona para o frontend.
            $this->generateAndSendToken($user);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha na autenticação com o Google.', 'details' => $e->getMessage()]);
            exit();
        }
    }

    // ===================================================================
    // Login local
    // ===================================================================
    
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha são obrigatórios.']);
            return;
        }

        $user = $this->generalModel->search('workz_data','hus', ['email' => $data['email']]);
        //(['email' => $data['email']]);

        // Verificação 1: O usuário foi encontrado no banco?
        if (!$user) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Credenciais inválidas (usuário não encontrado).']);
            return;
        }

        // Verificação 2: A senha foi fornecida no banco? (Para contas locais)
        if (!isset($user['password']) || $user['password'] === null) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Esta conta deve ser acessada via login social.']);
            return;
        }

        // Verificação 3: A senha corresponde?
        if (!password_verify($data['password'], $user['password'])) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Credenciais inválidas (senha incorreta).']);
            return;
        }

        // Se tudo passou, gera o token
        $this->generateAndSendToken($user);
    }
    
    private function generateAndSendToken(array $user): void
    {
        $secretKey = $_ENV['JWT_SECRET'];
        $issuedAt = time();
        $expire = $issuedAt + 3600; // 1 hora

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expire,
            'sub'  => $user['id'],
            'name' => $user['name']
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // Verificamos o campo 'provider' do utilizador.
        $isSocialLogin = ($user['provider'] === 'google' || $user['provider'] === 'microsoft');

        if ($isSocialLogin) {
            // LÓGICA PARA LOGIN SOCIAL            
            $frontendUrl = 'http://localhost:8080';
            header('Location: ' . $frontendUrl . '?token=' . $jwt);
            exit();

        } else {
            // LÓGICA PARA LOGIN LOCAL (DO FORMULÁRIO)            
            http_response_code(200);
            echo json_encode([
                'message' => 'Login bem-sucedido!',
                'token' => $jwt
            ]);
        }        
    }

    // ===================================================================
    // Registro Local
    // ===================================================================

    public function register(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        //1. Validação dos dados de entrada
        if(empty($data['name']) || empty($data['email']) || empty($data['password'])){
            http_response_code(400); //Bad Request
            echo json_encode(['error' => 'Todos os campos são obrigatórios.']);
            return;
        }

        if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)){
            http_response_code(400);
            echo json_encode(['error' => 'Formato de e-mail inválido.']);
            return;
        }

        //2. Verifica se o usuário já existe
        $existingUser = $this->generalModel->search('workz_data','hus', ['email' => $data['email']]);
        if($existingUser){
            http_response_code(400);
            echo json_encode(['error' => 'Este e-mail já está sendo utilizado.']);
            return;
        }

        //3. Cria o novo usuário (o Model irá hashear a senha)
        $newUserId = $this->generalModel->insert('workz_data','hus', $data = [
            'tt' => $data['name'],
            'ml' => $data['email'],
            'pw' => $data['password'],
            'dt' => date('Y-m-d H:i:s')
        ]);

        //4. Retorna uma resposta de sucesso
        if($newUserId){
                        
            // LÓGICA PARA LOGIN LOCAL (DO FORMULÁRIO)            
            http_response_code(201);
            echo json_encode([
                'userId' => $newUserId,
                'message' => 'Cadastro bem-sucedido!'                
            ]);

        }else{
            http_response_code(500); //Erro
            echo json_encode(['error' => 'Ocorreu um erro ao criar a conta.'.$data ]);            
        }

    }

}
