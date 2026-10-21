<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\Psr7RequestFactory;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(JsonBodyParser::class)]
final class DuplicateKeysTest extends TestCase
{
    private const string DUPLICATE_KEYS_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Duplicate Keys API
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

    #[Test]
    public function php_json_decode_last_value_wins_for_duplicate_keys(): void
    {
        $decoded = json_decode('{"value": 1, "value": 2}', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['value' => 2], $decoded);
    }

    #[Test]
    public function duplicate_keys_last_value_wins_passes_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DUPLICATE_KEYS_SPEC)
            ->build();

        $request = Psr7RequestFactory::createJsonRequest('POST', '/data', '{"value": 1, "value": 2}');

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function duplicate_keys_negative_when_last_value_violates_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DUPLICATE_KEYS_SPEC)
            ->build();

        $request = Psr7RequestFactory::createJsonRequest('POST', '/data', '{"value": 2, "value": 1}');

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

    #[Test]
    public function decoded_body_round_trips_to_single_key(): void
    {
        $decoded = json_decode('{"value": 1, "value": 2}', true, 512, JSON_THROW_ON_ERROR);

        $reencoded = json_encode($decoded, JSON_THROW_ON_ERROR);

        $this->assertSame('{"value":2}', $reencoded);
    }
}
