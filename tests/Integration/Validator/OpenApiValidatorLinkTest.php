<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Link\LinkContext;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenApiValidatorLinkTest extends TestCase
{
    private const string LINKS_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Link Test API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsers
      responses:
        '200':
          description: List of users
          links:
            GetUserById:
              operationId: getUser
              parameters:
                userId: '\$response.body#/0/id'
components:
  links:
    UserAddress:
      operationId: getAddress
      parameters:
        userId: '\$response.body#/id'
        requestId: '\$response.header#/X-Request-Id'
        source: '\$url'
        method: '\$method'
        status: '\$statusCode'
YAML;

    #[Test]
    public function resolve_link_with_response_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINKS_YAML)
            ->build();

        $result = $validator->resolveLink('UserAddress', ['id' => 42]);

        $this->assertSame(42, $result['parameters']['userId']);
    }

    #[Test]
    public function resolve_link_throws_for_unknown_link(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINKS_YAML)
            ->build();

        $this->expectException(InvalidArgumentException::class);

        $validator->resolveLink('UnknownLink', []);
    }

    #[Test]
    public function resolve_link_with_context_passes_full_context(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINKS_YAML)
            ->build();

        $context = new LinkContext(
            body: ['id' => 42],
            headers: ['X-Request-Id' => 'req-123'],
            url: 'https://api.example.com/users',
            method: 'GET',
            statusCode: 200,
        );

        $result = $validator->resolveLinkWithContext('UserAddress', $context);

        $this->assertSame(42, $result['parameters']['userId']);
        $this->assertSame('req-123', $result['parameters']['requestId']);
        $this->assertSame('https://api.example.com/users', $result['parameters']['source']);
        $this->assertSame('GET', $result['parameters']['method']);
        $this->assertSame(200, $result['parameters']['status']);
    }

    #[Test]
    public function resolve_link_with_context_throws_for_unknown_link(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINKS_YAML)
            ->build();

        $this->expectException(InvalidArgumentException::class);

        $validator->resolveLinkWithContext('UnknownLink', new LinkContext());
    }

    #[Test]
    public function resolve_link_delegates_to_resolve_link_with_context(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LINKS_YAML)
            ->build();

        $result = $validator->resolveLink('UserAddress', ['id' => 99]);
        $contextResult = $validator->resolveLinkWithContext('UserAddress', new LinkContext(body: ['id' => 99]));

        $this->assertSame($result['parameters']['userId'], $contextResult['parameters']['userId']);
    }
}
