<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class SecurityBacktrackLimitTest extends TestCase
{
    private const string BEARER_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Bearer Auth API
  version: 1.0.0
security:
  - bearerAuth: []
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: User list
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
YAML;

    #[Test]
    public function builder_backtrack_limit_governs_authorization_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->withMaxRegexBacktracks(100)
            ->build();

        $authorization = 'bearer a' . str_repeat("\t", 500) . '!';
        $request = new Psr17Factory()
            ->createServerRequest('GET', '/users')
            ->withHeader('Authorization', $authorization);

        $this->expectException(PregRuntimeException::class);
        $this->expectExceptionMessage('Backtrack limit exhausted');

        $validator->validateRequest($request);
    }

    #[Test]
    public function default_limit_rejects_malformed_bearer_without_pcre_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $authorization = 'bearer a' . str_repeat("\t", 500) . '!';
        $request = new Psr17Factory()
            ->createServerRequest('GET', '/users')
            ->withHeader('Authorization', $authorization);

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }
}
