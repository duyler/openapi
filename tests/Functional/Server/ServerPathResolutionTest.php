<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Server;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerPathResolutionTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../fixtures/server-path-resolution';
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function request_with_base_path_matches_path_template(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/v1/users/550e8400-e29b-41d4-a716-446655440000',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function request_with_different_base_path_matches_correct_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/v2/users/550e8400-e29b-41d4-a716-446655440000',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function request_without_base_path_still_works_when_disabled(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/users/550e8400-e29b-41d4-a716-446655440000',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function request_without_base_path_falls_through_when_enabled(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/users/550e8400-e29b-41d4-a716-446655440000',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function path_parameters_validated_after_base_path_strip(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/v1/users/not-a-uuid',
        );

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function query_parameters_validated_after_base_path_strip(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/v1/users?limit=abc',
        );

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_validated_after_base_path_strip(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/v1/products')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'Widget',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('/products', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function server_with_variables_resolved_correctly(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/server-with-variables.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/v1/status');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/status', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function root_server_no_base_path_strips_nothing(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/root-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/ping');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/ping', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function non_matching_base_path_throws_operation_not_found(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/v3/users');

        $this->expectException(BuilderException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function backward_compat_without_enable_flag(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(self::FIXTURES_DIR . '/multi-server.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/v1/users/550e8400-e29b-41d4-a716-446655440000',
        );

        $this->expectException(BuilderException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function multiple_servers_longest_prefix_match(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Prefix Match API
  version: 1.0.0
servers:
  - url: https://api.example.com/v1
    description: V1
  - url: https://api.example.com/v1beta
    description: V1 Beta
paths:
  /status:
    get:
      operationId: getStatus
      responses:
        '200':
          description: Status
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/v1beta/status');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/status', $operation->path);
        $this->assertSame('GET', $operation->method);
    }
}
