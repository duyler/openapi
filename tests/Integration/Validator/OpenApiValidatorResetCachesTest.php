<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function assert;
use function count;
use function sprintf;

/**
 * Verifies EI-008 contract: OpenApiValidator::reset() must clear all internal
 * instance caches (PathRegexCache from EI-005 and RegexValidator from EI-006)
 * so the validator is safe to reuse across requests in long-running workers.
 *
 * @internal
 */
final class OpenApiValidatorResetCachesTest extends TestCase
{
    private OpenApiValidator $validator;
    private PathRegexCache $pathRegexCache;
    private RegexValidator $regexValidator;

    protected function setUp(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Reset Caches API
  version: 1.0.0
paths:
  /users/{userId}:
    get:
      parameters:
        - name: userId
          in: path
          required: true
          schema:
            type: string
            pattern: '^[a-z0-9-]+$'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                  email:
                    type: string
                    pattern: '^[a-z]+@[a-z]+\.[a-z]+$'
                patternProperties:
                  '^x-':
                    type: string
                required:
                  - name
                  - email
YAML;

        $built = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $built);

        $this->validator = $built;
        [$this->pathRegexCache, $this->regexValidator] = $this->extractSharedCaches($built);
    }

    #[Test]
    public function reset_clears_path_regex_cache_after_request_validation(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users/abc-123');

        $this->validator->validateRequest($request);

        self::assertGreaterThan(0, $this->pathRegexCacheSize());

        $this->validator->reset();

        self::assertSame(0, $this->pathRegexCacheSize());
    }

    #[Test]
    public function reset_clears_regex_validator_cache_after_request_validation(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users/abc-123');

        $this->validator->validateRequest($request);

        $this->validator->reset();

        self::assertSame(0, $this->regexValidatorSize());
    }

    #[Test]
    public function reset_clears_both_caches_simultaneously(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users/abc-123');

        $this->validator->validateRequest($request);

        self::assertGreaterThan(0, $this->pathRegexCacheSize());

        $this->validator->reset();

        self::assertSame(0, $this->pathRegexCacheSize());
        self::assertSame(0, $this->regexValidatorSize());
    }

    #[Test]
    public function validator_reusable_after_reset_with_no_cache_growth_leak(): void
    {
        $factory = new Psr17Factory();

        for ($iteration = 0; $iteration < 3; ++$iteration) {
            $request = $factory->createServerRequest('GET', '/users/abc-123');
            $this->validator->validateRequest($request);

            self::assertGreaterThan(0, $this->pathRegexCacheSize());

            $this->validator->reset();

            self::assertSame(0, $this->pathRegexCacheSize(), sprintf('iteration %d leaked path regex entries', $iteration));
            self::assertSame(0, $this->regexValidatorSize(), sprintf('iteration %d leaked regex validator entries', $iteration));
        }
    }

    /**
     * @return array{0: PathRegexCache, 1: RegexValidator}
     */
    private function extractSharedCaches(OpenApiValidator $validator): array
    {
        $reflection = new ReflectionClass($validator);
        $dependencies = $reflection->getProperty('dependencies')->getValue($validator);

        assert($dependencies->pathRegexCache instanceof PathRegexCache);
        assert($dependencies->regexValidator instanceof RegexValidator);

        return [$dependencies->pathRegexCache, $dependencies->regexValidator];
    }

    /**
     * @return int<0, max>
     */
    private function pathRegexCacheSize(): int
    {
        return count($this->readCacheArray($this->pathRegexCache, 'cache'));
    }

    /**
     * @return int<0, max>
     */
    private function regexValidatorSize(): int
    {
        return count($this->readCacheArray($this->regexValidator, 'normalizeCache'));
    }

    /**
     * @return array<string, mixed>
     */
    private function readCacheArray(object $owner, string $property): array
    {
        $reflection = new ReflectionClass($owner);
        $prop = $reflection->getProperty($property);

        /** @var array<string, mixed> $value */
        $value = $prop->getValue($owner);

        return $value;
    }
}
