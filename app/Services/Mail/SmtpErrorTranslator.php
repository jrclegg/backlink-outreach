<?php

namespace App\Services\Mail;

use Exception;

class SmtpErrorTranslator
{
    public static function translate(Exception $exception): string
    {
        $message = $exception->getMessage();

        // Authentication errors
        if (strpos($message, '535') !== false || strpos($message, 'Authentication failed') !== false) {
            return 'Check your username/password — Gmail/Outlook usually need an app-specific password, not your normal login.';
        }

        // Connection errors
        if (strpos($message, 'Could not connect') !== false || strpos($message, 'Connection refused') !== false) {
            return 'Could not connect to the mail server. Check your host, port, and encryption settings.';
        }

        // Timeout errors
        if (strpos($message, 'timeout') !== false || strpos($message, 'timed out') !== false) {
            return 'Connection timed out. The mail server may be offline or blocking connections.';
        }

        // DNS errors
        if (strpos($message, 'getaddrinfo failed') !== false || strpos($message, 'Name resolution failed') !== false) {
            return 'Could not resolve the mail server hostname. Check the SMTP host setting.';
        }

        // TLS errors
        if (strpos($message, 'TLS') !== false || strpos($message, 'SSL') !== false) {
            return 'TLS/SSL error. Check that your encryption method matches the server requirements.';
        }

        // Generic fallback
        return 'SMTP error: ' . substr($message, 0, 100);
    }
}
