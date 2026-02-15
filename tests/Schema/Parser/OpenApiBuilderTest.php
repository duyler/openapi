<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Servers;
use Duyler\OpenApi\Schema\Model\Tags;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenApiBuilderTest extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonParser();
    }

    #[Test]
    public function build_document_with_all_fields(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'Test description',
                'termsOfService' => 'https://example.com/terms',
                'contact' => [
                    'name' => 'Support',
                    'url' => 'https://example.com/support',
                    'email' => 'support@example.com',
                ],
                'license' => [
                    'name' => 'MIT',
                    'identifier' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT',
                ],
            ],
            'jsonSchemaDialect' => 'https://json-schema.org/draft/2020-12/schema',
            'servers' => [
                [
                    'url' => 'https://api.example.com',
                    'description' => 'Production server',
                    'variables' => [
                        'version' => [
                            'default' => 'v1',
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/test' => [
                    'get' => [
                        'operationId' => 'getTest',
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
            'webhooks' => [
                'newPet' => [
                    'post' => [
                        'requestBody' => [
                            'description' => 'Information about a new pet',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['type' => 'object'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Pet' => ['type' => 'object'],
                ],
                'responses' => [
                    'NotFound' => ['description' => 'Not found'],
                ],
                'parameters' => [
                    'limitParam' => [
                        'name' => 'limit',
                        'in' => 'query',
                    ],
                ],
                'examples' => [
                    'example1' => [
                        'summary' => 'Example summary',
                        'value' => ['foo' => 'bar'],
                    ],
                ],
                'requestBodies' => [
                    'body1' => [
                        'description' => 'Request body',
                    ],
                ],
                'headers' => [
                    'X-Custom' => [
                        'description' => 'Custom header',
                    ],
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
                'links' => [
                    'link1' => [
                        'operationRef' => '#/paths/~1users/get',
                    ],
                ],
                'callbacks' => [
                    'callback1' => [
                        'expression' => [
                            'post' => [
                                'responses' => [
                                    '200' => ['description' => 'OK'],
                                ],
                            ],
                        ],
                    ],
                ],
                'pathItems' => [
                    'path1' => [
                        'get' => [
                            'responses' => [
                                '200' => ['description' => 'OK'],
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'tags' => [
                [
                    'name' => 'pets',
                    'description' => 'Pets operations',
                    'externalDocs' => [
                        'url' => 'https://example.com/docs',
                        'description' => 'External docs',
                    ],
                ],
            ],
            'externalDocs' => [
                'url' => 'https://example.com/docs',
                'description' => 'External documentation',
            ],
        ]);

        $document = $this->parser->parse($json);

        $this->assertSame('3.1.0', $document->openapi);
        $this->assertSame('Test API', $document->info->title);
        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $document->jsonSchemaDialect);
        $this->assertInstanceOf(Servers::class, $document->servers);
        $this->assertInstanceOf(Paths::class, $document->paths);
        $this->assertInstanceOf(Webhooks::class, $document->webhooks);
        $this->assertInstanceOf(Components::class, $document->components);
        $this->assertInstanceOf(SecurityRequirement::class, $document->security);
        $this->assertInstanceOf(Tags::class, $document->tags);
        $this->assertInstanceOf(ExternalDocs::class, $document->externalDocs);
    }

    #[Test]
    public function build_info_with_contact(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Support',
                    'url' => 'https://example.com',
                    'email' => 'test@example.com',
                ],
            ],
            'paths' => [],
        ]);

        $document = $this->parser->parse($json);

        $this->assertInstanceOf(Contact::class, $document->info->contact);
        $this->assertSame('Support', $document->info->contact->name);
        $this->assertSame('https://example.com', $document->info->contact->url);
        $this->assertSame('test@example.com', $document->info->contact->email);
    }

    #[Test]
    public function build_info_with_license(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'license' => [
                    'name' => 'MIT',
                    'identifier' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT',
                ],
            ],
            'paths' => [],
        ]);

        $document = $this->parser->parse($json);

        $this->assertInstanceOf(License::class, $document->info->license);
        $this->assertSame('MIT', $document->info->license->name);
        $this->assertSame('MIT', $document->info->license->identifier);
        $this->assertSame('https://opensource.org/licenses/MIT', $document->info->license->url);
    }

    #[Test]
    public function build_server_with_variables(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'servers' => [
                [
                    'url' => 'https://{environment}.example.com',
                    'description' => 'API server',
                    'variables' => [
                        'environment' => [
                            'default' => 'api',
                            'enum' => ['api', 'staging'],
                        ],
                    ],
                ],
            ],
            'paths' => [],
        ]);

        $document = $this->parser->parse($json);

        $this->assertCount(1, $document->servers->servers);
        $server = $document->servers->servers[0];
        $this->assertSame('https://{environment}.example.com', $server->url);
        $this->assertSame('API server', $server->description);
        $this->assertNotNull($server->variables);
    }

    #[Test]
    public function build_operation_with_all_fields(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'tags' => ['users'],
                        'summary' => 'Get users',
                        'description' => 'Returns a list of users',
                        'externalDocs' => [
                            'url' => 'https://example.com/docs/users',
                        ],
                        'operationId' => 'getUsers',
                        'parameters' => [
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'description' => 'Limit results',
                            ],
                        ],
                        'requestBody' => [
                            'description' => 'Request body',
                            'content' => [],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                        'callbacks' => [
                            'onEvent' => [
                                '{$request.body#/callbackUrl}' => [
                                    'post' => [
                                        'responses' => ['200' => ['description' => 'OK']],
                                    ],
                                ],
                            ],
                        ],
                        'deprecated' => true,
                        'security' => [['bearerAuth' => []]],
                        'servers' => [
                            ['url' => 'https://api.example.com'],
                        ],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $operation = $document->paths->paths['/users']->get;

        $this->assertSame(['users'], $operation->tags);
        $this->assertSame('Get users', $operation->summary);
        $this->assertSame('Returns a list of users', $operation->description);
        $this->assertInstanceOf(ExternalDocs::class, $operation->externalDocs);
        $this->assertSame('getUsers', $operation->operationId);
        $this->assertInstanceOf(Parameters::class, $operation->parameters);
        $this->assertInstanceOf(RequestBody::class, $operation->requestBody);
        $this->assertInstanceOf(Responses::class, $operation->responses);
        $this->assertInstanceOf(Callbacks::class, $operation->callbacks);
        $this->assertTrue($operation->deprecated);
        $this->assertInstanceOf(SecurityRequirement::class, $operation->security);
        $this->assertInstanceOf(Servers::class, $operation->servers);
    }

    #[Test]
    public function build_parameter_with_content(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'filter',
                                'in' => 'query',
                                'description' => 'Filter parameter',
                                'required' => true,
                                'deprecated' => true,
                                'allowEmptyValue' => true,
                                'style' => 'form',
                                'explode' => true,
                                'allowReserved' => true,
                                'schema' => ['type' => 'string'],
                                'examples' => ['example1' => ['value' => 'test']],
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $param = $document->paths->paths['/users']->get->parameters->parameters[0];

        $this->assertSame('filter', $param->name);
        $this->assertSame('query', $param->in);
        $this->assertSame('Filter parameter', $param->description);
        $this->assertTrue($param->required);
        $this->assertTrue($param->deprecated);
        $this->assertTrue($param->allowEmptyValue);
        $this->assertSame('form', $param->style);
        $this->assertTrue($param->explode);
        $this->assertTrue($param->allowReserved);
        $this->assertInstanceOf(Schema::class, $param->schema);
        $this->assertInstanceOf(Content::class, $param->content);
    }

    #[Test]
    public function build_schema_with_all_fields(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'TestSchema' => [
                        '$ref' => '#/components/schemas/Other',
                        'format' => 'date-time',
                        'title' => 'Test Schema',
                        'description' => 'Test description',
                        'default' => 'default_value',
                        'deprecated' => true,
                        'type' => 'string',
                        'nullable' => true,
                        'const' => 'constant_value',
                        'multipleOf' => 2,
                        'maximum' => 100,
                        'exclusiveMaximum' => 50,
                        'minimum' => 0,
                        'exclusiveMinimum' => 1,
                        'maxLength' => 100,
                        'minLength' => 1,
                        'pattern' => '^[a-z]+$',
                        'maxItems' => 10,
                        'minItems' => 1,
                        'uniqueItems' => true,
                        'maxProperties' => 20,
                        'minProperties' => 1,
                        'required' => ['id'],
                        'allOf' => [['type' => 'object']],
                        'anyOf' => [['type' => 'string']],
                        'oneOf' => [['type' => 'number']],
                        'not' => ['type' => 'null'],
                        'discriminator' => [
                            'propertyName' => 'type',
                            'mapping' => ['dog' => '#/components/schemas/Dog'],
                        ],
                        'properties' => [
                            'id' => ['type' => 'string'],
                        ],
                        'additionalProperties' => true,
                        'unevaluatedProperties' => false,
                        'items' => ['type' => 'string'],
                        'prefixItems' => [['type' => 'string']],
                        'contains' => ['type' => 'number'],
                        'minContains' => 1,
                        'maxContains' => 5,
                        'patternProperties' => [
                            '^x-' => ['type' => 'string'],
                        ],
                        'propertyNames' => ['pattern' => '^[a-z]+$'],
                        'dependentSchemas' => [
                            'creditCard' => ['type' => 'object'],
                        ],
                        'if' => ['type' => 'object'],
                        'then' => ['required' => ['name']],
                        'else' => ['required' => ['id']],
                        'unevaluatedItems' => ['type' => 'string'],
                        'example' => 'example_value',
                        'examples' => ['ex1' => ['value' => 'test']],
                        'enum' => ['a', 'b', 'c'],
                        'contentEncoding' => 'base64',
                        'contentMediaType' => 'application/json',
                        'contentSchema' => '{"type": "object"}',
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $schema = $document->components->schemas['TestSchema'];

        $this->assertSame('#/components/schemas/Other', $schema->ref);
        $this->assertSame('date-time', $schema->format);
        $this->assertSame('Test Schema', $schema->title);
        $this->assertSame('Test description', $schema->description);
        $this->assertSame('default_value', $schema->default);
        $this->assertTrue($schema->deprecated);
        $this->assertSame('string', $schema->type);
        $this->assertTrue($schema->nullable);
        $this->assertSame('constant_value', $schema->const);
        $this->assertSame(2.0, $schema->multipleOf);
        $this->assertSame(100.0, $schema->maximum);
        $this->assertSame(50.0, $schema->exclusiveMaximum);
        $this->assertSame(0.0, $schema->minimum);
        $this->assertSame(1.0, $schema->exclusiveMinimum);
        $this->assertSame(100, $schema->maxLength);
        $this->assertSame(1, $schema->minLength);
        $this->assertSame('^[a-z]+$', $schema->pattern);
        $this->assertSame(10, $schema->maxItems);
        $this->assertSame(1, $schema->minItems);
        $this->assertTrue($schema->uniqueItems);
        $this->assertSame(20, $schema->maxProperties);
        $this->assertSame(1, $schema->minProperties);
        $this->assertSame(['id'], $schema->required);
        $this->assertCount(1, $schema->allOf);
        $this->assertCount(1, $schema->anyOf);
        $this->assertCount(1, $schema->oneOf);
        $this->assertInstanceOf(Schema::class, $schema->not);
        $this->assertInstanceOf(Discriminator::class, $schema->discriminator);
        $this->assertArrayHasKey('id', $schema->properties);
        $this->assertTrue($schema->additionalProperties);
        $this->assertFalse($schema->unevaluatedProperties);
        $this->assertInstanceOf(Schema::class, $schema->items);
        $this->assertCount(1, $schema->prefixItems);
        $this->assertInstanceOf(Schema::class, $schema->contains);
        $this->assertSame(1, $schema->minContains);
        $this->assertSame(5, $schema->maxContains);
        $this->assertArrayHasKey('^x-', $schema->patternProperties);
        $this->assertInstanceOf(Schema::class, $schema->propertyNames);
        $this->assertArrayHasKey('creditCard', $schema->dependentSchemas);
        $this->assertInstanceOf(Schema::class, $schema->if);
        $this->assertInstanceOf(Schema::class, $schema->then);
        $this->assertInstanceOf(Schema::class, $schema->else);
        $this->assertInstanceOf(Schema::class, $schema->unevaluatedItems);
        $this->assertSame('example_value', $schema->example);
        $this->assertNotNull($schema->examples);
        $this->assertSame(['a', 'b', 'c'], $schema->enum);
        $this->assertSame('base64', $schema->contentEncoding);
        $this->assertSame('application/json', $schema->contentMediaType);
        $this->assertSame('{"type": "object"}', $schema->contentSchema);
        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $schema->jsonSchemaDialect);
    }

    #[Test]
    public function build_schema_with_additional_properties_schema(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'TestSchema' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'string'],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $schema = $document->components->schemas['TestSchema'];

        $this->assertInstanceOf(Schema::class, $schema->additionalProperties);
    }

    #[Test]
    public function build_response_with_headers_and_links(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'headers' => [
                                    'X-Rate-Limit' => [
                                        'description' => 'Rate limit',
                                        'required' => true,
                                        'deprecated' => true,
                                        'allowEmptyValue' => true,
                                        'schema' => ['type' => 'integer'],
                                        'example' => 100,
                                        'examples' => ['default' => ['value' => 100]],
                                        'content' => [
                                            'text/plain' => [
                                                'schema' => ['type' => 'string'],
                                            ],
                                        ],
                                    ],
                                ],
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
                                        'encoding' => 'utf-8',
                                        'example' => '{"id": 1}',
                                        'examples' => ['example1' => ['value' => ['id' => 1]]],
                                    ],
                                ],
                                'links' => [
                                    'userPosts' => [
                                        'operationRef' => '#/paths/~1users~1{id}~1posts/get',
                                        '$ref' => '#/components/links/UserPosts',
                                        'description' => 'Get user posts',
                                        'operationId' => 'getUserPosts',
                                        'parameters' => ['userId' => '$response.body#/id'],
                                        'requestBody' => [
                                            'description' => 'Request body',
                                        ],
                                        'server' => [
                                            'url' => 'https://api.example.com',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $response = $document->paths->paths['/users']->get->responses->responses['200'];

        $this->assertSame('Success', $response->description);
        $this->assertInstanceOf(Headers::class, $response->headers);
        $this->assertInstanceOf(Content::class, $response->content);
        $this->assertInstanceOf(Links::class, $response->links);

        $header = $response->headers->headers['X-Rate-Limit'];
        $this->assertSame('Rate limit', $header->description);
        $this->assertTrue($header->required);
        $this->assertTrue($header->deprecated);
        $this->assertTrue($header->allowEmptyValue);
        $this->assertInstanceOf(Schema::class, $header->schema);
        $this->assertSame(100, $header->example);
        $this->assertNotNull($header->examples);
        $this->assertInstanceOf(Content::class, $header->content);

        $link = $response->links->links['userPosts'];
        $this->assertSame('#/paths/~1users~1{id}~1posts/get', $link->operationRef);
        $this->assertSame('#/components/links/UserPosts', $link->ref);
        $this->assertSame('Get user posts', $link->description);
        $this->assertSame('getUserPosts', $link->operationId);
        $this->assertSame(['userId' => '$response.body#/id'], $link->parameters);
        $this->assertInstanceOf(RequestBody::class, $link->requestBody);
        $this->assertInstanceOf(Server::class, $link->server);
    }

    #[Test]
    public function build_security_scheme_with_all_fields(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'description' => 'Bearer authentication',
                        'name' => 'Authorization',
                        'in' => 'header',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'authorizationUrl' => 'https://example.com/auth',
                        'tokenUrl' => 'https://example.com/token',
                        'refreshUrl' => 'https://example.com/refresh',
                        'scopes' => ['read' => 'Read access', 'write' => 'Write access'],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $scheme = $document->components->securitySchemes['bearerAuth'];

        $this->assertSame('http', $scheme->type);
        $this->assertSame('Bearer authentication', $scheme->description);
        $this->assertSame('Authorization', $scheme->name);
        $this->assertSame('header', $scheme->in);
        $this->assertSame('bearer', $scheme->scheme);
        $this->assertSame('JWT', $scheme->bearerFormat);
        $this->assertSame('https://example.com/auth', $scheme->authorizationUrl);
        $this->assertSame('https://example.com/token', $scheme->tokenUrl);
        $this->assertSame('https://example.com/refresh', $scheme->refreshUrl);
        $this->assertSame(['read' => 'Read access', 'write' => 'Write access'], $scheme->scopes);
    }

    #[Test]
    public function build_example(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'examples' => [
                    'example1' => [
                        'summary' => 'Example summary',
                        'description' => 'Example description',
                        'value' => ['foo' => 'bar'],
                        'externalValue' => 'https://example.com/example.json',
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $example = $document->components->examples['example1'];

        $this->assertSame('Example summary', $example->summary);
        $this->assertSame('Example description', $example->description);
        $this->assertSame(['foo' => 'bar'], $example->value);
        $this->assertSame('https://example.com/example.json', $example->externalValue);
    }

    #[Test]
    public function build_external_docs(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'externalDocs' => [
                'url' => 'https://example.com/docs',
                'description' => 'External documentation',
            ],
        ]);

        $document = $this->parser->parse($json);

        $this->assertSame('https://example.com/docs', $document->externalDocs->url);
        $this->assertSame('External documentation', $document->externalDocs->description);
    }

    #[Test]
    public function build_discriminator_without_mapping(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'discriminator' => [
                            'propertyName' => 'petType',
                        ],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $schema = $document->components->schemas['Pet'];

        $this->assertSame('petType', $schema->discriminator->propertyName);
        $this->assertNull($schema->discriminator->mapping);
    }

    #[Test]
    public function build_callbacks_in_components(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'callbacks' => [
                    'myCallback' => [
                        'expression' => [
                            'post' => [
                                'responses' => ['200' => ['description' => 'OK']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);

        $this->assertArrayHasKey('myCallback', $document->components->callbacks);
        $this->assertInstanceOf(Callbacks::class, $document->components->callbacks['myCallback']);
    }

    #[Test]
    public function build_path_item_with_all_methods(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    '$ref' => '#/components/pathItems/Test',
                    'summary' => 'Test path',
                    'description' => 'Test description',
                    'get' => ['responses' => ['200' => ['description' => 'OK']]],
                    'put' => ['responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['responses' => ['200' => ['description' => 'OK']]],
                    'options' => ['responses' => ['200' => ['description' => 'OK']]],
                    'head' => ['responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['responses' => ['200' => ['description' => 'OK']]],
                    'trace' => ['responses' => ['200' => ['description' => 'OK']]],
                    'servers' => [['url' => 'https://api.example.com']],
                    'parameters' => [['name' => 'id', 'in' => 'path']],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $pathItem = $document->paths->paths['/test'];

        $this->assertSame('#/components/pathItems/Test', $pathItem->ref);
        $this->assertSame('Test path', $pathItem->summary);
        $this->assertSame('Test description', $pathItem->description);
        $this->assertInstanceOf(Operation::class, $pathItem->get);
        $this->assertInstanceOf(Operation::class, $pathItem->put);
        $this->assertInstanceOf(Operation::class, $pathItem->post);
        $this->assertInstanceOf(Operation::class, $pathItem->delete);
        $this->assertInstanceOf(Operation::class, $pathItem->options);
        $this->assertInstanceOf(Operation::class, $pathItem->head);
        $this->assertInstanceOf(Operation::class, $pathItem->patch);
        $this->assertInstanceOf(Operation::class, $pathItem->trace);
        $this->assertInstanceOf(Servers::class, $pathItem->servers);
        $this->assertInstanceOf(Parameters::class, $pathItem->parameters);
    }

    #[Test]
    public function build_response_with_ref(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => [
                            '200' => ['$ref' => '#/components/responses/Success'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'responses' => [
                    'Success' => ['description' => 'Success response'],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $response = $document->paths->paths['/test']->get->responses->responses['200'];

        $this->assertSame('#/components/responses/Success', $response->ref);
    }

    #[Test]
    public function invalid_discriminator_throws_exception(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Pet' => [
                        'type' => 'object',
                        'discriminator' => [],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Discriminator must have propertyName');
        $this->parser->parse($json);
    }

    #[Test]
    public function invalid_external_docs_throws_exception(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'externalDocs' => [],
        ]);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('External documentation must have url');
        $this->parser->parse($json);
    }

    #[Test]
    public function invalid_security_scheme_throws_exception(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'invalid' => [],
                ],
            ],
        ]);

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Security scheme must have type');
        $this->parser->parse($json);
    }

    #[Test]
    public function parameter_with_example_string(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'q',
                                'in' => 'query',
                                'example' => 'search term',
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $param = $document->paths->paths['/test']->get->parameters->parameters[0];

        $this->assertInstanceOf(Example::class, $param->example);
        $this->assertSame('search term', $param->example->value);
    }

    #[Test]
    public function media_type_with_example_string(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'example' => '{"id": 1}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $document = $this->parser->parse($json);
        $mediaType = $document->paths->paths['/test']->get->responses->responses['200']->content->mediaTypes['application/json'];

        $this->assertInstanceOf(Example::class, $mediaType->example);
        $this->assertSame('{"id": 1}', $mediaType->example->value);
    }
}
