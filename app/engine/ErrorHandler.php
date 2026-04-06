<?php

declare(strict_types=1);

namespace app\engine;

use Throwable;
use ErrorException;

/**
 * ErrorHandler
 * 
 * Centralized error and exception handling for the application.
 */
class ErrorHandler
{
    /**
     * Handle uncaught exceptions.
     *
     * @param Throwable $exception
     * @return void
     */
    public static function handleException(Throwable $exception): void
    {
        $date = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] PHP Fatal error: Uncaught %s: %s in %s:%d\nStack trace:\n%s\n",
            $date,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Securely log the error to the log file defined in config
        error_log($message, 3, LOG_PATH_TRIMMED . '/error.log');

        if (ini_get('display_errors') === '1' || strtolower(ini_get('display_errors')) === 'on') {
            // Display full error details for development
            echo "<h1>Uncaught Exception</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<h2>Stack Trace:</h2>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        } else {
            // Display a generic error message for production
            http_response_code(500);
            echo "<!DOCTYPE html>
            <html lang=\"en\">
            <head>
                <meta charset=\"UTF-8\">
                <title>500 Internal Server Error</title>
                <style>
                    body { font-family: sans-serif; text-align: center; padding: 50px; color: #333; }
                    h1 { font-size: 40px; }
                    p { font-size: 20px; }
                </style>
            </head>
            <body>
                <h1>500 Internal Server Error</h1>
                <p>Something went wrong on our end. Please try again later.</p>
            </body>
            </html>";
        }
        exit;
    }

    /**
     * Convert PHP errors into ErrorExceptions.
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @return void
     * @throws ErrorException
     */
    public static function handleError(int $level, string $message, string $file, int $line): void
    {
        if (!(error_reporting() & $level)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new ErrorException($message, 0, $level, $file, $line);
    }
}
