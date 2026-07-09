<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use InvalidArgumentException;
use Luremo\DataExportBuilder\helpers\WebhookUrlHelper;
use PHPUnit\Framework\TestCase;

final class WebhookUrlHelperTest extends TestCase
{
    public function testAcceptsHttpsConfigurationWithoutEmbeddedCredentials(): void
    {
        WebhookUrlHelper::assertValidConfiguration('https://hooks.example.test/export');
        self::addToAssertionCount(1);
    }

    public function testRejectsUnsafeWebhookConfiguration(): void
    {
        foreach (['http://hooks.example.test/export', 'https://user:pass@example.test/hook', 'not-a-url'] as $url) {
            try {
                WebhookUrlHelper::assertValidConfiguration($url);
                self::fail('Expected invalid webhook URL to be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsPrivateResolvedDestinations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WebhookUrlHelper::assertPublicDestination('https://hooks.example.test/export', static fn(string $host): array => ['127.0.0.1']);
    }

    public function testResolvesAndPinsAPublicDestination(): void
    {
        self::assertSame([
            'host' => 'hooks.example.test',
            'port' => 8443,
            'address' => '8.8.8.8',
        ], WebhookUrlHelper::resolvePublicDestination(
            'https://hooks.example.test:8443/export',
            static fn(string $host): array => ['8.8.8.8']
        ));
    }

    public function testSignsPayloadAndFileDigest(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'deb-webhook-');
        self::assertNotFalse($file);
        file_put_contents($file, 'export body');

        try {
            self::assertSame(
                'v1=' . hash_hmac('sha256', '1700000000.{"runId":7}.' . hash('sha256', 'export body'), 'secret'),
                WebhookUrlHelper::buildSignature('1700000000', '{"runId":7}', $file, 'secret')
            );
        } finally {
            unlink($file);
        }
    }
}
