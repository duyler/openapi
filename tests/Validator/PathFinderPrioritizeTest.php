<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

final class PathFinderPrioritizeTest extends TestCase
{
    #[Test]
    public function prioritizes_static_path_over_parameterized_path(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/me:
    get:
      responses:
        '200':
          description: Success
  /users/{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/users/me');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/me', $operation->path);
        $this->assertSame('GET', $operation->method);
    }
}
