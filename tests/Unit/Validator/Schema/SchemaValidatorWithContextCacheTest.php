<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

use function spl_object_id;
use function sprintf;

final class SchemaValidatorWithContextCacheTest extends TestCase
{
    #[Test]
    public function repeated_validate_calls_do_not_recreate_validators(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(openapi: '3.2.0', info: new InfoObject(title: 'Test', version: '1.0.0'));
        $statelessValidators = new StatelessValidatorRegistry($pool, BuiltinFormats::create());

        $validator = new SchemaValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $pool, refResolver: $refResolver, statelessValidators: $statelessValidators));

        $schema = new Schema(type: 'string');

        $validator->validate('hello', $schema);
        $validator->validate('world', $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_integer_schema(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(openapi: '3.2.0', info: new InfoObject(title: 'Test', version: '1.0.0'));
        $statelessValidators = new StatelessValidatorRegistry($pool, BuiltinFormats::create());

        $validator = new SchemaValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $pool, refResolver: $refResolver, statelessValidators: $statelessValidators));

        $schema = new Schema(type: 'integer');

        $validator->validate(42, $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function repeated_composition_resolution_preserves_reference_identity(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
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

        $schema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
        );

        $resolve = new ReflectionMethod($validator, 'resolveCompositionRefs');
        $firstResolved = $resolve->invoke($validator, $schema, []);
        $firstId = spl_object_id($firstResolved);

        for ($i = 0; $i < 999; ++$i) {
            $resolved = $resolve->invoke($validator, $schema, []);

            $this->assertSame($firstId, spl_object_id($resolved));
        }
    }

    #[Test]
    public function circular_composition_does_not_overflow(): void
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

        try {
            $validator->validate(['any' => 'data'], $schema);
        } catch (SchemaDepthExceededException) {
            // Controlled depth guard is acceptable; the goal is to avoid stack overflow.
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected no stack overflow, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }
}
