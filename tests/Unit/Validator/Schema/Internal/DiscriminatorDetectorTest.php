<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\Internal\DiscriminatorDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakMap;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Closure;

use function is_array;

/**
 * @internal
 */
final class DiscriminatorDetectorTest extends TestCase
{
    private DiscriminatorDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DiscriminatorDetector();
    }

    #[Test]
    public function detect_discriminator_returns_true_when_direct_discriminator_present(): void
    {
        $schema = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_returns_false_when_no_discriminator(): void
    {
        $schema = new Schema(type: 'object');
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertFalse($has);
    }

    #[Test]
    public function detect_discriminator_walks_oneof_branch(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = new Schema(oneOf: [$discriminated]);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_walks_anyof_branch(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = new Schema(anyOf: [$discriminated]);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_walks_allof_branch(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = new Schema(allOf: [$discriminated]);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_walks_nested_property(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $parent = new Schema(properties: ['child' => $discriminated]);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $parent,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_walks_additional_properties_schema(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = new Schema(additionalProperties: $discriminated);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_walks_items(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = new Schema(items: $discriminated);
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_follows_ref_via_resolver_callable(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $document = $this->documentWithSchemas(['D' => $discriminated]);
        $refSchema = new Schema(ref: '#/components/schemas/D');

        $resolver = $this->coordinateResolver($document);

        [$has,] = $this->detector->detectDiscriminator(
            $refSchema,
            $document,
            new WeakMap(),
            $resolver,
            0,
        );

        self::assertTrue($has);
    }

    #[Test]
    public function detect_discriminator_returns_false_when_ref_unresolvable(): void
    {
        $refSchema = new Schema(ref: '#/components/schemas/Missing');
        $document = $this->emptyDocument();

        [$has,] = $this->detector->detectDiscriminator(
            $refSchema,
            $document,
            new WeakMap(),
            $this->unresolvingResolver(),
            0,
        );

        self::assertFalse($has);
    }

    #[Test]
    public function detect_discriminator_short_circuits_on_visited_cycle(): void
    {
        // Cycle via $ref: A -> B -> A. The visited WeakMap guard breaks it.
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/A');
        $document = $this->documentWithSchemas(['A' => $schemaA, 'B' => $schemaB]);

        $resolver = $this->coordinateResolver($document);

        [$has,] = $this->detector->detectDiscriminator(
            $schemaA,
            $document,
            new WeakMap(),
            $resolver,
            0,
        );

        self::assertFalse($has);
    }

    #[Test]
    public function detect_discriminator_enforces_max_depth(): void
    {
        $discriminated = new Schema(discriminator: new Discriminator(propertyName: 'kind'));
        $schema = $this->wrapInProperties($discriminated, 65);
        $document = $this->emptyDocument();

        $this->expectException(SchemaDepthExceededException::class);

        $this->detector->detectDiscriminator(
            $schema,
            $document,
            new WeakMap(),
            $this->nullResolver(),
            0,
        );
    }

    #[Test]
    public function detect_ref_returns_true_when_ref_present(): void
    {
        $schema = new Schema(ref: '#/components/schemas/User');

        [$has,] = $this->detector->detectRef($schema, new WeakMap(), 0);

        self::assertTrue($has);
    }

    #[Test]
    public function detect_ref_returns_false_when_no_ref(): void
    {
        $schema = new Schema(type: 'string');

        [$has,] = $this->detector->detectRef($schema, new WeakMap(), 0);

        self::assertFalse($has);
    }

    #[Test]
    public function detect_ref_walks_nested_property(): void
    {
        $leaf = new Schema(ref: '#/components/schemas/Leaf');
        $parent = new Schema(properties: ['child' => $leaf]);

        [$has,] = $this->detector->detectRef($parent, new WeakMap(), 0);

        self::assertTrue($has);
    }

    #[Test]
    public function detect_ref_enforces_max_depth(): void
    {
        $schema = $this->wrapInProperties(new Schema(ref: '#/x'), 65);

        $this->expectException(SchemaDepthExceededException::class);

        $this->detector->detectRef($schema, new WeakMap(), 0);
    }

    #[Test]
    public function iterate_sub_schemas_yields_composition_arrays_and_singles(): void
    {
        $items = new Schema(type: 'string');
        $notSchema = new Schema(type: 'null');
        $oneOfItem = new Schema(type: 'integer');
        $schema = new Schema(
            items: $items,
            not: $notSchema,
            oneOf: [$oneOfItem],
        );

        $yielded = [];
        foreach ($this->detector->iterateSubSchemas($schema) as $sub) {
            $yielded[] = $sub;
        }

        self::assertContains($items, $yielded);
        self::assertContains($notSchema, $yielded);
        self::assertContains($oneOfItem, $yielded);
    }

    #[Test]
    public function collect_single_sub_schemas_excludes_boolean_and_null_entries(): void
    {
        $schema = new Schema(
            items: new Schema(type: 'string'),
            additionalProperties: true,
            unevaluatedItems: null,
        );

        $collected = $this->detector->collectSingleSubSchemas($schema);

        self::assertCount(1, $collected);
        self::assertInstanceOf(Schema::class, $collected[0]);
    }

    private function emptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument('3.2.0', new InfoObject('Test', '1.0.0'));
    }

    private function documentWithSchemas(array $schemas): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('Test', '1.0.0'),
            components: new Components(schemas: $schemas),
        );
    }

    private function wrapInProperties(Schema $leaf, int $depth): Schema
    {
        $current = $leaf;
        for ($i = 0; $i < $depth - 1; ++$i) {
            $current = new Schema(properties: ['nested' => $current]);
        }

        return $current;
    }

    /**
     * Returns a resolver callable that never actually resolves anything
     * (used for tests that don't exercise ref-following).
     */
    private function nullResolver(): Closure
    {
        return static fn(string $ref, OpenApiDocument $d): Schema => new Schema();
    }

    /**
     * Resolver that walks the document's components.schemas map
     * (mirrors RefResolver::resolve for tests that need ref-following).
     */
    private function coordinateResolver(OpenApiDocument $document): Closure
    {
        return static function (string $ref) use ($document): Schema {
            $parts = explode('/', substr($ref, 2));
            /** @var object|array $current */
            $current = $document;
            foreach ($parts as $segment) {
                $decoded = str_replace(['~1', '~0'], ['/', '~'], $segment);
                if (is_array($current)) {
                    $current = $current[$decoded] ?? throw new UnresolvableRefException($ref, 'missing');
                } else {
                    $current = $current->$decoded ?? throw new UnresolvableRefException($ref, 'missing');
                }
            }

            return $current instanceof Schema ? $current : throw new UnresolvableRefException($ref, 'not a schema');
        };
    }

    /**
     * Returns a resolver callable that always throws UnresolvableRefException,
     * matching the behaviour of {@see RefResolver::resolve()}
     * for refs that cannot be resolved against the document.
     */
    private function unresolvingResolver(): Closure
    {
        return static fn(string $ref, OpenApiDocument $d): Schema
            => throw new UnresolvableRefException($ref, 'not found');
    }
}
