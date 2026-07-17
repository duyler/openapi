<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathParametersTest extends TestCase
{
    private const string SPEC_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Path Parameters API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsers
      summary: List users
      responses:
        '200':
          description: A list of users
  /users/{id}:
    get:
      operationId: getUserById
      summary: Get user by ID
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: User found
  /users/{id}/posts/{postId}:
    get:
      operationId: getUserPost
      summary: Get a specific post authored by a user
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
        - name: postId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Post found
YAML;

    private OpenApiValidator $validator;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_YAML)
            ->build();
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function extracts_path_parameters_from_request(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users/42/posts/7');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame(['id' => '42', 'postId' => '7'], $operation->pathParameters);
        $this->assertSame('/users/{id}/posts/{postId}', $operation->path);
    }

    #[Test]
    public function extracts_single_path_parameter_from_request(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users/42');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame(['id' => '42'], $operation->pathParameters);
    }

    #[Test]
    public function empty_array_when_no_path_params(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame([], $operation->pathParameters);
    }

    #[Test]
    public function exposes_operation_id_from_matched_operation(): void
    {
        $request = $this->psrFactory->createServerRequest('GET', '/users/42');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame('getUserById', $operation->operationId);
    }
}
