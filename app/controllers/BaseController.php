<?php
declare(strict_types=1);

namespace app\controllers;

/**
 * BaseController
 * * Provides common utility methods (CSRF, Auth checks, responses) for all controllers.
 */
abstract class BaseController
{
    public function __construct()
    {
        // Always ensure a CSRF token exists for the current session
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    // ─────────────────────────────────────────────
    // SECURITY HELPER METHODS
    // ─────────────────────────────────────────────

    /**
     * Validates the CSRF token from a form submission.
     * Kills the script if validation fails.
     */
    protected function validateCsrf(array $postData): void
    {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($postData['csrf_token'] ?? ''))) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }
    }

    // Rate limiting: max access attempts per hour per IP or Email
    protected function rateLimit(string $actionHeader, int $maxAttempts = 32, string $email = '', int $timeWindowSeconds = 3600): void
    {
        if (empty($email)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown'; 
            $header = $actionHeader . "_ip_{$ip}"; 
            $errmess = "from this device";
        } else {
            $header = $actionHeader . "_email_{$email}"; 
            $errmess = "using this email address";
        }

        if (!\app\models\RateLimiter::checkAndIncrement($header, $maxAttempts, $timeWindowSeconds)) {
            http_response_code(429);
            $this->render('errors/general.php', [
                'errorMessage' => 'Too many attempts ' . $errmess . '. Please try again later.'
            ]);
            exit;
        }
    }

    // ─────────────────────────────────────────────
    // AUTHENTICATION & SESSION HELPER METHODS
    // ─────────────────────────────────────────────

    /**
     * Gets the currently logged-in user's ID.
     */
    protected function getCurrentUserId(): int
    {
        return (int)($_SESSION['mID'] ?? 0);
    }

    /**
     * Gets the currently logged-in user's role, or GUEST_ROLE if not logged in.
     */
    protected function getCurrentUserRole(): int
    {
        // Assuming GUEST_ROLE is a defined constant (e.g., in your config)
        return (int)($_SESSION['mrole'] ?? GUEST_ROLE);
    }

    /**
     * Enforces that a user must be logged in to proceed.
     * Redirects to login if they are not.
     * * @return int The logged-in member's ID
     */
    protected function requireLogin(): int
    {
        $mID = $this->getCurrentUserId();
        if ($mID === 0) {
            // Optional: Store the intended URL so you can redirect them back after login
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            header('Location: /login');
            exit;
        }
        return $mID;
    }

    // require a member in good standing for special tasks like upload/edit/revise
    protected function requireGoodStanding(): int
    {
        $mID = $this->requireLogin(); // Ensure they are logged in first
        if (empty($_SESSION['is_good'])) {
            http_response_code(401);
            $this->render('errors/general.php', [
                'errorMessage' => 'The action is not allowed for a member who is not in good standing.'
            ]);
            exit;
        }
        return $mID;
    }

    // The first line of every admin controller method should be: $mID = $this->requireAdmin();
    protected function requireAdmin(int $minRole = ADMIN_ROLE_MIN): int
    {
        $mID = $this->requireLogin(); // Ensure they are logged in first

        if (empty($_SESSION['admin_role']) || (int)$_SESSION['admin_role'] < $minRole) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }
        return $mID;
    }

	/**
	 * Safely redirects the user to their intended destination or a default URL.
	 * Automatically clears the redirect session variable and prevents Open Redirect attacks.
	 */
	protected function safeRedirect(string $defaultUrl = '/'): void
	{
		$redirectUrl = $defaultUrl;

		// 1. Check if we have a saved destination
		if (!empty($_SESSION['redirect_after_login'])) {
			$redirectUrl = $_SESSION['redirect_after_login'];

			// Clear it so it isn't used again randomly later!
			unset($_SESSION['redirect_after_login']);
		}

		// 2. SECURITY CHECK: Prevent "Open Redirect" Vulnerability
		// Make sure the URL starts with a single '/' (local path) and not 'http' or '//'
		if (strpos($redirectUrl, '/') !== 0 || strpos($redirectUrl, '//') === 0) {
			$redirectUrl = $defaultUrl;
		}

		// 3. Execute redirect
		header('Location: ' . $redirectUrl);
		exit;
	}

    // ─────────────────────────────────────────────
    // RESPONSE HELPER METHODS
    // ─────────────────────────────────────────────

    /**
     * Sets a temporary flash message in the session to be displayed after a redirect.
     *
     * @param string $type The type of message ('error', 'success', 'info')
     * @param string $message The message text
     */
    protected function setFlash(string $type, string $message): void
    {
        // Ensure the flash array exists in the session
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Retrieves a flash message by type and immediately deletes it from the session.
     *
     * @param string $type The type of message ('error', 'success', 'info')
     * @return string|null The message text, or null if none exists
     */
    protected function getFlash(string $type): ?string
    {
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]); // Automatically clear it!
            return $message;
        }
        
        return null;
    }

    /**
     * Standardizes rendering views and passing data to them.
     */
    protected function render(string $viewPath, array $data = []): void
    {
        // Automatically grab any pending flash messages
        $data['flash_error'] = $this->getFlash('error');
        $data['flash_success'] = $this->getFlash('success');

        // Extract array keys into variables (e.g., ['title' => 'Home'] becomes $title)
        extract($data);
        
        require VIEWS_PATH_TRIMMED . '/' . ltrim($viewPath, '/');
    }

    /**
     * Standardizes JSON responses for AJAX/API endpoints (used heavily in DocController).
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Serve a file with appropriate headers.
     */
    protected function serveFile(string $filePath, string $contentType): void
    {
        header("Content-Type: $contentType");
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }
}
