<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RefResolverOverrideTest extends TestCase
{
    private RefResolver $resolver;
    private OpenApiDocument $document;
    private SchemaValidator $schemaValidator;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
        $this->schemaValidator = new SchemaValidator(
            new ValidatorPool(),
            BuiltinFormats::create(),
        );

        $this->document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => new Schema(
                        type: 'object',
                        description: 'Original description',
                        title: 'Original Title',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ],
                parameters: [
                    'userId' => new Parameter(
                        name: 'userId',
                        in: 'path',
                        required: true,
                        schema: new Schema(type: 'string'),
                    ),
                ],
                responses: [
                    'success' => new Response(
                        description: 'Success response',
                    ),
                ],
            ),
        );
    }

    #[Test]
    public function resolve_parameter_returns_parameter_object(): void
    {
        $result = $this->resolver->resolveParameter('#/components/parameters/userId', $this->document);

        self::assertInstanceOf(Parameter::class, $result);
        self::assertSame('userId', $result->name);
        self::assertSame('path', $result->in);
    }

    #[Test]
    public function resolve_parameter_throws_for_non_parameter_ref(): void
    {
        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveParameter('#/components/schemas/User', $this->document);
    }

    #[Test]
    public function resolve_response_returns_response_object(): void
    {
        $result = $this->resolver->resolveResponse('#/components/responses/success', $this->document);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame('Success response', $result->description);
    }

    #[Test]
    public function resolve_response_throws_for_non_response_ref(): void
    {
        $this->expectException(UnresolvableRefException::class);

        $this->resolver->resolveResponse('#/components/schemas/User', $this->document);
    }

    #[Test]
    public function resolve_schema_with_override_returns_schema_as_is_when_no_ref(): void
    {
        $schema = new Schema(type: 'string');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame($schema, $result);
    }

    #[Test]
    public function resolve_schema_with_override_resolves_ref(): void
    {
        $schema = new Schema(ref: '#/components/schemas/User');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertNull($result->ref);
        self::assertSame('object', $result->type);
    }

    #[Test]
    public function resolve_schema_with_override_applies_ref_description(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refDescription: 'Custom description',
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Custom description', $result->description);
    }

    #[Test]
    public function resolve_schema_with_override_uses_resolved_description_when_no_override(): void
    {
        $schema = new Schema(ref: '#/components/schemas/User');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Original description', $result->description);
    }

    #[Test]
    public function resolve_parameter_with_override_returns_parameter_as_is_when_no_ref(): void
    {
        $parameter = new Parameter(name: 'test', in: 'query');

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertSame($parameter, $result);
    }

    #[Test]
    public function resolve_parameter_with_override_resolves_ref(): void
    {
        $parameter = new Parameter(ref: '#/components/parameters/userId');

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertNull($result->ref);
        self::assertSame('userId', $result->name);
    }

    #[Test]
    public function resolve_parameter_with_override_applies_ref_description(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/userId',
            refDescription: 'Custom param description',
        );

        $result = $this->resolver->resolveParameterWithOverride($parameter, $this->document);

        self::assertSame('Custom param description', $result->description);
    }

    #[Test]
    public function resolve_response_with_override_returns_response_as_is_when_no_ref(): void
    {
        $response = new Response(description: 'Local response');

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame($response, $result);
    }

    #[Test]
    public function resolve_response_with_override_resolves_ref(): void
    {
        $response = new Response(ref: '#/components/responses/success');

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertNull($result->ref);
        self::assertSame('Success response', $result->description);
    }

    #[Test]
    public function resolve_response_with_override_applies_ref_summary(): void
    {
        $response = new Response(
            ref: '#/components/responses/success',
            refSummary: 'Custom summary',
        );

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame('Custom summary', $result->summary);
    }

    #[Test]
    public function resolve_response_with_override_applies_ref_description(): void
    {
        $response = new Response(
            ref: '#/components/responses/success',
            refDescription: 'Custom response description',
        );

        $result = $this->resolver->resolveResponseWithOverride($response, $this->document);

        self::assertSame('Custom response description', $result->description);
    }

    #[Test]
    public function resolve_schema_with_override_applies_ref_summary_as_title(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'Override Title',
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame('Override Title', $result->title);
    }

    /**
     * SPEC-02 / JSON Schema 2020-12 §8.2.3: a sibling `minLength` next to
     * `$ref` must be applied as an additional constraint on the resolved
     * schema. Without sibling merge the constraint would be silently
     * dropped, allowing shorter values through.
     */
    #[Test]
    public function resolve_schema_with_override_applies_sibling_min_length(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            minLength: 5,
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $this->document);

        self::assertSame(5, $result->minLength);
    }

    /**
     * SPEC-02 / JSON Schema 2020-12 §8.2.3: sibling `properties` next to
     * `$ref` are merged shallowly into the resolved schema's properties.
     * Sibling-defined property schemas are added on top of the resolved
     * ones; on key collision the sibling entry wins.
     */
    #[Test]
    public function resolve_schema_with_override_merges_sibling_properties(): void
    {
        $resolvedRoleSchema = new Schema(type: 'integer');
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'object',
            title: 'BaseUser',
            properties: ['role' => $resolvedRoleSchema],
        ));

        $siblingRoleSchema = new Schema(type: 'string');
        $schema = new Schema(
            ref: '#/components/schemas/User',
            properties: ['role' => $siblingRoleSchema],
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNotNull($result->properties);
        self::assertArrayHasKey('role', $result->properties);
        self::assertSame($siblingRoleSchema, $result->properties['role']);
    }

    /**
     * SPEC-02 / JSON Schema 2020-12 §8.2.3: a sibling `enum` and the
     * referenced schema's `enum` both apply, so the merged schema must
     * accept only the intersection. Without intersection logic either
     * side would be silently dropped.
     */
    #[Test]
    public function resolve_schema_with_override_intersects_sibling_enum(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'integer',
            enum: [1, 2, 3],
        ));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            enum: [1, 2],
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertSame([1, 2], $result->enum);
    }

    /**
     * SPEC-02: a pure `$ref` without siblings resolves unchanged — the
     * resolved schema is returned as-is (only the `$ref` family is
     * cleared because resolution is complete).
     */
    #[Test]
    public function resolve_schema_with_override_passthrough_without_siblings(): void
    {
        $resolved = new Schema(
            type: 'object',
            title: 'Original Title',
            description: 'Original description',
            pattern: '^[a-z]+$',
            properties: ['name' => new Schema(type: 'string')],
        );
        $document = $this->buildDocumentWithSchema($resolved);

        $schema = new Schema(ref: '#/components/schemas/User');

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNull($result->ref);
        self::assertSame('object', $result->type);
        self::assertSame('Original Title', $result->title);
        self::assertSame('Original description', $result->description);
        self::assertSame('^[a-z]+$', $result->pattern);
        self::assertNotNull($result->properties);
        self::assertArrayHasKey('name', $result->properties);
    }

    /**
     * SPEC-02: a sibling `title` overrides the resolved schema's title
     * per the historical OpenAPI override semantics preserved by the
     * merge strategy.
     */
    #[Test]
    public function resolve_schema_with_override_sibling_title_overrides_resolved(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'object',
            title: 'Resolved',
        ));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            title: 'Sibling',
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertSame('Sibling', $result->title);
    }

    /**
     * SPEC-02: resolved-only fields are preserved when the sibling does
     * not declare them. The sibling wins only when it sets a value.
     */
    #[Test]
    public function resolve_schema_with_override_preserves_resolved_only_fields(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'string',
            pattern: '^[a-z]+$',
            minLength: 2,
        ));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            maxLength: 10,
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertSame('^[a-z]+$', $result->pattern);
        self::assertSame(2, $result->minLength);
        self::assertSame(10, $result->maxLength);
    }

    /**
     * SPEC-02: sibling `required` is unioned with the resolved schema's
     * `required` so that both required lists apply.
     */
    #[Test]
    public function resolve_schema_with_override_unions_sibling_required(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'object',
            required: ['name'],
        ));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            required: ['role'],
        );

        $result = $this->resolver->resolveSchemaWithOverride($schema, $document);

        self::assertNotNull($result->required);
        $required = $result->required;
        sort($required);
        self::assertSame(['name', 'role'], $required);
    }

    /**
     * SPEC-02 end-to-end: a sibling `minLength` next to `$ref` must be
     * enforced against actual data after the merge. Without sibling merge
     * the constraint would be silently dropped and short values would
     * pass downstream validation.
     */
    #[Test]
    public function resolve_schema_with_override_validates_min_length_via_validator(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(type: 'string'));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            minLength: 5,
        );

        $merged = $this->resolver->resolveSchemaWithOverride($schema, $document);

        try {
            $this->schemaValidator->validate('ab', $merged);
            self::fail('Expected validation to fail with MinLengthError or ValidationException for value shorter than merged minLength=5');
        } catch (MinLengthError | ValidationException $e) {
            self::addToAssertionCount(1);
        }
    }

    /**
     * SPEC-02 end-to-end: a sibling `enum` next to `$ref` intersects with
     * the resolved schema's `enum`, and the merged schema must reject
     * values that were valid in the resolved enum but absent from the
     * sibling enum.
     */
    #[Test]
    public function resolve_schema_with_override_validates_enum_intersection_via_validator(): void
    {
        $document = $this->buildDocumentWithSchema(new Schema(
            type: 'integer',
            enum: [1, 2, 3],
        ));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            enum: [1, 2],
        );

        $merged = $this->resolver->resolveSchemaWithOverride($schema, $document);

        try {
            $this->schemaValidator->validate(3, $merged);
            self::fail('Expected validation to fail with EnumError or ValidationException for value outside merged enum intersection [1, 2]');
        } catch (EnumError | ValidationException $e) {
            self::addToAssertionCount(1);
        }
    }

    private function buildDocumentWithSchema(Schema $resolved): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: ['User' => $resolved],
            ),
        );
    }
}
