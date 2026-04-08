<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\RateLimiter;
use app\models\Member;
use app\models\AuthService;
use app\models\lookups\Institution;
use app\models\lookups\ResearchBranch;

/**
 * AuthController
 * 
 * Logic for registration and login.
 */
class AuthController
{
    private Member $memberModel;
    private Institution $institutionModel;
    private ResearchBranch $branchModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->memberModel = new Member();
        $this->institutionModel = new Institution();
        $this->branchModel = new ResearchBranch();
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

        $memberModel = new Member();
        $member = $memberModel->findByToken($token);

        if ($member && $member['is_active']) {
            session_regenerate_id(true);
            $_SESSION['mID'] = $member['mID'];
            $_SESSION['email'] = $member['email'];
            $_SESSION['display_name'] = $member['display_name'];
            $_SESSION['pub_name'] = $member['pub_name'];
            $_SESSION['core_id'] = $memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
            $_SESSION['mrole'] = $member['mrole'];
            $_SESSION['admin_role'] = $member['admin_role'];

            $memberModel->updateLastLogin((int)$_SESSION['mID']);
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
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $errors = [];
        $message = '';
        include VIEWS_PATH_TRIMMED . '/auth/login.php';
    }

    /**
     * Show the registration page.
     */
    public function showRegister(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $errors = [];
        $message = '';
        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();
        include VIEWS_PATH_TRIMMED . '/auth/register.php';
    }

    /**
     * Show the complete profile page for ORCID users.
     */
    public function showCompleteProfile(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            header('Location: /login');
            exit;
        }

        // Split ORCID name into First and Last name for pre-filling
        $parts = explode(' ', $pending['name'], 2);
        $preFirstName = count($parts) > 1 ? $parts[0] : '';
        $preFamilyName = count($parts) > 1 ? $parts[1] : $parts[0];

        $errors = [];
        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();
        include VIEWS_PATH_TRIMMED . '/auth/complete_profile.php';
    }

    /**
     * Process login request.
     */
    public function processLogin(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        // Rate limiting: max 16 attempts per hour per IP to prevent brute force
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
                $token = bin2hex(random_bytes(32));
                if ($this->memberModel->updateToken((int)$member['mID'], $token)) {
                    $expiry = time() + REMEMBER_ME_DURATION;
                    setcookie('remember_token', $token, [
                        'expires' => $expiry,
                        'path' => '/',
                        'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }
            }

            $this->startSession($member);
            header('Location: /');
            exit;
        } else {
            $errors = ['login' => $result['message']];
            include VIEWS_PATH_TRIMMED . '/auth/login.php';
        }
    }

    /**
     * Process registration request.
     */
    public function processRegister(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        $result = $this->authService->register($postData);
        
        if ($result['success']) {
            $_SESSION['success_message'] = 'Registration successful! Please check your email to verify your account before login.';
            header('Location: /login');
            exit;
        } else {
            $errors = $result['errors'];
            $institutions = $this->institutionModel->getAllInstitutions();
            $researchBranches = $this->branchModel->getAllBranches();
            include VIEWS_PATH_TRIMMED . '/auth/register.php';
        }
    }

    /**
     * Process complete profile request.
     */
    public function processCompleteProfile(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            $errors = ['general' => 'No pending ORCID registration found.'];
            include VIEWS_PATH_TRIMMED . '/auth/complete_profile.php';
            exit;
        }

        $result = $this->authService->finalizeOrcidRegistration($postData, $pending);
        
        if ($result['success']) {
            unset($_SESSION['pending_orcid_registration']);
            
            $member = $result['member'];
            $this->setRememberMe((int)$member['mID']);
            $this->startSession($member);
            
            header('Location: /');
            exit;
        } else {
            $errors = $result['errors'] ?? ['general' => $result['message']];
            include VIEWS_PATH_TRIMMED . '/auth/complete_profile.php';
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
     * @return array
     */
    public function orcidCallback(string $code): array
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
            return ['success' => false, 'message' => 'Failed to exchange code for token.'];
        }

        $data = json_decode($response, true);
        $orcid = $data['orcid'];
        $name  = $data['name'] ?? 'ORCID User';

        // Check if user is already logged in (Linking ORCID to existing account)
        if (isset($_SESSION['mID'])) {
            if ($this->memberModel->updateOrcid((int)$_SESSION['mID'], $orcid)) {
                $_SESSION['success_message'] = 'ORCID successfully linked.';
            } else {
                $_SESSION['error_message'] = 'Failed to link ORCID. It may already be associated with another account.';
            }
            header('Location: /profile/edit');
            exit;
        }

        $member = $this->memberModel->findByOrcid($orcid);

        if (!$member) {
            // New ORCID user - store data in session for profile completion
            $_SESSION['pending_orcid_registration'] = [
                'orcid' => $orcid,
                'name'  => $name
            ];
            return ['success' => true, 'pending' => true];
        }

        // Existing user - Log the user in
        $this->setRememberMe((int)$member['mID']);

        $this->startSession($member);

        return ['success' => true];
    }

    /**
     * Helper to set Remember Me token and cookie.
     *
     * @param int $mID
     * @return void
     */
    private function setRememberMe(int $mID): void
    {
        $token = bin2hex(random_bytes(32));
        if ($this->memberModel->updateToken($mID, $token)) {
            $expiry = time() + REMEMBER_ME_DURATION;
            setcookie('remember_token', $token, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }

    public function verifyEmail(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            include VIEWS_PATH_TRIMMED . '/errors/400.php';
            exit;
        }

        // Rate limiting: max 32 attempts per hour per IP to prevent DB spam
        $this->rateLimit("verify_email", 32);

        $member = $this->memberModel->findByEmailToken($token, 'verify_email');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Verification link is invalid or has expired.';
            include VIEWS_PATH_TRIMMED . '/errors/general.php';
            exit;
        }

        $this->memberModel->deleteEmailToken((int)$member['mID'], 'verify_email');
        $this->memberModel->setEmailVerified((int)$member['mID'], true);

        $this->startSession($member);

        header('Location: /');
        exit;
    }

    public function showForgotPassword(): void
    {
        include VIEWS_PATH_TRIMMED . '/auth/forgot_password.php';
    }

    public function processForgotPassword(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        // HARD LIMIT: Max 10 password reset attempts per IP address per hour to prevent SMTP spam.
        $this->rateLimit("pwd_forgot", 10);

        $email = trim(strtolower($postData['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = 'Please enter a valid email address.';
            include VIEWS_PATH_TRIMMED . '/auth/forgot_password.php';
            exit;
        }

        // USER LIMIT: Max 3 password reset attempts per specific Email per hour to protect user.
        $this->rateLimit("pwd_forgot", 3, $email);

        $member = $this->memberModel->findByEmail($email);

        if ($member) {
            $token = $this->memberModel->createEmailToken((int)$member['mID'], 'reset_password');
            if ($token) {
                $resetUrl = SITE_URL . '/reset-password?token=' . $token;
                $subject = 'Reset your ' . SITE_TITLE . ' password';
                $body = "Click the link below to reset your password:\n\n$resetUrl\n\nThis link expires in 30 minutes.";
                $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                mail($email, $subject, $body, $headers);
            }
        }

        $_SESSION['success_message'] = 'If that email exists, you will receive a password reset link.';
        include VIEWS_PATH_TRIMMED . '/auth/forgot_password.php';
    }

    public function showResetPassword(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            include VIEWS_PATH_TRIMMED . '/errors/400.php';
            exit;
        }

        // Rate limiting: max 32 attempts per hour per IP to prevent DB spam
        $this->rateLimit("show_pwd_reset", 32);

        $member = $this->memberModel->findByEmailToken($token, 'reset_password');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Invalid or expired reset link.';
            include VIEWS_PATH_TRIMMED . '/errors/general.php';
            exit;
        }

        include VIEWS_PATH_TRIMMED . '/auth/reset_password.php';
    }

    public function processResetPassword(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        // Rate limiting: max 8 attempts per hour per IP to prevent CPU spam
        $this->rateLimit("process_pwd_reset", 8);

        $token = $postData['token'] ?? '';
        $password = $postData['password'] ?? '';
        $confirmPassword = $postData['confirm_password'] ?? '';

        if (empty($token) || empty($password)) {
            $_SESSION['error_message'] = 'All fields are required.';
            header('Location: /reset-password?token=' . $token);
            exit;
        }

        if ($password !== $confirmPassword) {
            $_SESSION['error_message'] = 'Passwords do not match.';
            header('Location: /reset-password?token=' . $token);
            exit;
        }

        if (!Member::validatePassword($password)) {
            $_SESSION['error_message'] = 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.';
            header('Location: /reset-password?token=' . $token);
            exit;
        }

        $member = $this->memberModel->findByEmailToken($token, 'reset_password');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Invalid or expired reset link.';
            include VIEWS_PATH_TRIMMED . '/errors/general.php';
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->memberModel->updatePassword((int)$member['mID'], $hashedPassword);

        $this->memberModel->deleteEmailToken((int)$member['mID'], 'reset_password');
        $this->memberModel->setEmailVerified((int)$member['mID'], true);

        $this->startSession($member);

        $_SESSION['success_message'] = 'Password reset successful!';
        header('Location: /');
        exit;
    }

    // Set access rate limits by IP or email
    public function rateLimit(string $actionHeader, int $maxAttempts=32, string $email=''): void
    {
        if (empty($email)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; $header = $actionHeader . "_ip_{$ip}"; $errmess = "from this device";
        } else {
            $header = $actionHeader . "_email_{$email}"; $errmess = "using this email address";
        }
        if (!RateLimiter::checkAndIncrement($header, $maxAttempts, 3600)) {
            http_response_code(429);
            $errorMessage = 'Too many attempts ' . $errmess . '. Please try again later.';
            include VIEWS_PATH_TRIMMED . '/errors/general.php';
            exit;
        }
    }

    private function startSession(array $member): void
    {
        $_SESSION['mID'] = $member['mID'];
        $_SESSION['email'] = $member['email'];
        $_SESSION['display_name'] = $member['display_name'];
        $_SESSION['pub_name'] = $member['pub_name'];
        $_SESSION['core_id'] = $this->memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
        $_SESSION['mrole'] = $member['mrole'];
        $_SESSION['admin_role'] = $member['admin_role'];

        $this->memberModel->updateLastLogin((int)$_SESSION['mID']);
    }
}
