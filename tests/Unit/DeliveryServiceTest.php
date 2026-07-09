<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\Tests\Unit;

use Luremo\DataExportBuilder\services\DeliveryService;
use PHPUnit\Framework\TestCase;
use yii\base\Exception;

final class DeliveryServiceTest extends TestCase
{
    public function testWebhookRedirectResponsesFailDelivery(): void
    {
        $method = new \ReflectionMethod(DeliveryService::class, 'assertSuccessfulWebhookStatus');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Webhook responded with HTTP 302.');
        $method->invoke(null, 302);
    }

    public function testSuccessfulWebhookResponsesAreAccepted(): void
    {
        $method = new \ReflectionMethod(DeliveryService::class, 'assertSuccessfulWebhookStatus');

        $method->invoke(null, 204);

        self::addToAssertionCount(1);
    }
}
