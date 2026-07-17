<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function gc_collect_cycles;

/**
 * Regression tests for P-027: cycle detection must use identity-based
 * WeakMap rather than spl_object_id() (which is recycled by PHP GC and
 * caused cache-key collisions / spurious cycle detection).
 */
final class CycleDetectionWeakMapRegressionTest extends TestCase
{
    #[Test]
    public function schema_has_ref_terminates_for_cyclic_object_graph_after_gc(): void
    {
        $schema = $this->createSchemaSelfReferencingViaComposition();

        gc_collect_cycles();

        $resolver = new RefResolver();

        $hasRef = $resolver->schemaHasRef($schema);

        self::assertFalse($hasRef, 'Self-referential via oneOf without $ref pointer must terminate without stack overflow and return false');
    }

    #[Test]
    public function schema_has_ref_distinguishes_independent_cyclic_graphs_after_gc(): void
    {
        $first = $this->createSchemaSelfReferencingViaComposition();
        $firstHasRef = new RefResolver()->schemaHasRef($first);

        gc_collect_cycles();

        $second = $this->createSchemaSelfReferencingViaComposition();
        $secondHasRef = new RefResolver()->schemaHasRef($second);

        self::assertFalse($firstHasRef);
        self::assertFalse($secondHasRef);
    }

    #[Test]
    public function schema_has_ref_terminates_for_deep_non_cyclic_tree(): void
    {
        $root = $this->createDeepBranchingTree(depth: 5, branching: 3);

        gc_collect_cycles();

        $resolver = new RefResolver();

        $hasRef = $resolver->schemaHasRef($root);

        self::assertFalse($hasRef);
    }

    #[Test]
    public function schema_has_discriminator_terminates_for_cyclic_object_graph_after_gc(): void
    {
        $schema = $this->createSchemaSelfReferencingViaComposition();
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(schemas: []),
        );

        gc_collect_cycles();

        $resolver = new RefResolver();

        $has = $resolver->schemaHasDiscriminator($schema, $document);

        self::assertFalse($has);
    }

    #[Test]
    public function repeated_schema_has_ref_calls_remain_stable_under_gc_pressure(): void
    {
        $resolver = new RefResolver();
        $previousResult = null;

        for ($i = 0; $i < 10; ++$i) {
            $schema = $this->createSchemaSelfReferencingViaComposition();
            $current = $resolver->schemaHasRef($schema);

            if (null !== $previousResult) {
                self::assertSame($previousResult, $current, 'Result must remain stable across calls under GC pressure');
            }

            $previousResult = $current;

            unset($schema);
            gc_collect_cycles();
        }

        self::assertFalse($previousResult);
    }

    #[Test]
    public function schema_validator_resolves_composition_with_cycle_and_gc_without_overflow(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'A' => new Schema(
                        type: 'object',
                        oneOf: [new Schema(ref: '#/components/schemas/B')],
                    ),
                    'B' => new Schema(
                        type: 'object',
                        allOf: [new Schema(ref: '#/components/schemas/A')],
                    ),
                ],
            ),
        );
        $statelessValidators = new StatelessValidatorRegistry($pool, BuiltinFormats::create());

        $validator = new SchemaValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $pool,
                refResolver: $refResolver,
                statelessValidators: $statelessValidators,
            ),
        );

        $schema = new Schema(ref: '#/components/schemas/A');

        gc_collect_cycles();

        try {
            $validator->validate(['any' => 'data'], $schema);
            self::assertTrue(true);
        } catch (SchemaDepthExceededException) {
            self::assertTrue(true, 'Depth guard fired cleanly without stack overflow');
        }
    }

    private function createSchemaSelfReferencingViaComposition(): Schema
    {
        $schema = new Schema(type: 'object');
        $schema = $schema->withOverrides(oneOf: [$schema]);

        return $schema;
    }

    private function createDeepBranchingTree(int $depth, int $branching): Schema
    {
        if ($depth <= 0) {
            return new Schema(type: 'string');
        }

        $properties = [];

        for ($i = 0; $i < $branching; ++$i) {
            $properties['p' . $i] = $this->createDeepBranchingTree($depth - 1, $branching);
        }

        return new Schema(type: 'object', properties: $properties);
    }
}
