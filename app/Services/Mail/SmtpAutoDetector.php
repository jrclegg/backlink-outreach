<?php

namespace App\Services\Mail;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SmtpAutoDetector
{
    private ?\Exception $lastError = null;

    private const SMTP_CONFIGS = [
        'gmail.com' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'googlemail.com' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'google.com' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'outlook.com' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'hotmail.com' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'live.com' => [
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'yahoo.com' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ymail.com' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'rocketmail.com' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'aol.com' => [
            'host' => 'smtp.aol.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'protonmail.com' => [
            'host' => 'smtp.protonmail.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'proton.me' => [
            'host' => 'smtp.protonmail.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.com' => [
            'host' => 'smtp.ionos.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.co.uk' => [
            'host' => 'smtp.ionos.co.uk',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.de' => [
            'host' => 'smtp.ionos.de',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.fr' => [
            'host' => 'smtp.ionos.fr',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.es' => [
            'host' => 'smtp.ionos.es',
            'port' => 587,
            'encryption' => 'tls',
        ],
        'ionos.it' => [
            'host' => 'smtp.ionos.it',
            'port' => 587,
            'encryption' => 'tls',
        ],
        '1and1.com' => [
            'host' => 'smtp.ionos.com',
            'port' => 587,
            'encryption' => 'tls',
        ],
    ];

    private const PORTS_TO_TRY = [587, 465, 25];
    private const ENCRYPTIONS = [
        'tls' => 'smtp',
        'ssl' => 'smtps',
        'starttls' => 'smtp',  // STARTTLS is SMTP with explicit TLS upgrade
    ];

    /**
     * Detect SMTP settings based on email domain
     */
    public function detect(string $email): array
    {
        $domain = $this->extractDomain($email);

        // Check if we have a known config for this domain
        if (isset(self::SMTP_CONFIGS[$domain])) {
            return self::SMTP_CONFIGS[$domain];
        }

        // Try common SMTP patterns for the domain
        return [
            'host' => "smtp.{$domain}",
            'port' => 587,
            'encryption' => 'tls',
        ];
    }

    /**
     * Test SMTP connection with given credentials
     */
    public function testConnection(string $email, string $password, array $config): bool
    {
        $host = $config['host'] ?? null;
        $port = $config['port'] ?? 587;
        $encryption = $config['encryption'] ?? 'tls';

        if (!$host) {
            return false;
        }

        // Ports to try in order
        $ports = [$port];

        // Add fallback ports if primary fails
        if ($port !== 587) {
            $ports[] = 587;
        }
        if ($port !== 465) {
            $ports[] = 465;
        }

        foreach ($ports as $tryPort) {
            foreach (array_keys(self::ENCRYPTIONS) as $tryEncryption) {
                if ($this->tryConnection($email, $password, $host, $tryPort, $tryEncryption)) {
                    // Update config with successful settings
                    $config['port'] = $tryPort;
                    $config['encryption'] = $tryEncryption;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Try a single SMTP connection
     */
    private function tryConnection(
        string $username,
        string $password,
        string $host,
        int $port,
        string $encryption
    ): bool {
        try {
            $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
            $dsn = sprintf(
                '%s://%s:%s@%s:%d',
                $scheme,
                rawurlencode($username),
                rawurlencode($password),
                $host,
                $port
            );

            \Log::debug('SMTP Connection Attempt', [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'scheme' => $scheme,
                'username' => $username,
            ]);

            // Create transport to verify connection
            $transport = Transport::fromDsn($dsn);

            // Try to start connection
            $transport->start();
            $transport->stop();

            return true;
        } catch (TransportExceptionInterface $e) {
            \Log::error('SMTP Transport Exception', [
                'host' => $host,
                'port' => $port,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            $this->lastError = $e;
            return false;
        } catch (\Exception $e) {
            \Log::error('SMTP Connection Exception', [
                'host' => $host,
                'port' => $port,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);
            $this->lastError = $e;
            return false;
        }
    }

    /**
     * Get the last connection error (if any)
     */
    public function getLastError(): ?\Exception
    {
        return $this->lastError;
    }

    /**
     * Extract domain from email address
     */
    private function extractDomain(string $email): string
    {
        if (strpos($email, '@') === false) {
            return '';
        }

        $parts = explode('@', $email);
        return strtolower($parts[1] ?? '');
    }
}
