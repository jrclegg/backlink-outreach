<?php

namespace App\Services\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

class SmtpErrorTranslator
{
    public static function diagnostics(Throwable $exception, array $sensitiveValues = []): array
    {
        $message = $exception->getMessage();
        $code = null;
        if (preg_match('/but got code "([245]\d{2})"/', $message, $match)) {
            $code = $match[1];
        } elseif (preg_match('/\b([245]\d{2})[ -]([245]\.\d{1,3}\.\d{1,3})\b/', $message, $match)) {
            $code = $match[1];
        }
        preg_match('/\b([245]\.\d{1,3}\.\d{1,3})\b/', $message, $enhanced);
        preg_match('/\(#((?:AUTH|SMTP|TS|TSS)\d{1,4})\)/i', $message, $provider);
        $stage = 'unknown';
        if ($exception instanceof TransportExceptionInterface) {
            // Extract command names only. Never retain AUTH payloads or message DATA.
            foreach (explode("\n", $exception->getDebug()) as $line) {
                if (preg_match('/\] > (EHLO|HELO|STARTTLS|AUTH|MAIL FROM|RCPT TO|DATA)\b/', $line, $command)) {
                    $stage = match ($command[1]) {
                        'EHLO', 'HELO' => 'greeting',
                        'STARTTLS' => 'tls',
                        'AUTH' => 'authentication',
                        'MAIL FROM' => 'sender',
                        'RCPT TO' => 'recipient',
                        'DATA' => 'message acceptance',
                    };
                    if ($command[1] === 'DATA') {
                        break;
                    }
                }
            }
        }

        $response = null;
        if ($exception instanceof UnexpectedResponseException && preg_match('/, with message "(.*)"\.\s*$/s', $message, $match)) {
            $response = $match[1];
            $redactions = [];
            foreach ($sensitiveValues as $value) {
                if (is_string($value) && $value !== '') {
                    $redactions[] = $value;
                    $redactions[] = base64_encode($value);
                }
            }
            usort($redactions, fn ($a, $b) => strlen($b) <=> strlen($a));
            $response = str_replace($redactions, '[redacted]', $response);
            $response = preg_replace('/[A-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Z0-9.-]+/i', '[address]', $response);
            $response = preg_replace('/https?:\/\/\S+/i', '[link]', $response);
            $response = preg_replace('/[A-Za-z0-9+\/_=-]{32,}/', '[token]', $response);
            $response = substr(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $response), 0, 500);
        }

        return array_filter([
            'smtp_code' => $code,
            'enhanced_code' => $enhanced[1] ?? null,
            'provider_code' => isset($provider[1]) ? strtoupper($provider[1]) : null,
            'stage' => $stage,
            'server_response' => $response,
            'exception_type' => get_class($exception),
        ], fn ($value) => $value !== null);
    }

    public static function diagnosticSummary(Throwable $exception, array $sensitiveValues = []): string
    {
        $details = self::diagnostics($exception, $sensitiveValues);
        $parts = ['Stage: '.$details['stage']];
        foreach (['smtp_code' => 'SMTP', 'enhanced_code' => 'Status', 'provider_code' => 'Provider code'] as $key => $label) {
            if (isset($details[$key])) {
                $parts[] = $label.': '.$details[$key];
            }
        }
        if (isset($details['server_response'])) {
            $parts[] = 'Server reply: '.$details['server_response'];
        }
        $parts[] = 'Error type: '.basename(str_replace('\\', '/', $details['exception_type']));

        return implode('; ', $parts).'.';
    }

    public static function translate(Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Never display raw SMTP errors: they can include credentials or email content.
        if (stripos($message, 'No MIME From header matches auth mailboxes') !== false) {
            return 'Yahoo rejected the From address because it is not an allowed sender for this mailbox over SMTP. Check that the alias is verified in this exact Yahoo account. If it works in Yahoo webmail but SMTP still rejects it, connect the alias domain’s outgoing mail provider. The app cannot override Yahoo’s sender restriction.';
        }
        if (preg_match('/sender|send as|from address|invalid mail from/i', $message)) {
            return 'The sending server rejected the sender address. Check that your Send as alias is verified and allowed by your provider.';
        }
        if (preg_match('/recipient|mailbox unavailable|user unknown/i', $message)) {
            return 'The sending server rejected the recipient. Check the destination email address.';
        }

        // Authentication errors
        if (strpos($message, '535') !== false || strpos($message, 'Authentication failed') !== false) {
            return 'Authentication failed. Check your email and password. Some providers (Gmail, Outlook, Yahoo) require an app-specific password instead of your regular password. For other providers, verify you\'re using the correct credentials.';
        }

        // Socket connection errors (wrong host/port or host doesn't exist)
        if (strpos($message, 'stream_socket_') !== false || strpos($message, 'Connection could not be established') !== false) {
            return 'Could not connect to the mail server. For custom domains, use the "Advanced" section to enter your provider\'s SMTP host (e.g., smtp.ionos.com for IONOS).';
        }

        // Connection errors
        if (stripos($message, 'connection lost') !== false || stripos($message, 'connection reset') !== false) {
            return 'The connection to the sending server was interrupted. Delivery could not be confirmed.';
        }
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

        if (preg_match('/rate limit|too many|temporarily deferred|\b(?:421|450|451|452)\b/i', $message)) {
            return 'The sending server temporarily refused the email. Check account sending limits and provider availability.';
        }
        if (preg_match('/spam|policy|not permitted|not allowed|\b5\.7\.1\b/i', $message)) {
            return 'The sending server rejected the email under its security or sending policy. Check alias permission and domain authentication.';
        }

        // Generic fallback
        return 'The sending server rejected the email or an unexpected sending error occurred. Check your provider’s sending restrictions and whether your alias is allowed.';
    }
}
