<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function count;

final class CompositionBranchErrorsTest extends TestCase
{
    private const string SPEC = <<<'YAML'
        openapi: 3.0.3
        info:
          title: composition error reporting
          version: 1.0.0
        paths:
          /forms:
            get:
              operationId: getForm
              responses:
                '200':
                  description: Success
                  content:
                    application/json:
                      schema:
                        allOf:
                          - $ref: '#/components/schemas/FormIdentifier'
        components:
          schemas:
            FormIdentifier:
              type: object
              required: [type, id]
              properties:
                type:
                  type: string
                  enum: [forms]
                id:
                  type: string
        YAML;

    private OpenApiValidatorInterface $validator;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    #[Test]
    public function null_body_reports_a_structured_error_with_a_data_path(): void
    {
        $exception = $this->assertRejects('null');

        $this->assertNotEmpty($exception->getErrors());
        $this->assertNotSame('', $this->validator->getFormattedErrors($exception));
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function failing_body_reports_each_error_exactly_once(): void
    {
        $exception = $this->assertRejects('{"type":"widgets"}');

        $identities = array_map(
            static fn($error): string => $error->keyword() . '@' . $error->dataPath(),
            $exception->getErrors(),
        );

        $this->assertCount(1, $identities);
        $this->assertCount(count(array_unique($identities)), $identities);
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    private function assertRejects(string $body): ValidationException
    {
        $request = $this->psrFactory->createServerRequest('GET', '/forms');
        $operation = $this->validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));

        try {
            $this->validator->validateResponse($response, $operation);
        } catch (ValidationException $e) {
            return $e;
        }

        $this->fail('Expected the response body to be rejected');
    }
}
