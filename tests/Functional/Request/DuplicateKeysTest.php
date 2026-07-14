<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * RB-11: JSON request body with duplicate keys.
 *
 * RFC 8259 §4 says object names "SHOULD be unique" (not MUST). When duplicates
 * appear, the behaviour is implementation-defined. PHP json_decode implements
 * "last value wins" semantics — the latest duplicate overrides earlier ones.
 * This characterization test pins that behaviour end-to-end through the
 * OpenAPI validator pipeline so any future change to the parser surfaces
 * visibly.
 */
#[CoversClass(JsonBodyParser::class)]
final class DuplicateKeysTest extends TestCase
{
    private const string DUPLICATE_KEYS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: RB-11 Duplicate Keys API
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
              required: [value]
              properties:
                value:
                  type: integer
                  const: 2
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * Sanity check: PHP's json_decode uses last value wins on duplicate keys.
     *
     * This is the underlying primitive that JsonBodyParser relies on. Pinning
     * this in a unit-level assertion documents the source of the behaviour
     * before the end-to-end test.
     */
    #[Test]
    public function rb_11_php_json_decode_last_value_wins_for_duplicate_keys(): void
    {
        $decoded = json_decode('{"value": 1, "value": 2}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['value' => 2], $decoded);
    }

    /**
     * Positive: JSON body with duplicate keys is accepted; the last value
     * wins and is validated against the schema.
     *
     * The schema declares `value` with `const: 2`. The body contains
     * `{"value":1,"value":2}`. With last-value-wins semantics the parsed
     * value is 2, which satisfies the const constraint and validation
     * succeeds. If the parser were changed to first-value-wins (or to
     * reject duplicates), this test would fail and surface the change.
     */
    #[Test]
    public function rb_11_duplicate_keys_last_value_wins_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DUPLICATE_KEYS_SPEC)
            ->build();

        $request = $this->createJsonRequest('POST', '/data', '{"value": 1, "value": 2}');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    /**
     * Negative: when the last value violates the schema, validation fails —
     * proving the validator operates on the last duplicate, not the first.
     *
     * Body `{"value":2,"value":1}` parses to value=1 (last wins), which
     * violates `const: 2`. The resulting ConstError surfaces the resolved
     * value and expected value so we can confirm last-wins semantics.
     */
    #[Test]
    public function rb_11_duplicate_keys_negative_when_last_value_violates_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DUPLICATE_KEYS_SPEC)
            ->build();

        $request = $this->createJsonRequest('POST', '/data', '{"value": 2, "value": 1}');

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ConstError $e) {
            $caught = $e;
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                if ($error instanceof ConstError) {
                    $caught = $error;
                    break;
                }
            }
        }

        $this->assertNotNull(
            $caught,
            'Expected ConstError because the last duplicate value (1) violates const: 2.',
        );

        $params = $caught->params();
        $this->assertSame(2, $params['expected'], 'Const constraint must be 2.');
        $this->assertSame(1, $params['actual'], 'Resolved value must be 1 (last duplicate wins).');
    }

    /**
     * Round-trip: json_encode of the decoded body must not contain duplicates.
     *
     * After json_decode, the duplicate is collapsed into a single key in the
     * PHP array, so any re-encoded form contains exactly one `value` entry.
     */
    #[Test]
    public function rb_11_decoded_body_round_trips_to_single_key(): void
    {
        $decoded = json_decode('{"value": 1, "value": 2}', true, 512, JSON_THROW_ON_ERROR);

        $reencoded = json_encode($decoded, JSON_THROW_ON_ERROR);

        $this->assertSame('{"value":2}', $reencoded);
    }

    private function createJsonRequest(string $method, string $path, string $body): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest($method, $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));
    }
}
