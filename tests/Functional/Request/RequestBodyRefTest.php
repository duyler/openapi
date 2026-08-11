<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestBodyRefTest extends TestCase
{
    private const string REF_BODY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Referenced Request Body API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        $ref: '#/components/requestBodies/UserBody'
      responses:
        '201':
          description: Created
components:
  requestBodies:
    UserBody:
      required: true
      description: The user to create
      content:
        application/json:
          schema:
            type: object
            required:
              - name
            properties:
              name:
                type: string
YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function referenced_request_body_retains_the_pointer_after_parsing(): void
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build()
            ->getDocument();

        $requestBody = $document->paths->paths['/data']->post?->requestBody;

        self::assertNotNull($requestBody);
        self::assertSame('#/components/requestBodies/UserBody', $requestBody->ref);
    }

    #[Test]
    public function valid_body_against_referenced_request_body_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/data', $operation->path);
    }

    #[Test]
    public function body_violating_referenced_schema_is_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":12345}'));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function body_missing_property_required_by_referenced_schema_is_rejected(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{}'));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function required_flag_from_referenced_request_body_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(''));

        $this->expectException(MissingRequestBodyException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function media_type_from_referenced_request_body_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream('John Doe'));

        $this->expectException(UnsupportedMediaTypeException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function chained_request_body_reference_is_resolved(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Chained Request Body Ref API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        $ref: '#/components/requestBodies/Alias'
      responses:
        '201':
          description: Created
components:
  requestBodies:
    Alias:
      $ref: '#/components/requestBodies/UserBody'
    UserBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - name
            properties:
              name:
                type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($spec)->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":12345}'));

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function unresolvable_request_body_reference_throws(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Dangling Request Body Ref API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        $ref: '#/components/requestBodies/NoSuchBody'
      responses:
        '201':
          description: Created
components:
  requestBodies:
    UserBody:
      required: true
      content:
        application/json:
          schema:
            type: object
YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($spec)->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $this->expectException(UnresolvableRefException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function request_body_reference_pointing_at_a_schema_throws(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Mistyped Request Body Ref API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        $ref: '#/components/schemas/User'
      responses:
        '201':
          description: Created
components:
  schemas:
    User:
      type: object
YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($spec)->build();

        $request = $this->psrFactory->createServerRequest('POST', '/data')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"John Doe"}'));

        $this->expectException(UnresolvableRefException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function sibling_description_overrides_the_referenced_description(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Request Body Ref Override API
  version: 1.0.0
paths:
  /data:
    post:
      requestBody:
        $ref: '#/components/requestBodies/UserBody'
        description: Overridden at the call site
      responses:
        '201':
          description: Created
components:
  requestBodies:
    UserBody:
      description: Declared in components
      required: true
      content:
        application/json:
          schema:
            type: object
YAML;

        $document = OpenApiValidatorBuilder::create()->fromYamlString($spec)->build()->getDocument();
        $requestBody = $document->paths->paths['/data']->post?->requestBody;

        self::assertNotNull($requestBody);
        self::assertSame('Overridden at the call site', $requestBody->refDescription);
        self::assertNull($requestBody->description);
    }

    #[Test]
    public function webhook_with_a_referenced_request_body_is_validated(): void
    {
        $spec = <<<'YAML'
openapi: 3.1.0
info:
  title: Webhook Request Body Ref API
  version: 1.0.0
webhooks:
  userCreated:
    post:
      requestBody:
        $ref: '#/components/requestBodies/UserBody'
      responses:
        '200':
          description: OK
components:
  requestBodies:
    UserBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            required:
              - name
            properties:
              name:
                type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()->fromYamlString($spec)->build();

        $request = $this->psrFactory->createServerRequest('POST', '/hooks/user-created')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":12345}'));

        $this->expectException(ValidationException::class);
        $validator->validateWebhook($request, 'userCreated');
    }

    #[Test]
    public function referenced_request_body_serializes_back_to_a_reference_object(): void
    {
        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_BODY_SPEC)
            ->build()
            ->getDocument();

        $requestBody = $document->paths->paths['/data']->post?->requestBody;

        self::assertNotNull($requestBody);
        self::assertSame(
            ['$ref' => '#/components/requestBodies/UserBody'],
            $requestBody->jsonSerialize(),
        );
    }
}
