<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class HeaderArrayTest extends TestCase
{
    private const string HEADER_ARRAY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Array API
  version: 1.0.0
paths:
  /items:
    get:
      parameters:
        - name: X-Items
          in: header
          required: true
          schema:
            type: array
            items:
              type: string
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
    public function hd_05_header_string_value_with_commas_parses_to_array(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Array API
  version: 1.0.0
paths:
  /items:
    get:
      parameters:
        - name: X-Items
          in: header
          required: true
          schema:
            type: array
            items:
              type: string
              enum: [a, b, c]
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->buildHeaderRequest('a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function hd_05_header_psr7_array_value_parses_to_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADER_ARRAY_SPEC)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/items')
            ->withHeader('X-Items', ['a', 'b', 'c']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function hd_05_missing_required_header_throws_missing_parameter(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADER_ARRAY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/items');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected MissingParameterException when required header is absent');
        } catch (MissingParameterException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(MissingParameterException::class, $caught);
        self::assertStringContainsString('X-Items', $caught->getMessage());
        self::assertStringContainsString('header', $caught->getMessage());
    }

    #[Test]
    public function hd_05_header_value_exceeding_max_items_throws_max_items_error(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Array API
  version: 1.0.0
paths:
  /items:
    get:
      parameters:
        - name: X-Items
          in: header
          required: true
          schema:
            type: array
            items:
              type: string
            maxItems: 3
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->buildHeaderRequest('a,b,c,d');

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected MaxItemsError when header array exceeds maxItems');
        } catch (MaxItemsError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(MaxItemsError::class, $caught);
        self::assertSame('maxItems', $caught->keyword());
        self::assertSame(3, $caught->params()['maxItems']);
        self::assertSame(4, $caught->params()['actual']);
    }

    #[Test]
    public function hd_05_psr7_array_header_trims_split_items(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Array API
  version: 1.0.0
paths:
  /items:
    get:
      parameters:
        - name: X-Items
          in: header
          required: true
          schema:
            type: array
            items:
              type: string
              enum: [a, b, c]
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/items')
            ->withHeader('X-Items', ['a', 'b', 'c']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    private function buildHeaderRequest(string $headerValue): ServerRequestInterface
    {
        return $this->psrFactory
            ->createServerRequest('GET', '/items')
            ->withHeader('X-Items', $headerValue);
    }
}
