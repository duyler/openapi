<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

use const JSON_THROW_ON_ERROR;

final class RequestBodyValidationTest extends TestCase
{
    private const string JSON_REQUIRED_BODY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Request Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
      responses:
        '201':
          description: Created
YAML;

    private const string XML_BODY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: XML Body API
  version: 1.0.0
paths:
  /xml:
    post:
      requestBody:
        required: true
        content:
          application/xml:
            schema:
              type: object
              additionalProperties: true
      responses:
        '200':
          description: OK
YAML;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function valid_json_body_with_required_field_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    /**
     * @return array<string, array{0: string, 1: class-string<Throwable>}>
     */
    public static function malformedJsonProvider(): array
    {
        return [
            'unclosed bracket' => ['{invalid', JsonException::class],
            'closing without opening' => ['}', JsonException::class],
            'null instead of object' => ['null', ValidationException::class],
            'missing value after colon' => ['{"key":}', JsonException::class],
            'trailing comma' => ['{"a":1,}', JsonException::class],
            'whitespace only' => ['   ', MissingRequestBodyException::class],
        ];
    }

    #[DataProvider('malformedJsonProvider')]
    #[Test]
    public function malformed_json_body_throws_exception(string $body, string $expectedException): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));

        $this->expectException($expectedException);
        $validator->validateRequest($request);
    }

    #[Test]
    public function missing_required_body_with_empty_content_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function missing_required_body_with_whitespace_only_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('  '));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function missing_required_body_with_content_length_zero_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', '0')
            ->withBody($this->psrFactory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function valid_xml_body_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_BODY_SPEC)
            ->build();

        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root><name>John Doe</name></root>';

        $request = $this->psrFactory->createServerRequest('POST', '/xml')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xmlBody));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/xml', $operation->path);
    }

    #[Test]
    public function xxe_file_entity_does_not_leak_contents_through_validator(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_BODY_SPEC)
            ->build();

        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE root [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root><name>&xxe;</name></root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xxePayload));

        $output = $this->capture_validator_output($validator, $request);

        $this->assertStringNotContainsString('root:x:0:0', $output);
        $this->assertStringNotContainsString('/bin/bash', $output);
        $this->assertStringNotContainsString('/bin/sh', $output);
    }

    #[Test]
    public function xxe_ssrf_entity_does_not_leak_contents_through_validator(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_BODY_SPEC)
            ->build();

        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE root [
  <!ENTITY xxe SYSTEM "http://internal-server.local/secret-endpoint">
]>
<root><data>&xxe;</data></root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xxePayload));

        $output = $this->capture_validator_output($validator, $request);

        $this->assertStringNotContainsString('secret-endpoint', $output);
        $this->assertStringNotContainsString('internal-server', $output);
    }

    #[Test]
    public function entity_expansion_does_not_leak_through_validator(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_BODY_SPEC)
            ->build();

        $entityPayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
]>
<root><name>&lol3;</name></root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($entityPayload));

        $memoryBefore = memory_get_usage();

        $output = $this->capture_validator_output($validator, $request);

        $memoryIncrease = memory_get_usage() - $memoryBefore;

        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
        $this->assertStringNotContainsString('lololol', $output);
    }

    #[Test]
    public function json_body_without_bom_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function json_body_with_utf8_bom_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $bom = "\xEF\xBB\xBF";
        $body = $bom . '{"name":"John Doe"}';

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function malformed_xml_with_unclosed_tag_throws_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/xml')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream('<root><unclosed>'));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function json_body_with_content_type_q_value_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json; q=0.9')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function json_body_with_q_value_zero_throws_unsupported_media_type(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json; q=0')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function json_body_with_unsupported_media_type_and_q_value_throws_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/xml; q=0.1')
            ->withBody($this->psrFactory->createStream('<data>test</data>'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function content_type_uppercase_application_json_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'APPLICATION/JSON')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function content_type_mixed_case_application_json_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'Application/Json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function content_type_uppercase_xml_rejected_when_json_expected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_REQUIRED_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'APPLICATION/XML')
            ->withBody($this->psrFactory->createStream('<data>test</data>'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function deeply_nested_json_body_within_depth_limit_passes_validation(): void
    {
        $depth = 20;
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->buildNestedSpecJson($depth))
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/deep')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($this->buildNestedBodyJson($depth)));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/deep', $operation->path);
    }

    #[Test]
    public function deeply_nested_json_body_exceeding_untrusted_depth_throws_json_exception(): void
    {
        $depth = 200;
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->buildNestedSpecJson($depth))
            ->withMaxSpecDepth(500)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/deep')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($this->buildNestedBodyJson($depth)));

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Maximum stack depth exceeded');
        $validator->validateRequest($request);
    }

    private function buildNestedSpecJson(int $depth): string
    {
        $schema = $this->buildNestedSchema($depth);

        return json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Deep Nested API', 'version' => '1.0.0'],
            'paths' => [
                '/deep' => [
                    'post' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => ['schema' => $schema],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Created'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function buildNestedSchema(int $depth): array
    {
        $schema = [
            'type' => 'object',
            'properties' => ['leaf' => ['type' => 'string']],
            'additionalProperties' => false,
        ];

        for ($i = 0; $i < $depth; ++$i) {
            $schema = [
                'type' => 'object',
                'properties' => ['a' => $schema],
                'additionalProperties' => false,
            ];
        }

        return $schema;
    }

    private function buildNestedBodyJson(int $depth): string
    {
        $data = ['leaf' => 'value'];

        for ($i = 0; $i < $depth; ++$i) {
            $data = ['a' => $data];
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function capture_validator_output(
        OpenApiValidatorInterface $validator,
        ServerRequestInterface $request,
    ): string {
        try {
            $validator->validateRequest($request);

            return '';
        } catch (ValidationException $e) {
            return $e->getMessage() . $validator->getFormattedErrors($e);
        } catch (AbstractValidationError $e) {
            return $e->getMessage() . ($e->suggestion() ?? '');
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }
    }
}
