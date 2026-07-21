<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

use function sprintf;

final class BuiltinFormatsTest extends TestCase
{
    #[Test]
    public function create_returns_format_registry_with_builtin_formats(): void
    {
        $registry = BuiltinFormats::create();

        $this->assertNotNull($registry->getValidator('string', 'email'));
        $this->assertNotNull($registry->getValidator('string', 'uri'));
        $this->assertNotNull($registry->getValidator('string', 'uuid'));
        $this->assertNotNull($registry->getValidator('string', 'date-time'));
    }

    #[Test]
    public function create_returns_new_instance_each_time(): void
    {
        $first = BuiltinFormats::create();
        $second = BuiltinFormats::create();

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function create_registers_all_ten_new_formats(): void
    {
        $registry = BuiltinFormats::create();

        $expected = [
            ['integer', 'int32'],
            ['integer', 'int64'],
            ['string', 'binary'],
            ['string', 'password'],
            ['string', 'idn-email'],
            ['string', 'idn-hostname'],
            ['string', 'iri'],
            ['string', 'iri-reference'],
            ['string', 'uri-reference'],
            ['string', 'uri-template'],
            ['string', 'regex'],
        ];

        foreach ($expected as [$type, $format]) {
            $this->assertNotNull(
                $registry->getValidator($type, $format),
                sprintf('Format "%s" for type "%s" must be registered', $format, $type),
            );
        }
    }

    #[Test]
    public function enable_strict_formats_accepts_all_new_formats(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: All Formats API
  version: 1.0.0
paths:
  /test:
    get:
      operationId: testAllFormats
      parameters:
        - name: int32Param
          in: query
          required: true
          schema: { type: integer, format: int32 }
        - name: int64Param
          in: query
          required: true
          schema: { type: integer, format: int64 }
        - name: binaryParam
          in: query
          required: true
          schema: { type: string, format: binary }
        - name: passwordParam
          in: query
          required: true
          schema: { type: string, format: password }
        - name: idnEmailParam
          in: query
          required: true
          schema: { type: string, format: idn-email }
        - name: idnHostnameParam
          in: query
          required: true
          schema: { type: string, format: idn-hostname }
        - name: iriParam
          in: query
          required: true
          schema: { type: string, format: iri }
        - name: iriReferenceParam
          in: query
          required: true
          schema: { type: string, format: iri-reference }
        - name: uriReferenceParam
          in: query
          required: true
          schema: { type: string, format: uri-reference }
        - name: uriTemplateParam
          in: query
          required: true
          schema: { type: string, format: uri-template }
        - name: regexParam
          in: query
          required: true
          schema: { type: string, format: regex }
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableStrictFormats()
            ->enableCoercion()
            ->build();

        $uri = '/test'
            . '?int32Param=42'
            . '&int64Param=123'
            . '&binaryParam=file_data'
            . '&passwordParam=secret123'
            . '&idnEmailParam=user%40example.com'
            . '&idnHostnameParam=example.com'
            . '&iriParam=https%3A%2F%2Fexample.com%2Fpath'
            . '&iriReferenceParam=%2Frelative%2Fpath'
            . '&uriReferenceParam=%2Fpath'
            . '&uriTemplateParam=%2Fusers%2F%7BuserId%7D'
            . '&regexParam=%5E%5Ba-z%5D%2B%24';

        $request = new Psr17Factory()->createServerRequest('GET', $uri);
        $operation = $validator->validateRequest($request);

        $this->assertSame('/test', $operation->path);
        $this->assertSame('GET', $operation->method);
    }
}
