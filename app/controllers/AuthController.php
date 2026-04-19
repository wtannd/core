<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\AuthService;

/**
 * AuthController
 * 
 * Logic for registration and login.
 */
class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    /**
     * Check for Remember Me cookie and log in user if valid.
     */
    public static function checkRememberMe(): void
    {
        if (isset($_SESSION['mID'])) return;

        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) return;

        $authService = new AuthService();
        $member = $authService->findUser('token', $token);

        if ($member && $member['is_active']) {
            session_regenerate_id(true);
            AuthService::fillSession($member);
            $authService->updateLastLogin((int)$_SESSION['mID']);
        } else {
            // Invalid token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }

    /**
     * Show the login page.
     */
    public function showLogin(): void
    {
        $errors = [];
        $message = '';
        $this->render('auth/login.php', ['errors' => $errors, 'message' => $message]);
    }

    /**
     * Process login request.
     */
    public function processLogin(array $postData): void
    {
        $this->validateCsrf($postData);

        $this->rateLimit("login", 16);

        $email = trim(strtolower($postData['email'] ?? ''));
        // Rate limiting: max 5 attempts per hour per email address to prevent brute force
        if (!empty($email)) $this->rateLimit("login", 5, $email);

        $password = $postData['password'] ?? '';
        $rememberMe = isset($postData['remember_me']);

        $result = $this->authService->login($email, $password, $rememberMe);
        
        if ($result['success']) {
            $member = $result['member'];
            
            // Handle Remember Me
            if ($rememberMe) {
                $this->authService->setRememberMe((int)$member['mID']);
            }

            $this->authService->startSession($member);
            $this->safeRedirect();
        } else {
            $errors = ['login' => $result['message']];
            $this->render('auth/login.php', ['errors' => $errors]);
        }
    }

    /**
     * Handle ORCID login.
     */
    public function orcidLogin(): void
    {
        $client_id = ORCID_CLIENT_ID;
        $redirect_uri = ORCID_REDIRECT_URI;
        $orcid_url = "https://orcid.org/oauth/authorize?client_id=$client_id&response_type=code&scope=/authenticate&redirect_uri=$redirect_uri";
        
        header("Location: $orcid_url");
        exit;
    }

    /**
     * Handle ORCID callback logic.
     *
     * @param string $code
     * @return void
     */
    public function orcidCallback(string $code): void
    {
        $client_id     = ORCID_CLIENT_ID;
        $client_secret = ORCID_CLIENT_SECRET;
        $redirect_uri  = ORCID_REDIRECT_URI;

        $token_url = "https://orcid.org/oauth/token"; 
        $post_data = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri
        ];

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            $this->render('errors/general.php', ['errorMessage' => 'Failed to exchange code for token.']);
            exit;
        }

        $data = json_decode($response, true);
        $orcid = $data['orcid'];
        $name  = $data['name'] ?? 'ORCID User';

        // Check if user is already logged in (Linking ORCID to existing account)
        if (isset($_SESSION['mID'])) {
            if ($this->authService->updateOrcid((int)$_SESSION['mID'], $orcid)) {
                $_SESSION['success_message'] = 'ORCID successfully linked.';
            } else {
                $_SESSION['error_message'] = 'Failed to link ORCID. It may already be associated with another account.';
            }
            header('Location: /profile/edit');
            exit;
        }

        $member = $this->authService->findUser('ORCID', $orcid);

        if (!$member) {
            // New ORCID user - store data in session for profile completion
            $_SESSION['pending_orcid_registration'] = [
                'orcid' => $orcid,
                'name'  => $name
            ];
            header('Location: /complete_profile');
            exit;
        }

        // Existing user - Log the user in
        $this->authService->startSession($member);
        $this->safeRedirect();
    }

    /**
     * Handle logout.
     */
    public function logout(): void
    {
        if (isset($_SESSION['mID'])) {
            $this->authService->updateToken((int)$_SESSION['mID'], null);
        }

        // Clear session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // Clear remember_token cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);

        header('Location: /login');
        exit;
    }
}
