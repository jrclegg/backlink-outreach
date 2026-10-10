<?php

namespace App\Services\Mail;

class MailProviderDetector
{
    public function detect(string $email): ?string
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if (! $domain || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $direct = [
            'gmail.com' => 'gmail', 'googlemail.com' => 'gmail',
            'yahoo.com' => 'yahoo', 'yahoo.co.uk' => 'yahoo', 'yahoo.fr' => 'yahoo', 'yahoo.de' => 'yahoo',
            'yahoo.ca' => 'yahoo', 'yahoo.com.au' => 'yahoo', 'ymail.com' => 'yahoo', 'rocketmail.com' => 'yahoo',
            'zoho.com' => 'zoho', 'zohomail.com' => 'zoho',
        ];
        if (isset($direct[$domain])) {
            return $direct[$domain];
        }
        $matches = [];
        foreach ($this->mxRecords($domain) as $host) {
            $provider = $this->providerForHost(strtolower(rtrim($host, '.')));
            if ($provider) {
                $matches[$provider] = true;
            } else {
                return null;
            } // Mixed providers or a forwarding gateway: ask, never guess.
        }

        return count($matches) === 1 ? array_key_first($matches) : null;
    }

    protected function mxRecords(string $domain): array
    {
        // MX is a discovery hint only. Credentials go exclusively to our trusted presets.
        try {
            return array_column(@dns_get_record($domain, DNS_MX) ?: [], 'target');
        } catch (\Throwable) {
            return [];
        }
    }

    private function providerForHost(string $host): ?string
    {
        if (preg_match('/^(?:aspmx|alt[1-4]\.aspmx)\.l\.google\.com$|^smtp\.google\.com$/', $host)) {
            return 'gmail';
        }
        if (preg_match('/^(?:mx\d+\.)?(?:ionos|1and1)\.co\.uk$/', $host)) {
            return 'ionos_uk';
        }
        if (preg_match('/^mx\d+\.(?:ionos|1and1)\.com$/', $host) || preg_match('/^mx\d+\.perfora\.net$/', $host)) {
            return 'ionos';
        }
        if (preg_match('/^mx\d+\.(?:ionos\.de|kundenserver\.de)$/', $host)) {
            return 'ionos_de';
        }
        if (preg_match('/^mx\d*\.zoho\.(com|eu|in|com\.au|jp|ca|com\.cn|sa)$/', $host, $m)) {
            return ['com' => 'zoho_pro', 'eu' => 'zoho_eu', 'in' => 'zoho_in', 'com.au' => 'zoho_au', 'jp' => 'zoho_jp', 'ca' => 'zoho_ca', 'com.cn' => 'zoho_cn', 'sa' => 'zoho_sa'][$m[1]];
        }

        // Yahoo custom-domain/business mail may be a separate service: do not guess.
        return null;
    }
}
