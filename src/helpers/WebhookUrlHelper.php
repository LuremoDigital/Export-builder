<?php

declare(strict_types=1);

namespace Luremo\DataExportBuilder\helpers;

use InvalidArgumentException;

final class WebhookUrlHelper
{
    public static function assertValidConfiguration(string $url): void
    {
        $parts = parse_url($url);
        if (
            $parts === false ||
            ($parts['scheme'] ?? null) !== 'https' ||
            !isset($parts['host']) ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            !filter_var($url, FILTER_VALIDATE_URL)
        ) {
            throw new InvalidArgumentException('Webhook URLs must be valid HTTPS URLs without embedded credentials.');
        }
    }

    /**
     * @param null|callable(string):list<string> $resolver
     */
    public static function assertPublicDestination(string $url, ?callable $resolver = null): void
    {
        self::resolvePublicDestination($url, $resolver);
    }

    /**
     * @param null|callable(string):list<string> $resolver
     * @return array{host:string,port:int,address:string}
     */
    public static function resolvePublicDestination(string $url, ?callable $resolver = null): array
    {
        self::assertValidConfiguration($url);
        $parts = parse_url($url);
        $host = (string)$parts['host'];
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        $addresses = $resolver ? $resolver($host) : self::resolve($host);

        if ($addresses === [] || array_filter($addresses, static fn(string $address): bool => !self::isPublicIp($address)) !== []) {
            throw new InvalidArgumentException('Webhook URLs must resolve only to public IP addresses.');
        }

        return ['host' => $host, 'port' => $port, 'address' => $addresses[0]];
    }

    public static function buildSignature(string $timestamp, string $payload, string $filePath, string $secret): string
    {
        $fileHash = hash_file('sha256', $filePath);
        if ($fileHash === false) {
            throw new InvalidArgumentException('Could not hash webhook export file.');
        }

        return 'v1=' . hash_hmac('sha256', $timestamp . '.' . $payload . '.' . $fileHash, $secret);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(static function (array $record): ?string {
            return isset($record['ip']) ? (string)$record['ip'] : (isset($record['ipv6']) ? (string)$record['ipv6'] : null);
        }, $records)));
    }

    private static function isPublicIp(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
