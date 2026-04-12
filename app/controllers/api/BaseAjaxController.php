<?php

declare(strict_types=1);

namespace app\controllers\api;

use app\controllers\BaseController;

/**
 * Base Controller specifically for AJAX/API endpoints.
 * Ensures all errors (Auth, CSRF) return JSON instead of HTML views.
 */
abstract class BaseAjaxController extends BaseController
{
    /**
     * Override validateCsrf to return a JSON error on failure.
     */
    protected function validateCsrf(array $postData): void
    {
        // AJAX requests usually pass the token in headers or a JSON payload
        $token = $postData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $this->jsonResponse(['error' => 'Invalid or missing CSRF token.'], 403);
            exit;
        }
    }

    /**
     * Override requireLogin (if you have one) to return JSON.
     */
    protected function requireLogin(): int
    {
        if (empty($_SESSION['mID'])) {
            $this->jsonResponse(['error' => 'Authentication required.'], 401);
            exit;
        }
        return (int)$_SESSION['mID'];
    }
}
