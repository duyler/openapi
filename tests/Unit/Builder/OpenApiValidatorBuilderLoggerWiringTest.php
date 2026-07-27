<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

use function is_string;

/**
 * End-to-end coverage for the logger wiring introduced in task 02b:
 * withLogger($logger) on the builder must propagate into the four
 * silent-catch sites (PathParser, CallbackValidator, ServerPathMatcher,
 * XmlBodyParser) so that PSR-3 records are produced on production paths.
 * Verifies one wiring end-to-end (ServerPathMatcher) — the other three
 * share the same $context->logger plumbing inside ValidatorDependencies,
 * so this single assertion exercises the whole chain.
 *
 * @internal
 */
final class OpenApiValidatorBuilderLoggerWiringTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Logger wiring test
  version: 1.0.0
servers:
  - url: 'https://{unknown}.example.com'
paths:
  /users:
    get:
      operationId: getUsers
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function with_logger_propagates_into_server_path_matcher_silent_catch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('debug')
            ->with(
                'Server URL template substitution failed',
                $this->callback(static function (array $context): bool {
                    return isset($context['server_url'], $context['exception'])
                        && is_string($context['server_url'])
                        && $context['exception'] instanceof Throwable;
                }),
            );

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->withLogger($logger)
            ->enableServerPathResolution()
            ->build();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users');

        // ServerPathMatcher catches ServerVariableException for {unknown},
        // logs at debug, and falls back to the original request path so the
        // path finder still matches '/users' and validateRequest returns.
        $operation = $validator->validateRequest($request);

        $this->assertSame('getUsers', $operation->operationId);
    }

    #[Test]
    public function default_logger_keeps_validation_working_when_server_variable_is_unresolvable(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableServerPathResolution()
            ->build();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users');

        $operation = $validator->validateRequest($request);

        $this->assertSame('getUsers', $operation->operationId);
    }
}
