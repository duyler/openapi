<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HD-05: Header parameter with `type: array` schema.
 *
 * PSR-7 headers are delivered as `string[]` arrays per RFC 7230 §3.2.2.
 * The RequestValidator normalizes each header into a single comma-joined
 * string via `implode(', ', $values)`. The HeadersValidator then routes
 * the value through `ParameterDeserializer::deserializeSimple`, which
 * splits on the comma character to produce the array form.
 *
 * Important quirk: the comma-space join in RequestValidator combined
 * with comma-only split in ParameterDeserializer means PSR-7 array
 * headers (multiple values joined with `, `) produce items with leading
 * spaces after the split. Single-string headers with `,` as separator
 * (no spaces) produce clean items.
 */
final class HeaderArrayTest extends TestCase
{
    private const string HEADER_ARRAY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: HD-05 Header Array API
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

    /**
     * HD-05 positive: `X-Items: a,b,c` (single string, no spaces around commas)
     * parses to the 3-element array ["a", "b", "c"] against `type: array`.
     *
     * The successful validation against `items.enum: [a, b, c]` proves the
     * comma-separated string was split into individual items.
     */
    #[Test]
    public function hd_05_header_string_value_with_commas_parses_to_array(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: HD-05 Header Array API
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

    /**
     * HD-05 positive (PSR-7 multi-value): `withHeader('X-Items', ['a', 'b', 'c'])`
     * produces a clean array after split when each PSR-7 value contains a
     * single token. The RequestValidator joins PSR-7 values with `, ` and
     * ParameterDeserializer splits by `,` — the resulting items have leading
     * spaces, so this positive test uses an unconstrained `items: string`.
     */
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

    /**
     * HD-05 negative: required header missing entirely.
     *
     * HeadersValidator::findParameter returns null when no header with
     * matching name (case-insensitive) is present. The required flag then
     * raises MissingParameterException for the header location.
     */
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

    /**
     * HD-05 negative: too many items — `X-Items: a,b,c,d` against `maxItems: 3`.
     *
     * The 4-element split array exceeds the maxItems constraint and
     * raises MaxItemsError with params `['maxItems' => 3, 'actual' => 4]`.
     */
    #[Test]
    public function hd_05_header_value_exceeding_max_items_throws_max_items_error(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: HD-05 Header Array API
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

    /**
     * HD-05 edge: PSR-7 array header values joined with `, ` (comma+space)
     * produce items with leading spaces after ParameterDeserializer's comma
     * split. The split result `["a", " b", " c"]` fails `items.enum: [a,b,c]`
     * because `" b"` is not in the allowed enum.
     *
     * This documents the actual implementation: RequestValidator uses
     * `implode(', ', $values)` but ParameterDeserializer splits on `','`
     * only — there is no space-trimming step.
     */
    #[Test]
    public function hd_05_psr7_array_header_produces_leading_spaces_in_split_items(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: HD-05 Header Array API
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

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected EnumError because PSR-7 array header items gain leading spaces');
        } catch (EnumError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(EnumError::class, $caught);
        self::assertSame('enum', $caught->keyword());
        self::assertSame(['a', 'b', 'c'], $caught->params()['allowed']);
        self::assertSame(' b', $caught->params()['actual']);
    }

    private function buildHeaderRequest(string $headerValue): ServerRequestInterface
    {
        return $this->psrFactory
            ->createServerRequest('GET', '/items')
            ->withHeader('X-Items', $headerValue);
    }
}
