<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E\OpenApi32;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function sprintf;

/**
 * OA-05: OpenAPI 3.2 $self reference — base URI for the document.
 *
 * Current behavior (documented): $self is parsed, validated as URL, stored on
 * OpenApiDocument. RefResolver::getBaseUri() returns it; resolveRelativeRef()
 * uses it as the base for combining relative refs and throws RefResolutionException
 * when no $self is set. Recursive schemas referencing themselves resolve without
 * infinite loops thanks to RefResolver's $visited map.
 */
final class SelfReferenceTest extends TestCase
{
    private const string SELF_BASE_URI = 'https://api.example.com/schemas/petstore.json';

    private const string SPEC_WITH_SELF_AND_RECURSION = <<<'YAML'
openapi: 3.2.0
$self: https://api.example.com/schemas/petstore.json
info:
  title: Recursive Tree API
  version: 1.0.0
paths: {}
components:
  schemas:
    TreeNode:
      type: object
      required:
        - id
      properties:
        id:
          type: string
        children:
          type: array
          items:
            $ref: '#/components/schemas/TreeNode'
YAML;

    private const string SPEC_WITHOUT_SELF = <<<'YAML'
openapi: 3.2.0
info:
  title: No Self API
  version: 1.0.0
paths: {}
components:
  schemas:
    Item:
      type: object
      required:
        - id
      properties:
        id:
          type: string
YAML;

    private OpenApiValidator $validatorWithSelf;

    #[Override]
    protected function setUp(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_SELF_AND_RECURSION)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        $this->validatorWithSelf = $validator;
    }

    #[Test]
    public function build_with_self_stores_value_on_document(): void
    {
        self::assertSame(self::SELF_BASE_URI, $this->validatorWithSelf->getDocument()->self);
    }

    #[Test]
    public function build_without_self_leaves_field_null(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITHOUT_SELF)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        self::assertNull($validator->getDocument()->self);
    }

    #[Test]
    public function build_with_invalid_self_uri_throws_invalid_schema_exception(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
$self: not-a-valid-url
info:
  title: Bad Self API
  version: 1.0.0
paths: {}
YAML;

        $caught = null;

        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString($yaml)
                ->build();
        } catch (BuilderException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Invalid $self URI must surface through BuilderException');
        self::assertStringContainsString('$self', $caught->getMessage());

        $previous = $caught->getPrevious();

        self::assertNotNull($previous);
        self::assertStringContainsString('$self', $previous->getMessage());
    }

    #[Test]
    public function recursive_schema_validates_without_infinite_loop(): void
    {
        $data = [
            'id' => 'root',
            'children' => [
                [
                    'id' => 'child-1',
                    'children' => [
                        ['id' => 'grandchild-1', 'children' => []],
                    ],
                ],
                ['id' => 'child-2', 'children' => []],
            ],
        ];

        $succeeded = false;

        try {
            $this->validatorWithSelf->validateSchema($data, '#/components/schemas/TreeNode');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected recursive schema to validate, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function recursive_schema_rejects_invalid_nested_node(): void
    {
        $data = [
            'id' => 'root',
            'children' => [
                ['id' => 123, 'children' => []],
            ],
        ];

        $caught = null;

        try {
            $this->validatorWithSelf->validateSchema($data, '#/components/schemas/TreeNode');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Invalid nested node must be rejected');
        self::assertSame('type', $caught->getErrors()[0]->keyword());
    }

    #[Test]
    public function resolve_relative_ref_uses_self_as_base_uri(): void
    {
        $refResolver = new RefResolver();

        $resolved = $refResolver->resolveRelativeRef(
            'sub-schema.json',
            $this->validatorWithSelf->getDocument(),
        );

        self::assertSame('https://api.example.com/schemas/sub-schema.json', $resolved);
    }

    #[Test]
    public function get_base_uri_returns_self_value(): void
    {
        $refResolver = new RefResolver();

        self::assertSame(
            self::SELF_BASE_URI,
            $refResolver->getBaseUri($this->validatorWithSelf->getDocument()),
        );
    }

    #[Test]
    public function resolve_relative_ref_without_self_throws_ref_resolution_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITHOUT_SELF)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);

        $refResolver = new RefResolver();

        $caught = null;

        try {
            $refResolver->resolveRelativeRef('sub-schema.json', $validator->getDocument());
        } catch (RefResolutionException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'resolveRelativeRef without $self must throw');
        self::assertStringContainsString('$self', $caught->getMessage());
    }

    #[Test]
    public function self_is_round_tripped_through_json_serialize(): void
    {
        $serialized = $this->validatorWithSelf->getDocument()->jsonSerialize();

        self::assertArrayHasKey('$self', $serialized);
        self::assertSame(self::SELF_BASE_URI, $serialized['$self']);
    }
}
