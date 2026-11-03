<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function http_build_query;

final class ContentParameterValidationRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Content Parameter Enforcement
  version: '1'
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - name: filter
          in: query
          content:
            application/json:
              schema:
                type: object
                required: [tag]
                properties:
                  tag:
                    type: string
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function content_query_parameter_matching_media_type_schema_is_valid(): void
    {
        $validator = $this->build();

        $operation = $validator->validateRequest($this->request('{"tag":"php"}'));

        self::assertSame('/items', $operation->path);
    }

    #[Test]
    public function content_query_parameter_contradicting_media_type_schema_is_invalid(): void
    {
        $validator = $this->build();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Property "tag" validation failed');

        $validator->validateRequest($this->request('{"tag":123}'));
    }

    #[Test]
    public function malformed_json_in_content_query_parameter_is_invalid(): void
    {
        $validator = $this->build();

        $this->expectException(InvalidParameterException::class);

        $validator->validateRequest($this->request('{"tag":'));
    }

    private function build(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    private function request(string $filterValue): ServerRequestInterface
    {
        $uri = 'http://localhost/items?' . http_build_query(['filter' => $filterValue]);

        return new Psr17Factory()
            ->createServerRequest('GET', $uri)
            ->withQueryParams(['filter' => $filterValue]);
    }
}
