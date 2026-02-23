<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function assert;
use function sprintf;

final class EdgeCasesV32Test extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function rejects_invalid_version(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '4.0.0'
info:
  title: Test
  version: '1.0'
YAML;

        $this->expectException(InvalidSchemaException::class);

        $parser->parse($yaml);
    }

    #[Test]
    public function rejects_unsupported_major_version(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '2.0.0'
info:
  title: Test
  version: '1.0'
YAML;

        $this->expectException(InvalidSchemaException::class);

        $parser->parse($yaml);
    }

    #[Test]
    public function handles_empty_additional_operations(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Test
  version: '1.0'
paths:
  /test:
    additionalOperations: {}
YAML;

        $document = $parser->parse($yaml);

        $testPath = $document->paths?->paths['/test'] ?? null;
        self::assertNotNull($testPath);
        self::assertSame([], $testPath->additionalOperations);
    }

    #[Test]
    public function handles_empty_streaming_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/streaming-events.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream(''));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function invalid_self_uri_rejected(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
\$self: 'not-a-valid-url'
info:
  title: Test
  version: '1.0'
YAML;

        $this->expectException(InvalidSchemaException::class);

        $parser->parse($yaml);
    }

    #[Test]
    public function accepts_valid_self_uri(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
\$self: 'https://api.example.com/openapi.json'
info:
  title: Test
  version: '1.0'
YAML;

        $document = $parser->parse($yaml);

        self::assertSame('https://api.example.com/openapi.json', $document->self);
    }

    #[Test]
    public function handles_missing_optional_fields(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Minimal API
  version: '1.0.0'
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
YAML;

        $document = $parser->parse($yaml);

        self::assertNull($document->servers);
        self::assertNull($document->components);
        self::assertNull($document->tags);
        self::assertNull($document->security);
        self::assertNull($document->externalDocs);
        self::assertNull($document->self);
        self::assertNull($document->jsonSchemaDialect);
        self::assertNull($document->webhooks);
    }

    #[Test]
    public function handles_missing_info_fields(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Test
  version: '1.0'
YAML;

        $document = $parser->parse($yaml);

        self::assertNull($document->info->description);
        self::assertNull($document->info->termsOfService);
        self::assertNull($document->info->contact);
        self::assertNull($document->info->license);
    }

    #[Test]
    public function info_requires_title_and_version(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Test
YAML;

        $this->expectException(InvalidSchemaException::class);

        $parser->parse($yaml);
    }

    #[Test]
    public function parameter_requires_name_and_in(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Test
  version: '1.0'
paths:
  /test:
    get:
      parameters:
        - name: testParam
      responses:
        '200':
          description: OK
YAML;

        $this->expectException(InvalidSchemaException::class);

        $parser->parse($yaml);
    }

    #[Test]
    public function handles_multiple_streaming_items(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/streaming-events.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '';
        for ($i = 0; $i < 100; $i++) {
            $body .= sprintf('{"level":"info","message":"Message %d"}', $i) . "\n";
        }

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function handles_special_characters_in_jsonl(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/streaming-events.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = '{"level":"info","message":"Test with unicode: \u0440\u0443\u0441\u0441\u043a\u0438\u0439"}';

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function handles_nested_discriminator_mapping(): void
    {
        $parser = new YamlParser();
        $content = file_get_contents(__DIR__ . '/../fixtures/v3.2/full-spec.yaml');
        assert($content !== false);

        $document = $parser->parse($content);

        $schemas = $document->components?->schemas;
        $userSchema = $schemas['User'] ?? null;
        self::assertNotNull($userSchema);
        self::assertNotNull($userSchema->discriminator);
        self::assertSame('type', $userSchema->discriminator->propertyName);

        $adminSchema = $schemas['AdminUser'] ?? null;
        self::assertNotNull($adminSchema);
        self::assertNotNull($adminSchema->allOf);
    }

    #[Test]
    public function handles_empty_required_array(): void
    {
        $parser = new YamlParser();

        $yaml = <<<YAML
openapi: '3.2.0'
info:
  title: Test
  version: '1.0'
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                required: []
YAML;

        $document = $parser->parse($yaml);

        self::assertNotNull($document->paths);
        $response = $document->paths->paths['/test']->get?->responses?->responses['200'];
        self::assertNotNull($response);
        $schema = $response->content?->mediaTypes['application/json']->schema;
        self::assertNotNull($schema);
        self::assertSame([], $schema->required);
    }

    #[Test]
    public function handles_complex_query_request_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/v3.2/query-method.yaml')
            ->build();

        $request = $this->psrFactory->createServerRequest('QUERY', '/search')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'query' => 'complex search',
                'filters' => ['active', 'verified', 'premium'],
            ])));

        $operation = $validator->validateRequest($request);

        self::assertSame('/search', $operation->path);
    }
}
