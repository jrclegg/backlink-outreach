<?php

namespace Tests\Unit;

use App\Services\Mail\SmtpErrorTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

class SmtpErrorTranslatorTest extends TestCase
{
    public function test_yahoo_alias_rejection_explains_the_actual_restriction(): void
    {
        $error = new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 No MIME From header matches auth mailboxes".');
        $this->assertStringContainsString('not an allowed sender', SmtpErrorTranslator::translate($error));
        $this->assertStringContainsString('over SMTP', SmtpErrorTranslator::translate($error));
        $this->assertSame('550 No MIME From header matches auth mailboxes', SmtpErrorTranslator::diagnostics($error)['server_response']);
    }

    public function test_server_reply_redacts_credentials_and_message_content(): void
    {
        $error = new UnexpectedResponseException('Expected response code "250" but got code "550", with message "550 rejected my-password '.base64_encode('my-password').' confidential-subject confidential-body user@example.org https://example.org/private".');
        $summary = SmtpErrorTranslator::diagnosticSummary($error, ['my-password', 'confidential-subject', 'confidential-body']);
        foreach (['my-password', base64_encode('my-password'), 'confidential-subject', 'confidential-body', 'user@example.org', 'https://example.org/private'] as $secret) {
            $this->assertStringNotContainsString($secret, $summary);
        }
        $this->assertStringContainsString('550 rejected', $summary);
    }

    public function test_smtp_diagnostics_preserve_codes_and_stage_without_raw_data(): void
    {
        $error = new UnexpectedResponseException('Expected response code "250" but got code "553", with message "553 5.7.1 (#SMTP123) secret-password private@example.org".');
        $error->appendDebug("[time] > EHLO localhost\n[time] > AUTH LOGIN\n[time] > secret-password\n[time] > MAIL FROM:<private@example.org>\n[time] < 553 rejected\n[time] > QUIT\n");
        $details = SmtpErrorTranslator::diagnostics($error, ['secret-password']);
        $this->assertSame('553', $details['smtp_code']);
        $this->assertSame('5.7.1', $details['enhanced_code']);
        $this->assertSame('SMTP123', $details['provider_code']);
        $this->assertSame('sender', $details['stage']);
        $summary = SmtpErrorTranslator::diagnosticSummary($error, ['secret-password']);
        $this->assertStringContainsString('SMTP: 553', $summary);
        $this->assertStringNotContainsString('secret-password', json_encode($details).$summary);
        $this->assertStringNotContainsString('private@example.org', json_encode($details).$summary);
    }

    public function test_message_body_cannot_change_the_reported_smtp_stage(): void
    {
        $error = new UnexpectedResponseException('Expected response code "250" but got code "554", with message "554 5.7.1 policy".');
        $error->appendDebug("[time] > MAIL FROM:<owner@example.org>\n[time] > RCPT TO:<editor@example.org>\n[time] > DATA\n[time] > AUTH body-content\n[time] > QUIT\n");
        $this->assertSame('message acceptance', SmtpErrorTranslator::diagnostics($error)['stage']);
        $this->assertStringContainsString('security or sending policy', SmtpErrorTranslator::translate($error));
    }

    public function test_unknown_errors_do_not_invent_an_smtp_code(): void
    {
        $details = SmtpErrorTranslator::diagnostics(new \RuntimeException('private data'));
        $this->assertSame('unknown', $details['stage']);
        $this->assertArrayNotHasKey('smtp_code', $details);
    }

    public function test_provider_errors_are_classified_without_exposing_raw_secrets(): void
    {
        $cases = [
            '535 Authentication failed secret-password' => 'Authentication failed',
            '553 sender address rejected secret-password' => 'sender address',
            '550 Invalid mail from secret-password' => 'sender address',
            '550 recipient rejected secret-password' => 'recipient',
            'Connection refused secret-password' => 'connect',
            'Unexpected secret-password' => 'unexpected',
        ];
        foreach ($cases as $raw => $expected) {
            $result = SmtpErrorTranslator::translate(new \RuntimeException($raw));
            $this->assertStringContainsString($expected, $result);
            $this->assertStringNotContainsString('secret-password', $result);
        }
        $this->assertStringNotContainsString('secret-password', SmtpErrorTranslator::translate(new \Error('secret-password')));
    }
}
