<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\AuthTokenService;

/**
 * AuthTokenController
 * 
 * Logic for verify_email, password_forgot, and password_reset.
 */
class AuthTokenController extends BaseController
{
    private AuthTokenService $authTokenService;

    public function __construct()
    {
        parent::__construct();
        $this->authTokenService = new AuthTokenService();
    }

    public function verifyEmail(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            $this->render('errors/400.php');
            exit;
        }

        $this->rateLimit("verify_email", 32);

        $member = $this->authTokenService->findByEmailToken($token, 'verify_email');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Verification link is invalid or has expired.';
            $this->render('errors/general.php', ['errorMessage' => $errorMessage]);
            exit;
        }

        $this->authTokenService->deleteEmailToken((int)$member['mID'], 'verify_email');
        $this->authTokenService->setEmailVerified((int)$member['mID'], true);

        $this->authTokenService->startSession($member);

        header('Location: /');
        exit;
    }

    public function showForgotPassword(): void
    {
        $this->render('auth/forgot_password.php');
    }

    public function processForgotPassword(array $postData): void
    {
        $this->validateCsrf($postData);

        $this->rateLimit("pwd_forgot", 10);

        $email = trim(strtolower($postData['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = 'Please enter a valid email address.';
            $this->render('auth/forgot_password.php');
            exit;
        }

        $this->rateLimit("pwd_forgot", 3, $email);

        $member = $this->authTokenService->findUser('email', $email);

        if ($member) {
            $token = $this->authTokenService->createEmailToken((int)$member['mID'], 'reset_password');
            if ($token) {
                $resetUrl = SITE_URL . '/reset-password?token=' . $token;
                $subject = 'Reset your ' . SITE_TITLE . ' password';
                $body = "Click the link below to reset your password:\n\n$resetUrl\n\nThis link expires in 30 minutes.";
                $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                mail($email, $subject, $body, $headers);
            }
        }

        $_SESSION['success_message'] = 'If that email exists, you will receive a password reset link.';
        $this->render('auth/forgot_password.php');
    }

    public function showResetPassword(string $token): void
    {
        if (empty($token)) {
            http_response_code(400);
            $this->render('errors/400.php');
            exit;
        }

        $this->rateLimit("show_pwd_reset", 32);

        $member = $this->authTokenService->findByEmailToken($token, 'reset_password');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Invalid or expired reset link.';
            $this->render('errors/general.php', ['errorMessage' => $errorMessage]);
            exit;
        }

        $this->render('auth/reset_password.php');
    }

    public function processResetPassword(array $postData): void
    {
        $this->validateCsrf($postData);

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

        if (!AuthTokenService::validatePassword($password)) {
            $_SESSION['error_message'] = 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.';
            header('Location: /reset-password?token=' . $token);
            exit;
        }

        $member = $this->authTokenService->findByEmailToken($token, 'reset_password');

        if (!$member) {
            http_response_code(400);
            $errorMessage = 'Invalid or expired reset link.';
            $this->render('errors/general.php', ['errorMessage' => $errorMessage]);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->authTokenService->updatePassword((int)$member['mID'], $hashedPassword);

        $this->authTokenService->deleteEmailToken((int)$member['mID'], 'reset_password');
        $this->authTokenService->setEmailVerified((int)$member['mID'], true);

        $this->authTokenService->startSession($member);

        $_SESSION['success_message'] = 'Password reset successful!';
        header('Location: /');
        exit;
    }
}
