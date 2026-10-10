<?php

namespace Tests\Unit;

use App\Services\Mail\MailProviderDetector;
use PHPUnit\Framework\TestCase;

class MailProviderDetectorTest extends TestCase
{
    private function detector(array $mx): MailProviderDetector
    {
        return new class($mx) extends MailProviderDetector
        {
            public function __construct(private array $records) {}

            protected function mxRecords(string $domain): array
            {
                return $this->records;
            }
        };
    }

    public function test_standard_addresses_need_no_dns_lookup(): void
    {
        $detector = new class extends MailProviderDetector
        {
            protected function mxRecords(string $domain): array
            {
                throw new \RuntimeException('DNS must not be needed');
            }
        };
        foreach (['USER@GMAIL.COM' => 'gmail', 'person@yahoo.co.uk' => 'yahoo', 'user@ymail.com' => 'yahoo', 'user@zoho.com' => 'zoho'] as $email => $expected) {
            $this->assertSame($expected, $detector->detect($email));
        }
    }

    public function test_custom_domains_resolve_to_trusted_provider_presets(): void
    {
        foreach ([
            'mx00.ionos.co.uk.' => 'ionos_uk', 'mx01.1and1.co.uk' => 'ionos_uk', 'mx00.perfora.net' => 'ionos',
            'ASPMX.L.GOOGLE.COM.' => 'gmail', 'alt2.aspmx.l.google.com' => 'gmail', 'smtp.google.com' => 'gmail',
            'mx.zoho.com' => 'zoho_pro', 'mx2.zoho.eu' => 'zoho_eu', 'mx3.zoho.com.au' => 'zoho_au',
        ] as $host => $expected) {
            $this->assertSame($expected, $this->detector([$host])->detect('owner@mywebsite.test'));
        }
    }

    public function test_unknown_mixed_and_lookalike_hosts_never_receive_credentials(): void
    {
        foreach ([[], ['mx.other.test'], ['smtp.google.com.attacker.test'], ['fakezoho.com'], ['mx.zoho.eu', 'smtp.google.com'], ['smtp.google.com', 'gateway.other.test']] as $mx) {
            $this->assertNull($this->detector($mx)->detect('owner@mywebsite.test'));
        }
    }
}
