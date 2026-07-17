<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\PathItemBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathItemBuilderTest extends TestCase
{
    private PathItemBuilder $pathItemBuilder;
    private OpenApiBuildContext $context;

    protected function setUp(): void
    {
        $this->context = new OpenApiBuildContext();
        $this->pathItemBuilder = $this->context->pathItemBuilder;
    }

    #[Test]
    public function build_server_with_all_fields(): void
    {
        $server = $this->pathItemBuilder->buildServer([
            'url' => 'https://api.example.com/{version}',
            'description' => 'Production',
            'variables' => ['version' => ['default' => 'v1']],
            'name' => 'prod',
        ]);

        self::assertSame('https://api.example.com/{version}', $server->url);
        self::assertSame('Production', $server->description);
        self::assertSame(['version' => ['default' => 'v1']], $server->variables);
        self::assertSame('prod', $server->name);
    }

    #[Test]
    public function build_servers_returns_list_of_servers(): void
    {
        $servers = $this->pathItemBuilder->buildServers([
            ['url' => 'https://a.example.com'],
            ['url' => 'https://b.example.com'],
        ]);

        self::assertCount(2, $servers);
        self::assertSame('https://a.example.com', $servers[0]->url);
        self::assertSame('https://b.example.com', $servers[1]->url);
    }

    #[Test]
    public function build_parameter_by_ref(): void
    {
        $parameter = $this->pathItemBuilder->buildParameter([
            '$ref' => '#/components/parameters/limitParam',
            'summary' => 'Limit summary',
            'description' => 'Limit description',
        ]);

        self::assertSame('#/components/parameters/limitParam', $parameter->ref);
        self::assertSame('Limit summary', $parameter->refSummary);
        self::assertSame('Limit description', $parameter->refDescription);
    }

    #[Test]
    public function build_parameter_throws_when_name_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Parameter must have name and in fields');

        $this->pathItemBuilder->buildParameter(['in' => 'query']);
    }

    #[Test]
    public function build_parameter_throws_when_in_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->pathItemBuilder->buildParameter(['name' => 'limit']);
    }

    #[Test]
    public function build_parameter_with_full_fields(): void
    {
        $parameter = $this->pathItemBuilder->buildParameter([
            'name' => 'limit',
            'in' => 'query',
            'description' => 'max records',
            'required' => true,
            'deprecated' => false,
            'allowEmptyValue' => false,
            'style' => 'form',
            'explode' => true,
            'allowReserved' => false,
            'schema' => ['type' => 'integer'],
        ]);

        self::assertSame('limit', $parameter->name);
        self::assertSame('query', $parameter->in);
        self::assertSame('max records', $parameter->description);
        self::assertTrue($parameter->required);
        self::assertSame('form', $parameter->style);
        self::assertTrue($parameter->explode);
        self::assertNotNull($parameter->schema);
        self::assertSame('integer', $parameter->schema->type);
    }

    #[Test]
    public function build_parameter_logs_deprecation_when_allow_empty_value_and_3_2(): void
    {
        $this->context->documentVersion = '3.2.0';

        $this->pathItemBuilder->buildParameter([
            'name' => 'q',
            'in' => 'query',
            'allowEmptyValue' => true,
        ]);

        // DeprecationLogger with NullLogger by default; assert no exception
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function build_path_item_with_methods(): void
    {
        $pathItem = $this->pathItemBuilder->buildPathItem([
            'get' => ['operationId' => 'list', 'responses' => ['200' => ['description' => 'OK']]],
            'post' => ['operationId' => 'create', 'responses' => ['201' => ['description' => 'Created']]],
            'summary' => 'Resource',
        ]);

        self::assertInstanceOf(PathItem::class, $pathItem);
        self::assertSame('Resource', $pathItem->summary);
        self::assertInstanceOf(Operation::class, $pathItem->get);
        self::assertInstanceOf(Operation::class, $pathItem->post);
        self::assertNull($pathItem->delete);
        self::assertSame('list', $pathItem->get->operationId);
    }

    #[Test]
    public function build_path_item_by_ref(): void
    {
        $pathItem = $this->pathItemBuilder->buildPathItem([
            '$ref' => '#/components/pathItems/UserPath',
        ]);

        self::assertSame('#/components/pathItems/UserPath', $pathItem->ref);
    }

    #[Test]
    public function build_parameters_returns_list(): void
    {
        $parameters = $this->pathItemBuilder->buildParameters([
            ['name' => 'limit', 'in' => 'query'],
            ['name' => 'offset', 'in' => 'query'],
        ]);

        self::assertCount(2, $parameters);
        self::assertSame('limit', $parameters[0]->name);
        self::assertSame('offset', $parameters[1]->name);
    }

    #[Test]
    public function build_paths_returns_path_map(): void
    {
        $paths = $this->pathItemBuilder->buildPaths([
            '/users' => ['get' => ['responses' => ['200' => ['description' => 'OK']]]],
            '/pets' => ['get' => ['responses' => ['200' => ['description' => 'OK']]]],
        ]);

        self::assertNotNull($paths->paths['/users'] ?? null);
        self::assertNotNull($paths->paths['/pets'] ?? null);
    }

    #[Test]
    public function build_operation_with_external_docs(): void
    {
        $operation = $this->pathItemBuilder->buildOperation([
            'operationId' => 'getUser',
            'externalDocs' => ['url' => 'https://docs.example.com/getUser'],
            'deprecated' => true,
        ]);

        self::assertSame('getUser', $operation->operationId);
        self::assertNotNull($operation->externalDocs);
        self::assertSame('https://docs.example.com/getUser', $operation->externalDocs->url);
        self::assertTrue($operation->deprecated);
    }

    #[Test]
    public function build_webhooks_returns_map(): void
    {
        $webhooks = $this->pathItemBuilder->buildWebhooks([
            'newPet' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]]]],
        ]);

        self::assertNotNull($webhooks->webhooks['newPet'] ?? null);
    }

    #[Test]
    public function build_additional_operations_returns_method_map(): void
    {
        $ops = $this->pathItemBuilder->buildAdditionalOperations([
            'custom' => ['responses' => ['200' => ['description' => 'OK']]],
        ]);

        self::assertArrayHasKey('custom', $ops);
        self::assertInstanceOf(Operation::class, $ops['custom']);
    }

    #[Test]
    public function build_callbacks_delegates_to_components_builder(): void
    {
        $callbacks = $this->pathItemBuilder->buildCallbacks([
            'myCallback' => [
                '{$request.body#/callback_url}' => [
                    'post' => ['responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ]);

        self::assertNotNull($callbacks->callbacks['myCallback'] ?? null);
    }
}
