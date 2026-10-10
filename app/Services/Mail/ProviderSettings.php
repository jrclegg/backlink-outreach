<?php

namespace App\Services\Mail;

class ProviderSettings
{
    public static function all(): array
    {
        return [
            'ionos_uk' => ['label' => 'IONOS UK', 'smtp' => 'smtp.ionos.co.uk', 'imap' => 'imap.ionos.co.uk'],
            'ionos' => ['label' => 'IONOS US', 'smtp' => 'smtp.ionos.com', 'imap' => 'imap.ionos.com'],
            'ionos_de' => ['label' => 'IONOS Germany', 'smtp' => 'smtp.ionos.de', 'imap' => 'imap.ionos.de'],
            'gmail' => ['label' => 'Gmail / Google Workspace', 'smtp' => 'smtp.gmail.com', 'imap' => 'imap.gmail.com'],
            'yahoo' => ['label' => 'Yahoo', 'smtp' => 'smtp.mail.yahoo.com', 'imap' => 'imap.mail.yahoo.com'],
            'zoho' => ['label' => 'Zoho personal (US)', 'smtp' => 'smtp.zoho.com', 'imap' => 'imap.zoho.com'],
            'zoho_pro' => ['label' => 'Zoho custom domain (US)', 'smtp' => 'smtppro.zoho.com', 'imap' => 'imappro.zoho.com'],
            'zoho_eu' => ['label' => 'Zoho custom domain (EU)', 'smtp' => 'smtppro.zoho.eu', 'imap' => 'imappro.zoho.eu'],
            'zoho_in' => ['label' => 'Zoho custom domain (India)', 'smtp' => 'smtppro.zoho.in', 'imap' => 'imappro.zoho.in'],
            'zoho_au' => ['label' => 'Zoho custom domain (Australia)', 'smtp' => 'smtppro.zoho.com.au', 'imap' => 'imappro.zoho.com.au'],
            'zoho_jp' => ['label' => 'Zoho custom domain (Japan)', 'smtp' => 'smtppro.zoho.jp', 'imap' => 'imappro.zoho.jp'],
            'zoho_ca' => ['label' => 'Zoho custom domain (Canada)', 'smtp' => 'smtppro.zoho.ca', 'imap' => 'imappro.zoho.ca'],
            'zoho_cn' => ['label' => 'Zoho custom domain (China)', 'smtp' => 'smtppro.zoho.com.cn', 'imap' => 'imappro.zoho.com.cn'],
            'zoho_sa' => ['label' => 'Zoho custom domain (Saudi Arabia)', 'smtp' => 'smtppro.zoho.sa', 'imap' => 'imappro.zoho.sa'],
            'custom' => ['label' => 'Custom / another region', 'smtp' => '', 'imap' => ''],
        ];
    }

    public static function help(string $provider): array
    {
        if ($provider === 'gmail') {
            return [
                'label' => 'Google app password',
                'instruction' => 'Create a Google app password for this app, then paste it below. Your usual Google password will not work.',
                'url' => 'https://myaccount.google.com/apppasswords',
                'failure' => 'Check your Google app password. Your account needs to allow app passwords and IMAP access; if app passwords are unavailable, this connection method cannot be used.',
            ];
        }
        if ($provider === 'yahoo') {
            return [
                'label' => 'Yahoo app password',
                'instruction' => 'In Yahoo Account Security, generate an app password and paste it below. Use the app password, not your usual Yahoo password.',
                'url' => 'https://login.yahoo.com/account/security',
                'failure' => 'Check your Yahoo app password. If Yahoo does not allow you to generate one, this connection method cannot be used.',
            ];
        }
        if (str_starts_with($provider, 'zoho')) {
            return [
                'label' => 'Zoho password / app password',
                'instruction' => 'Use your Zoho mailbox password, or an app password if two-factor authentication is enabled. Your Zoho account must allow IMAP access.',
                'url' => 'https://www.zoho.com/mail/help/imap-access.html',
                'failure' => 'Check your Zoho credentials and make sure IMAP access is enabled on your account and plan. If needed, choose the correct Zoho region under “Different provider?”.',
            ];
        }

        return [
            'label' => 'Mailbox password',
            'instruction' => 'Enter the password you use to sign in to this email mailbox.',
            'url' => null,
            'failure' => 'Check your mailbox password and try again. If it still fails, confirm your email provider using “Different provider?”.',
        ];
    }
}
