<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Server;

final class ServerTest extends TestCase
{
    #[Test]
    public function json_serialize_includes_description(): void
    {
        $server = new Server(
            url: 'https://api.example.com',
            description: 'Production API server',
        );

        $serialized = $server->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('https://api.example.com', $serialized['url']);
        self::assertSame('Production API server', $serialized['description']);
    }

    #[Test]
    public function json_serialize_includes_variables(): void
    {
        $server = new Server(
            url: 'https://{username}.example.com:{port}/api',
            variables: [
                'username' => ['default' => 'demo'],
                'port' => ['default' => '443'],
            ],
        );

        $serialized = $server->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertArrayHasKey('variables', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $server = new Server(
            url: 'https://api.example.com',
        );

        $serialized = $server->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('variables', $serialized);
    }

    #[Test]
    public function server_has_name_field(): void
    {
        $server = new Server(
            url: 'https://api.example.com',
            name: 'production',
        );

        self::assertSame('production', $server->name);
    }

    #[Test]
    public function json_serialize_includes_name(): void
    {
        $server = new Server(
            url: 'https://api.example.com',
            description: 'Production API server',
            name: 'production',
        );

        $serialized = $server->jsonSerialize();

        self::assertArrayHasKey('name', $serialized);
        self::assertSame('production', $serialized['name']);
    }

    #[Test]
    public function json_serialize_excludes_null_name(): void
    {
        $server = new Server(
            url: 'https://api.example.com',
        );

        $serialized = $server->jsonSerialize();

        self::assertArrayNotHasKey('name', $serialized);
    }
}
