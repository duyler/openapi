<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function count;

/**
 * @internal
 */
#[CoversClass(DiscriminatorValidator::class)]
final class DiscriminatorValidatorTest extends TestCase
{
    private DiscriminatorValidator $validator;
    private RefResolver $refResolver;
    private ValidatorPool $pool;
    private StatelessValidatorRegistry $statelessValidators;

    #[Override]
    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->validator = new DiscriminatorValidator(
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );
    }

    #[Test]
    public function implicit_mapping_without_title_selects_schema_by_ref_name(): void
    {
        // Arrange: Cat/Dog schemas have NO title; per OAS 3.2 the discriminator
        // value must match the schema name extracted from the $ref last segment.
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        // Act.
        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // Assert: implicit mapping by schema name 'Cat' (NOT title).
        // Anti-test: on the old schemaMatchesValue-by-title code this throws
        // UnknownDiscriminatorValueException because Cat has no title.
        $this->assertNull($exception, 'Implicit mapping must select Cat by $ref name even without title');
    }

    #[Test]
    public function implicit_mapping_ignores_title_when_title_differs_from_name(): void
    {
        // Arrange: Cat schema has title 'My Cat Schema' which does NOT match
        // discriminator value 'Cat'. Per OAS 3.2 implicit mapping uses the
        // schema name (last $ref segment), title is ignored entirely.
        $catSchema = new Schema(
            title: 'My Cat Schema',
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        // Act.
        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // Assert: passes because mapping is by name 'Cat', title ignored.
        // Anti-test: on old code matching by title, value 'Cat' !== title
        // 'My Cat Schema' → UnknownDiscriminatorValueException.
        $this->assertNull($exception, 'Implicit mapping must ignore title and match by schema name');
    }

    #[Test]
    public function explicit_mapping_takes_priority_over_implicit_name_match(): void
    {
        // Arrange: explicit mapping 'kitten' → Cat. Discriminator value 'kitten'
        // is NOT equal to schema name 'Cat', so implicit mapping would miss;
        // explicit mapping must win.
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: ['kitten' => '#/components/schemas/Cat'],
            ),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $kittenData = ['petType' => 'kitten', 'name' => 'Tom'];

        // Act.
        $exception = null;

        try {
            $this->validator->validate($kittenData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // Assert: explicit mapping resolves 'kitten' → Cat schema.
        $this->assertNull($exception, 'Explicit mapping must take priority over implicit name match');
    }

    #[Test]
    public function unknown_discriminator_value_throws_exception(): void
    {
        // Arrange: 'Bird' is neither an explicit mapping key nor a schema name.
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        // Assert.
        $this->expectException(UnknownDiscriminatorValueException::class);
        $this->expectExceptionMessage('Unknown discriminator value "Bird"');

        // Act.
        $this->validator->validate(['petType' => 'Bird'], $petSchema, $document);
    }

    #[Test]
    public function parent_required_property_enforced_after_child_match(): void
    {
        // Arrange: parent Pet declares required ['petType']; child Cat declares
        // required ['meow']. Data has petType='Cat' (child selected) but omits
        // 'meow' → child-required error (EI-013 invariant: discriminator does
        // NOT disable parent+child validation).
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name', 'meow'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'meow' => new Schema(type: 'boolean'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            required: ['petType'],
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catWithoutMeow = ['petType' => 'Cat', 'name' => 'Tom'];

        // Act.
        try {
            $this->validator->validate($catWithoutMeow, $petSchema, $document);
            $this->fail('Cat schema selected via implicit name mapping should enforce its required fields');
        } catch (ValidationException $e) {
            // Assert: child-required 'meow' surfaces as a required-keyword error.
            $errors = $e->getErrors();
            $this->assertGreaterThan(0, count($errors));
            $this->assertSame('required', $errors[0]->keyword());
            $this->assertStringContainsString('meow', $errors[0]->message());
        }
    }

    #[Test]
    public function default_mapping_used_when_no_explicit_or_implicit_match(): void
    {
        // Arrange: discriminator.defaultMapping kicks in when neither explicit
        // mapping nor implicit schema-name match resolves the value.
        $defaultSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            discriminator: new Discriminator(
                propertyName: 'petType',
                defaultMapping: '#/components/schemas/Default',
            ),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Default' => $defaultSchema,
                ],
            ),
        );

        $unknownData = ['petType' => 'unknown', 'name' => 'Tom'];

        // Act.
        $exception = null;

        try {
            $this->validator->validate($unknownData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // Assert: 'unknown' resolves to Default via defaultMapping.
        $this->assertNull($exception, 'defaultMapping must resolve unknown values when no explicit/implicit match exists');
    }

    #[Test]
    public function inline_schema_without_ref_in_oneOf_is_skipped_during_implicit_mapping(): void
    {
        // Arrange: first candidate is an inline schema (no $ref) which MUST be
        // skipped; second candidate Cat matches implicitly by schema name.
        $catSchema = new Schema(
            type: 'object',
            required: ['petType', 'name'],
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            oneOf: [
                new Schema(type: 'object', properties: ['noise' => new Schema(type: 'string')]),
                new Schema(ref: '#/components/schemas/Cat'),
            ],
            discriminator: new Discriminator(propertyName: 'petType'),
        );

        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $catData = ['petType' => 'Cat', 'name' => 'Tom'];

        // Act.
        $exception = null;

        try {
            $this->validator->validate($catData, $petSchema, $document);
        } catch (Throwable $e) {
            $exception = $e;
        }

        // Assert: inline schema skipped (no $ref), Cat selected by name.
        $this->assertNull($exception, 'Inline schema without $ref must be skipped during implicit mapping');
    }
}
