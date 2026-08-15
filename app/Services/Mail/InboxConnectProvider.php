<?php

namespace App\Services\Mail;

use App\Models\EmailInbox;
use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class InboxConnectProvider
{
    private ?Google_Client $googleClient = null;

    public function __construct()
    {
        $this->initializeGoogleClient();
    }

    private function initializeGoogleClient(): void
    {
        $this->googleClient = new Google_Client();
        $this->googleClient->setClientId(config('services.google.client_id'));
        $this->googleClient->setClientSecret(config('services.google.client_secret'));
        $this->googleClient->setRedirectUri(config('services.google.redirect_uri'));
        $this->googleClient->setScopes([
            'https://www.googleapis.com/auth/gmail.modify',
            'https://mail.google.com/',
        ]);
        $this->googleClient->setAccessType('offline');
        $this->googleClient->setPrompt('consent');
    }

    public function getGoogleAuthUrl(): string
    {
        return $this->googleClient->createAuthUrl();
    }

    public function handleGoogleCallback(string $code, string $organizationId, string $name, string $fromName): EmailInbox
    {
        $token = $this->googleClient->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception('Failed to fetch Google token: ' . $token['error']);
        }

        $this->googleClient->setAccessToken($token);

        $gmail = new Google_Service_Gmail($this->googleClient);
        $profile = $gmail->users->getProfile('me');
        $email = $profile->getEmailAddress();

        $expiresAt = null;
        if (isset($token['expires_in'])) {
            $expiresAt = Carbon::now()->addSeconds($token['expires_in']);
        }

        $inbox = EmailInbox::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'email_address' => $email,
            'from_name' => $fromName,
            'oauth_provider' => 'gmail',
            'oauth_access_token' => $token['access_token'],
            'oauth_refresh_token' => $token['refresh_token'] ?? null,
            'oauth_expires_at' => $expiresAt,
            'last_verified_at' => now(),
            // Gmail defaults
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => $email,
            'smtp_password' => '[oauth_token]',
            'smtp_encryption' => 'tls',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_username' => $email,
            'imap_password' => '[oauth_token]',
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'max_sends_per_hour' => 20,
            'is_active' => true,
        ]);

        return $inbox;
    }

    public function getOutlookAuthUrl(): string
    {
        $clientId = config('services.microsoft.client_id');
        $redirectUri = config('services.microsoft.redirect_uri');
        $scopes = 'offline_access%20Mail.ReadWrite%20Mail.Send';

        return "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'response_mode' => 'query',
        ]);
    }

    public function handleOutlookCallback(string $code, string $organizationId, string $name, string $fromName): EmailInbox
    {
        $clientId = config('services.microsoft.client_id');
        $clientSecret = config('services.microsoft.client_secret');
        $redirectUri = config('services.microsoft.redirect_uri');

        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Outlook token: ' . $response->body());
        }

        $token = $response->json();

        $userResponse = Http::withToken($token['access_token'])
            ->get('https://graph.microsoft.com/v1.0/me');

        if (!$userResponse->successful()) {
            throw new \Exception('Failed to fetch Outlook user info: ' . $userResponse->body());
        }

        $user = $userResponse->json();
        $email = $user['mail'] ?? $user['userPrincipalName'] ?? null;

        if (!$email) {
            throw new \Exception('Could not determine email address from Outlook');
        }

        $expiresAt = null;
        if (isset($token['expires_in'])) {
            $expiresAt = Carbon::now()->addSeconds($token['expires_in']);
        }

        $inbox = EmailInbox::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'email_address' => $email,
            'from_name' => $fromName,
            'oauth_provider' => 'outlook',
            'oauth_access_token' => $token['access_token'],
            'oauth_refresh_token' => $token['refresh_token'] ?? null,
            'oauth_expires_at' => $expiresAt,
            'last_verified_at' => now(),
            // Outlook defaults
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
            'smtp_username' => $email,
            'smtp_password' => '[oauth_token]',
            'smtp_encryption' => 'tls',
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_username' => $email,
            'imap_password' => '[oauth_token]',
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'max_sends_per_hour' => 20,
            'is_active' => true,
        ]);

        return $inbox;
    }

    public function refreshGoogleToken(EmailInbox $inbox): bool
    {
        if ($inbox->oauth_provider !== 'gmail' || !$inbox->oauth_refresh_token) {
            return false;
        }

        try {
            $this->googleClient->setAccessToken([
                'refresh_token' => $inbox->oauth_refresh_token,
            ]);

            $token = $this->googleClient->fetchAccessTokenWithRefreshToken($inbox->oauth_refresh_token);

            if (isset($token['error'])) {
                return false;
            }

            $expiresAt = null;
            if (isset($token['expires_in'])) {
                $expiresAt = Carbon::now()->addSeconds($token['expires_in']);
            }

            $inbox->update([
                'oauth_access_token' => $token['access_token'],
                'oauth_expires_at' => $expiresAt,
                'last_verified_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function refreshOutlookToken(EmailInbox $inbox): bool
    {
        if ($inbox->oauth_provider !== 'outlook' || !$inbox->oauth_refresh_token) {
            return false;
        }

        try {
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $inbox->oauth_refresh_token,
                'grant_type' => 'refresh_token',
                'scope' => 'offline_access Mail.ReadWrite Mail.Send',
            ]);

            if (!$response->successful()) {
                return false;
            }

            $token = $response->json();

            $expiresAt = null;
            if (isset($token['expires_in'])) {
                $expiresAt = Carbon::now()->addSeconds($token['expires_in']);
            }

            $inbox->update([
                'oauth_access_token' => $token['access_token'],
                'oauth_expires_at' => $expiresAt,
                'last_verified_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function ensureTokenValid(EmailInbox $inbox): bool
    {
        if (!$inbox->isOAuthConnected()) {
            return false;
        }

        if (!$inbox->isTokenExpired()) {
            return true;
        }

        // Try to refresh
        if ($inbox->oauth_provider === 'gmail') {
            return $this->refreshGoogleToken($inbox);
        } elseif ($inbox->oauth_provider === 'outlook') {
            return $this->refreshOutlookToken($inbox);
        }

        return false;
    }
}
