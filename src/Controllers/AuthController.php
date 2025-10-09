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
            'redirectUri'  => 'http://localhost:9090/api/auth/google/callback',
        ]);

        // Configuração para a Microsoft
        $this->microsoftProvider = new Azure([
            'clientId'          => $_ENV['MICROSOFT_CLIENT_ID'],
            'clientSecret'      => $_ENV['MICROSOFT_CLIENT_SECRET'],
            'redirectUri'       => 'http://localhost:9090/api/auth/microsoft/callback',
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
            $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['ml' => $email], false);

            if ($user && ($user['provider'] === '' || $user['provider'] === null)) {
                $user['provider'] = 'microsoft';
                $this->generalModel->update('workz_data', 'hus', ['provider' => $user['provider']], ['id' => $user['id']]);
                // If the user has no profile image yet, try to fetch it from Microsoft Graph
                if (empty($user['im'])) {
                    $photo = $this->fetchMicrosoftProfilePhoto($token->getToken());
                    if ($photo !== null) {
                        [$bytes, $ctype] = $photo;
                        $saved = $this->saveProfileImageBytes($bytes, $ctype);
                        if ($saved) {
                            $this->generalModel->update('workz_data', 'hus', ['im' => $saved], ['id' => $user['id']]);
                            $user['im'] = $saved;
                        }
                    }
                }
            }

            if (!$user) {
                // New user: try to download profile photo
                $savedIm = null;
                $photo = $this->fetchMicrosoftProfilePhoto($token->getToken());
                if ($photo !== null) {
                    [$bytes, $ctype] = $photo;
                    $savedIm = $this->saveProfileImageBytes($bytes, $ctype);
                }

                $newUserId = $this->generalModel->insert('workz_data','hus', [                    
                    'tt' => $name,
                    'ml' => $email,                    
                    'dt' => date('Y-m-d H:i:s'),                    
                    'pd' => $microsoftUser->getId(),
                    'provider' => 'microsoft',
                    'im' => $savedIm
                ]);
                $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['id' => $newUserId], false);
            }

            $this->generateAndSendToken($user, 'social');

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
            $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['ml' => $googleUser->getEmail()], false);

            if (!$user) {
                // Get Google profile picture
                $gArr = is_object($googleUser) ? $googleUser->toArray() : [];
                $avatarUrl = null;
                if (is_object($googleUser) && method_exists($googleUser, 'getAvatar')) { $avatarUrl = $googleUser->getAvatar(); }
                if (!$avatarUrl && is_array($gArr)) { $avatarUrl = $gArr['picture'] ?? null; }
                $savedIm = $avatarUrl ? $this->saveProfileImageFromUrl($avatarUrl) : null;

                $newUserId = $this->generalModel->insert('workz_data','hus', [                    
                    'tt' => $googleUser->getName(),
                    'ml' => $googleUser->getEmail(),                    
                    'dt' => date('Y-m-d H:i:s'),                    
                    'pd' => $googleUser->getId(),
                    'provider' => 'google',
                    'im' => $savedIm
                ]);
                $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['id' => $newUserId], false);
            } else {
                // Update missing profile picture
                if (empty($user['im'])) {
                    $gArr = is_object($googleUser) ? $googleUser->toArray() : [];
                    $avatarUrl = null;
                    if (is_object($googleUser) && method_exists($googleUser, 'getAvatar')) { $avatarUrl = $googleUser->getAvatar(); }
                    if (!$avatarUrl && is_array($gArr)) { $avatarUrl = $gArr['picture'] ?? null; }
                    if ($avatarUrl) {
                        $saved = $this->saveProfileImageFromUrl($avatarUrl);
                        if ($saved) {
                            $this->generalModel->update('workz_data','hus', ['im' => $saved], ['id' => $user['id']]);
                            $user['im'] = $saved;
                        }
                    }
                }
            }
            
            // O método generateAndSendToken redireciona para o frontend.
            $this->generateAndSendToken($user, 'social');

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha na autenticação com o Google.', 'details' => $e->getMessage()]);
            exit();
        }
    }

    // ===================================================================
    // Login local (OK)
    // ===================================================================
    
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password'])) {
            echo json_encode(['error' => 'Email e senha são obrigatórios.']);
            http_response_code(400);            
            return;
        }

        $user = $this->generalModel->search('workz_data','hus', ['id', 'pw', 'tt', 'provider'], ['ml' => $data['email']], false);

        // Verificação 1: O usuário foi encontrado no banco?
        if (!$user) {
            echo json_encode(['error' => 'Credenciais inválidas (usuário não encontrado).']);
            http_response_code(401); // Unauthorized            
            return;
        }

        // Verificação 2: A senha foi fornecida no banco? (Para contas locais)
        if (!isset($user['pw']) || $user['pw'] === null || empty($user['pw'])) {
            echo json_encode(['error' => 'Esta conta deve ser acessada via login social.']);
            http_response_code(401); // Unauthorized
            return;
        }

        // Verificação 3: A senha corresponde?            
            

        if (!password_verify($data['password'], $user['pw'])) {
            echo json_encode(['error' => 'Credenciais inválidas (senha incorreta).']);
            http_response_code(401); // Unauthorized
            return;
        }
        
        // Se tudo passou, gera o token
        $this->generateAndSendToken($user, 'local');
    }
    
    private function generateAndSendToken(array $user, string $type = 'local'): void
    {
        $secretKey = $_ENV['JWT_SECRET'];
        $issuedAt = time();
        $expire = $issuedAt + 3600; // 1 hora

        $payload = [
            'iat'  => $issuedAt,
            'exp'  => $expire,
            'sub'  => $user['id'],
            'name' => $user['tt']
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // Verificamos o campo 'provider' do utilizador.
        $isSocialLogin = ($type === 'social' && ($user['provider'] === 'google' || $user['provider'] === 'microsoft'));

        if ($isSocialLogin) {
            // LÓGICA PARA LOGIN SOCIAL            
            $frontendUrl = 'http://localhost:9090';
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

        if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['password-repeat'])){
            echo json_encode(['error' => 'Nome de usuário, email e senha são obrigatórios.']);
            http_response_code(400);            
            return;
        }

        // Verifica se o email já está em uso
        $existingUser = $this->generalModel->search('workz_data', 'hus', ['id'], ['ml' => $data['email']], false);
        if ($existingUser) {
            echo json_encode(['error' => 'Este email já está registrado.']);
            http_response_code(409); // Conflict            
            return;
        }

        // Valida o formato do e-mail
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            echo json_encode(['error' => 'O formato do e-mail é inválido.']);
            http_response_code(400);            
            return;
        }

        // Checa se as senhas conferem
        if ($data['password'] !== $data['password-repeat']) {
            echo json_encode(['error' => 'As senhas não conferem.']);
            http_response_code(400);            
            return;
        }

        // Valida o formato da senha
        if (!$this->isValidPassword($data['password'])) {
            echo json_encode(['error' => 'A senha deve conter pelo menos 8 caracteres, uma letra maiúscula, uma letra minúscula, um número e um caractere especial.']);
            http_response_code(400);            
            return;
        }

        // Criptografa a senha
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);        
        

        $userId = $this->generalModel->insert('workz_data', 'hus', [
            'tt' => $data['name'],
            'ml' => $data['email'],
            'pw' => $hashedPassword,
            'dt' => date('Y-m-d H:i:s'),
            'provider' => 'local' // Indica que é um registro local
        ]);

        if ($userId) {
            // Busca o usuário recém-criado para gerar o token
            $user = $this->generalModel->search('workz_data', 'hus', ['id', 'tt', 'ml', 'provider'], ['id' => $userId], false);            
            
            // Verificação 1: O usuário foi encontrado no banco?
            if (!$user) {
                echo json_encode(['error' => 'Credenciais inválidas (usuário não encontrado).']);
                http_response_code(401); // Unauthorized            
                return;
            }

            $this->generateAndSendToken($user);

        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao registrar o usuário.']);
        }        
    }

    public function isValidPassword($password) {
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.#])[A-Za-z\d@$!%*?&.#]{8,}$/';
        return preg_match($regex, $password);
    }

    // ===============================
    // Social avatar helpers
    // ===============================
    private function imagesUsersDir(): string
    {
        $rootDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users';
        if (!is_dir($rootDir)) { @mkdir($rootDir, 0755, true); }
        return $rootDir;
    }

    private function extFromContentType(string $ctype): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];
        $lc = strtolower(trim($ctype));
        return $map[$lc] ?? 'jpg';
    }

    private function saveProfileImageBytes(string $bytes, string $contentType = 'image/jpeg'): ?string
    {
        if ($bytes === '') return null;
        $dir = $this->imagesUsersDir();
        $ext = $this->extFromContentType($contentType);
        $name = 'people_' . uniqid('', true) . '.' . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@file_put_contents($path, $bytes) === false) { return null; }
        return '/images/users/' . $name;
    }

    private function saveProfileImageFromUrl(string $url): ?string
    {
        if (!$url) return null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => true,
            ]);
            $resp = curl_exec($ch);
            if ($resp === false) { curl_close($ch); return null; }
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
            $body = substr($resp, (int)$headerSize);
            curl_close($ch);
            return $this->saveProfileImageBytes($body, $ctype);
        }
        // Fallback
        $context = stream_context_create(['http' => ['timeout' => 15]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) return null;
        // Content-type unknown in fallback, default jpg
        return $this->saveProfileImageBytes($body, 'image/jpeg');
    }

    private function fetchMicrosoftProfilePhoto(string $accessToken): ?array
    {
        if (!$accessToken) return null;
        $url = 'https://graph.microsoft.com/v1.0/me/photo/$value';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [ 'Authorization: Bearer ' . $accessToken ]
            ]);
            $body = curl_exec($ch);
            if ($body === false) { curl_close($ch); return null; }
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
            curl_close($ch);
            if ($code >= 200 && $code < 300 && !empty($body)) {
                return [$body, $ctype];
            }
            return null;
        }
        // Fallback
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => 'Authorization: Bearer ' . $accessToken
            ]
        ];
        $context = stream_context_create($opts);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) return null;
        return [$body, 'image/jpeg'];
    }

}
