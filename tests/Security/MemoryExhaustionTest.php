<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function memory_get_usage;
use function range;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class MemoryExhaustionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Memory Exhaustion DoS API
  version: 1.0.0
paths:
  /tags:
    post:
      operationId: createTags
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - tags
              properties:
                tags:
                  type: array
                  uniqueItems: true
                  items:
                    type: string
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function rejects_oversized_uniqueitems_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $payload = '{"tags":[' . str_repeat('"aaaaaa",', 900_000) . '"aaaaaa"]}';

        $request = $this->factory
            ->createServerRequest('POST', '/tags')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($payload));

        $caught = null;
        try {
            $validator->validateRequest($request);
        } catch (BodyTooLargeException|ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'A large uniqueItems payload (above the 10 MB default body cap) must be rejected by the body cap or schema validation.',
        );
    }

    #[Test]
    public function memory_stays_bounded_on_uniqueitems_deduplication(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $large = [];
        foreach (range(1, 50_000) as $i) {
            $large[] = 'tag-' . $i;
        }

        $payload = json_encode(['tags' => $large], JSON_THROW_ON_ERROR);

        $request = $this->factory
            ->createServerRequest('POST', '/tags')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($payload));

        $before = memory_get_usage();
        try {
            $validator->validateRequest($request);
        } catch (ValidationException|BodyTooLargeException) {
        }
        $after = memory_get_usage();

        $delta = $after - $before;

        self::assertLessThan(
            100 * 1024 * 1024,
            $delta,
            'Memory delta during 50k-element uniqueItems validation must stay bounded (under 100 MB).',
        );
    }

    #[Test]
    public function rejects_payload_above_default_json_body_cap(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $oversized = '{"tags":["' . str_repeat('a', 11 * 1024 * 1024) . '"]}';

        $request = $this->factory
            ->createServerRequest('POST', '/tags')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($oversized));

        $caught = null;
        try {
            $validator->validateRequest($request);
        } catch (BodyTooLargeException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'A payload above the default 10 MB JSON body cap must be rejected by BodyReader::readSafely.',
        );
    }
}
