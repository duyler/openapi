<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class SequentialWithoutParserTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: '3.2.0'
info:
  title: Sequential Media Types Without Stream Parsers
  version: '1.0.0'
paths:
  /geo-seq:
    get:
      operationId: getGeoSeq
      responses:
        '200':
          description: GeoJSON text sequence
          content:
            application/geo+json-seq:
              schema:
                type: object
                required:
                  - geometry
  /mixed:
    get:
      operationId: getMixed
      responses:
        '200':
          description: Mixed multipart stream
          content:
            multipart/mixed:
              schema:
                type: object
                required:
                  - payload
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function geo_json_seq_response_body_is_validated_loudly(): void
    {
        [$validator, $operation] = $this->buildValidatorWithOperation('/geo-seq');

        $response = $this->createResponse(
            'application/geo+json-seq',
            '{"geometry":{"type":"Point"}}' . "\n" . '{"geometry":{"type":"LineString"}}' . "\n",
        );

        $this->assertValidationFailsWithTypeError($validator, $response, $operation);
    }

    #[Test]
    public function multipart_mixed_response_body_is_validated_loudly(): void
    {
        [$validator, $operation] = $this->buildValidatorWithOperation('/mixed');

        $response = $this->createResponse(
            'multipart/mixed',
            "--mixed\r\nContent-Type: application/json\r\n\r\n{\"payload\":{}}\r\n--mixed--\r\n",
        );

        $this->assertValidationFailsWithTypeError($validator, $response, $operation);
    }

    /**
     * @return array{0: OpenApiValidatorInterface, 1: Operation}
     */
    private function buildValidatorWithOperation(string $path): array
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $operation = $validator->validateRequest(
            $this->factory->createServerRequest('GET', $path),
        );

        return [$validator, $operation];
    }

    private function createResponse(string $contentType, string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->factory->createStream($body));
    }

    private function assertValidationFailsWithTypeError(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): void {
        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException $exception) {
            $error = $exception->getErrors()[0] ?? null;

            $this->assertInstanceOf(TypeMismatchError::class, $error);
            $this->assertSame('type', $error->keyword());

            return;
        }

        $this->fail('Expected ValidationException was not thrown for a sequential media type without a stream parser');
    }
}
