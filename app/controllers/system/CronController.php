<?php

declare(strict_types=1);

namespace app\controllers\system;

use app\models\RateLimiter;
use app\models\system\CronService;

class CronController
{
    public function run(): void
    {
        // 1. Early exit if no token provided
        if (empty($_GET['token'])) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        // 2. Securely compare the provided token to the config constant
        if (!hash_equals(CRON_SECRET_TOKEN, $_GET['token'])) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        // 3. Functional Rate Limit: Only allow this script to run ONCE per minute maximum!
        // Notice we don't append an IP address to the key. It's a global lock.
        if (!RateLimiter::checkAndIncrement('global_cron_execution', 1, 60)) {
            die("Cron already ran less than a minute ago. Skipping to prevent overlap.");
        }

        // 3. FastCGI trick: Send immediate success response to the authorized caller
        if (function_exists('fastcgi_finish_request')) {
            echo "Cron triggered securely.";
            fastcgi_finish_request();
        }

        // 4. Run the tasks
        (new CronService())->runDueTasks();
    }
}
