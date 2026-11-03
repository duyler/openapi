<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Test\Support\Psr7RequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ReadOnlyRequiredRelaxationRegressionTest extends TestCase
{
    private const string SPEC_30 = <<<'YAML'
openapi: 3.0.3
info:
  title: ReadOnly Required Relaxation
  version: '1'
paths:
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [id]
              properties:
                id:
                  type: integer
                  readOnly: true
      responses:
        '200':
          description: OK
YAML;

    private const string SPEC_31 = <<<'YAML'
openapi: 3.1.0
info:
  title: ReadOnly Required No Relaxation
  version: '1'
paths:
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [id]
              properties:
                id:
                  type: integer
                  readOnly: true
      responses:
        '200':
          description: OK
YAML;

    private const string SPEC_30_ALLOF_BOUNDARY = <<<'YAML'
openapi: 3.0.3
info:
  title: ReadOnly Required AllOf Boundary
  version: '1'
paths:
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              allOf:
                - required: [id]
              properties:
                id:
                  type: integer
                  readOnly: true
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function missing_read_only_required_property_is_valid_in_30_request(): void
    {
        $validator = $this->build(self::SPEC_30);

        $caught = null;

        try {
            $validator->validateRequest(Psr7RequestFactory::createJsonRequest('POST', '/items', []));
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            'OAS 3.0.3: required takes effect on the response only — a missing readOnly property must not fail request validation.',
        );
    }

    #[Test]
    public function present_read_only_property_in_request_is_invalid_in_30(): void
    {
        $validator = $this->build(self::SPEC_30);

        $caught = null;

        try {
            $validator->validateRequest(Psr7RequestFactory::createJsonRequest('POST', '/items', ['id' => 1]));
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Sending a readOnly property in a request body must remain an error (ReadOnlyWriteOnlyValidator).',
        );
    }

    #[Test]
    public function opt_out_enforces_required_for_read_only_in_30(): void
    {
        $validator = $this->build(self::SPEC_30, static fn(OpenApiValidatorBuilder $b) => $b->enforceRequiredForReadOnly());

        $caught = null;

        try {
            $validator->validateRequest(Psr7RequestFactory::createJsonRequest('POST', '/items', []));
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'enforceRequiredForReadOnly() must restore the 0.10 strict behavior (missing readOnly+required → INVALID).',
        );
    }

    #[Test]
    public function missing_read_only_required_property_is_invalid_in_31_request(): void
    {
        $validator = $this->build(self::SPEC_31);

        $caught = null;

        try {
            $validator->validateRequest(Psr7RequestFactory::createJsonRequest('POST', '/items', []));
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            '3.1 dialect has no readOnly relaxation — missing readOnly+required must remain INVALID.',
        );
    }

    #[Test]
    public function read_only_declared_in_parent_schema_does_not_relax_allof_required(): void
    {
        $validator = $this->build(self::SPEC_30_ALLOF_BOUNDARY);

        $caught = null;

        try {
            $validator->validateRequest(Psr7RequestFactory::createJsonRequest('POST', '/items', []));
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Conservative boundary: readOnly declared in the parent schema must not relax required in an allOf subschema.',
        );
    }

    private function build(string $spec, ?callable $configure = null): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString($spec);

        $builder = null === $configure ? $builder : $configure($builder);

        return $builder->build();
    }
}
