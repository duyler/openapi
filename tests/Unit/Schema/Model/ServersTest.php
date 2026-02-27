<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Servers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Servers::class)]
final class ServersTest extends TestCase
{
    #[Test]
    public function can_create_servers(): void
    {
        $server = new Server(
            url: 'https://api.example.com/v1',
            description: 'Production server',
        );

        $servers = new Servers(
            servers: [$server],
        );

        self::assertCount(1, $servers->servers);
        self::assertSame('https://api.example.com/v1', $servers->servers[0]->url);
    }

    #[Test]
    public function can_create_empty_servers(): void
    {
        $servers = new Servers(
            servers: [],
        );

        self::assertCount(0, $servers->servers);
    }

    #[Test]
    public function json_serialize_includes_servers(): void
    {
        $server = new Server(
            url: 'https://api.example.com/v1',
            description: 'Production server',
        );

        $servers = new Servers(
            servers: [$server],
        );

        $serialized = $servers->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('servers', $serialized);
        self::assertIsArray($serialized['servers']);
        self::assertCount(1, $serialized['servers']);
        self::assertIsArray($serialized['servers'][0]);
        self::assertArrayHasKey('url', $serialized['servers'][0]);
    }
}
