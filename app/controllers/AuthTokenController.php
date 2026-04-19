<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\AuthService;

/**
 * AuthTokenController
 * 
 * Logic for verify_email, password_forgot, and password_reset.
 */
class AuthTokenController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }
}
