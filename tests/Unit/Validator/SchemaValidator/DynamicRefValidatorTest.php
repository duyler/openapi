<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\DynamicRefValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RefResolvingSchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use stdClass;

use function array_map;

final class DynamicRefValidatorTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function is_applicable_only_for_dynamic_ref(): void
    {
        $validator = $this->createValidatorWithoutDocument();

        self::assertTrue($validator->isApplicable(new Schema(dynamicRef: '#node')));
        self::assertFalse($validator->isApplicable(new Schema(type: 'string')));
        self::assertFalse($validator->isApplicable(new Schema()));
    }

    #[Test]
    public function validate_without_dynamic_ref_is_noop(): void
    {
        $validator = $this->createValidatorWithoutDocument();

        $validator->validate('anything', new Schema(type: 'string'));

        self::addToAssertionCount(1);
    }

    #[Test]
    public function validate_without_document_is_unresolvable(): void
    {
        $validator = $this->createValidatorWithoutDocument();

        $this->expectException(UnresolvableRefException::class);

        $validator->validate('data', new Schema(dynamicRef: '#node'));
    }

    #[Test]
    public function self_recursive_resource_terminates_without_stack_overflow(): void
    {
        $resource = new Schema(
            id: 'https://example.com/self',
            dynamicAnchor: 'node',
            dynamicRef: '#node',
        );

        $rootValidator = $this->rootValidatorForResource($resource);

        $rootValidator->validate('data', $resource);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function repeated_self_entry_deduplicates_frame_and_leave_pops_outer_frame(): void
    {
        $resource = new Schema(id: 'https://example.com/self', dynamicAnchor: 'node');
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool));

        $context->enterDynamicScope($resource);
        self::assertSame([$resource], $context->dynamicScope());

        $context->enterDynamicScope($resource);
        self::assertSame([$resource], $context->dynamicScope());

        $context->leaveDynamicScope();
        self::assertSame([], $context->dynamicScope());

        $context->leaveDynamicScope();
        self::assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function wrapper_with_id_enters_scope_before_ref_resolution(): void
    {
        $target = new Schema(id: 'https://example.com/target', title: 'Target');
        $wrapper = new Schema(
            id: 'https://example.com/inner',
            ref: '#/components/schemas/Target',
            dynamicAnchor: 'marker',
        );
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('DynamicRefValidatorTest', '1.0.0'),
            components: new Components(schemas: ['Target' => $target, 'Subject' => $wrapper]),
        );

        $observations = [];
        $probe = new class ($observations) implements SchemaValidatorInterface {
            /** @param list<array{id: ?string, scope: list<string>}> $observations */
            public function __construct(private array &$observations) {}

            #[Override]
            public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
            {
                $scope = null === $context ? [] : $context->dynamicScope();

                $this->observations[] = [
                    'id' => $schema->id,
                    'scope' => array_map(
                        static fn(Schema $resource): ?string => $resource->id,
                        $scope,
                    ),
                ];
            }
        };
        $resolver = new RefResolver();
        $inner = new StatelessValidatorDispatcher(validators: [$probe], pool: $this->pool);
        $wrapped = new RefResolvingSchemaValidator($inner, $resolver, $document);

        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool));

        $wrapped->validate(['any' => 'data'], $wrapper, $context);

        self::assertNotEmpty($observations);

        self::assertSame('https://example.com/target', $observations[0]['id']);
        self::assertSame(
            ['https://example.com/inner', 'https://example.com/target'],
            $observations[0]['scope'],
        );
    }

    #[Test]
    public function paradoxical_not_with_self_dynamic_ref_terminates_with_domain_depth_exception(): void
    {
        $paradox = new Schema(
            id: 'https://example.com/paradox',
            dynamicAnchor: 'node',
            not: new Schema(dynamicRef: '#node'),
        );

        $rootValidator = $this->rootValidatorForResource($paradox);

        $this->expectException(SchemaDepthExceededException::class);

        $rootValidator->validate('data', $paradox);
    }

    #[Test]
    public function deep_dynamic_ref_tree_raises_depth_exception(): void
    {
        $resource = new Schema(
            id: 'https://example.com/deep',
            dynamicAnchor: 'node',
            type: 'object',
            properties: ['child' => new Schema(dynamicRef: '#node')],
        );

        $rootValidator = $this->rootValidatorForResource($resource);

        $data = [];
        $cursor = &$data;

        for ($level = 0; $level <= ValidationContext::MAX_DEPTH + 2; ++$level) {
            $cursor['child'] = [];
            $cursor = &$cursor['child'];
        }

        unset($cursor);

        $this->expectException(SchemaDepthExceededException::class);

        $rootValidator->validate($data, $resource);
    }

    #[Test]
    public function resolved_dynamic_anchor_target_validates_through_root_path(): void
    {
        $root = new Schema(
            id: 'https://example.com/root',
            dynamicAnchor: 'node',
            type: 'object',
            properties: ['child' => new Schema(dynamicRef: '#node')],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $rootValidator->validate(['child' => ['child' => []]], $root);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function anchor_mismatch_is_caught_only_through_dynamic_scope(): void
    {
        $root = new Schema(
            id: 'https://example.com/root',
            dynamicAnchor: 'node',
            type: 'object',
            properties: ['child' => new Schema(dynamicRef: '#node')],
            unevaluatedProperties: false,
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $this->expectException(ValidationException::class);

        $rootValidator->validate(['child' => ['daat' => 1]], $root);
    }

    #[Test]
    public function non_validatable_instance_is_rejected(): void
    {
        $resource = new Schema(id: 'https://example.com/self', dynamicAnchor: 'node');
        $validator = $this->createValidatorWithDocument($resource);

        $this->expectException(InvalidDataTypeException::class);

        $validator->validate(new stdClass(), new Schema(dynamicRef: '#node'));
    }

    #[Test]
    public function document_wide_fallback_resolves_anchor_out_of_frame_resource(): void
    {
        $root = new Schema(
            id: 'https://example.com/wide-root',
            dynamicAnchor: 'shared',
            properties: ['value' => new Schema(
                id: 'b',
                dynamicRef: '#shared',
            )],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $rootValidator->validate(['value' => 'ok'], $root);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function document_wide_fallback_validates_against_found_anchor(): void
    {
        $root = new Schema(
            id: 'https://example.com/wide-strict',
            dynamicAnchor: 'shared',
            properties: ['value' => new Schema(id: 'b', dynamicRef: '#shared')],
            unevaluatedProperties: false,
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $this->expectException(ValidationException::class);

        $rootValidator->validate(['value' => ['x' => 1]], $root);
    }

    #[Test]
    public function pointer_fragment_failure_rethrows_without_cross_document_fallback(): void
    {
        $root = new Schema(
            id: 'https://example.com/pointer-miss',
            properties: ['value' => new Schema(dynamicRef: '#/$defs/missing')],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $this->expectException(UnresolvableRefException::class);

        $rootValidator->validate(['value' => 'ok'], $root);
    }

    #[Test]
    public function chain_resources_enter_scope_regardless_of_defs_order(): void
    {
        $root = new Schema(
            id: 'https://example.com/avoid-root',
            ref: 'first#/$defs/stuff',
            defs: [
                'third' => new Schema(
                    id: 'third',
                    defs: [
                        'stuff' => new Schema(dynamicRef: '#length'),
                        'length' => new Schema(dynamicAnchor: 'length', maxLength: 3),
                    ],
                ),
                'first' => new Schema(
                    id: 'first',
                    defs: [
                        'stuff' => new Schema(ref: 'second#/$defs/stuff'),
                        'length' => new Schema(maxLength: 1),
                    ],
                ),
                'second' => new Schema(
                    id: 'second',
                    defs: [
                        'stuff' => new Schema(ref: 'third#/$defs/stuff'),
                        'length' => new Schema(dynamicAnchor: 'length', maxLength: 2),
                    ],
                ),
            ],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $rootValidator->validate('hi', $root);

        $this->expectException(ValidationException::class);

        $rootValidator->validate('hey', $root);
    }

    #[Test]
    public function pointer_fragment_behaves_like_ref(): void
    {
        $root = new Schema(
            id: 'https://example.com/pointer',
            properties: ['value' => new Schema(dynamicRef: '#/$defs/thing')],
            defs: ['thing' => new Schema(type: 'string')],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $this->expectException(ValidationException::class);

        $rootValidator->validate(['value' => 42], $root);
    }

    #[Test]
    public function pointer_fragment_validates_target_constraints(): void
    {
        $root = new Schema(
            id: 'https://example.com/pointer-valid',
            properties: ['value' => new Schema(dynamicRef: '#/$defs/thing')],
            defs: ['thing' => new Schema(type: 'string')],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $rootValidator->validate(['value' => 'ok'], $root);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function fragment_less_dynamic_ref_behaves_like_ref(): void
    {
        $root = new Schema(
            id: 'https://example.com/bare',
            properties: ['value' => new Schema(dynamicRef: 'thing')],
            defs: ['thing' => new Schema(type: 'string', id: 'thing')],
        );

        $rootValidator = $this->rootValidatorForResource($root);

        $rootValidator->validate(['value' => 'ok'], $root);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function validate_without_context_resolves_without_dynamic_scope(): void
    {
        $resource = new Schema(id: 'https://example.com/self', dynamicAnchor: 'node');
        $validator = $this->createValidatorWithDocument($resource);

        $validator->validate('data', new Schema(dynamicRef: '#node'));

        self::addToAssertionCount(1);
    }

    #[Test]
    public function null_context_root_validation_applies_30_nullable_semantics(): void
    {
        $target = new Schema(
            type: 'object',
            properties: ['a' => new Schema(nullable: true)],
        );
        $validator = $this->createValidatorWithDocument($target, '3.0.3');

        $this->expectException(InvalidDataTypeException::class);

        $validator->validate(['a' => null], new Schema(dynamicRef: '#/components/schemas/Subject'));
    }

    #[Test]
    public function null_context_root_validation_keeps_31_nullable_semantics(): void
    {
        $target = new Schema(
            type: 'object',
            properties: ['a' => new Schema(nullable: true)],
        );
        $validator = $this->createValidatorWithDocument($target);

        $validator->validate(['a' => null], new Schema(dynamicRef: '#/components/schemas/Subject'));

        self::addToAssertionCount(1);
    }

    #[Test]
    public function non_registry_resolver_exhausts_fallback_and_rethrows(): void
    {
        $resolver = $this->createStub(RefResolverInterface::class);
        $resolver->method('resolve')->willThrowException(new UnresolvableRefException('#node', 'unresolvable'));

        $resource = new Schema(id: 'https://example.com/self', dynamicAnchor: 'node');

        $validator = new DynamicRefValidator(new SchemaValidatorDependencies(
            pool: $this->pool,
            refResolver: $resolver,
            statelessValidators: new StatelessValidatorRegistry($this->pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            document: new OpenApiDocument(
                '3.1.0',
                new InfoObject('DynamicRefValidatorTest', '1.0.0'),
                components: new Components(schemas: ['Subject' => $resource]),
            ),
        ));

        $this->expectException(UnresolvableRefException::class);

        $validator->validate('data', new Schema(dynamicRef: '#node'));
    }

    private function createValidatorWithoutDocument(): DynamicRefValidator
    {
        return new DynamicRefValidator(new SchemaValidatorDependencies(
            pool: $this->pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($this->pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        ));
    }

    private function createValidatorWithDocument(Schema $resource, string $openapiVersion = '3.1.0'): DynamicRefValidator
    {
        $pool = new ValidatorPool();
        $document = new OpenApiDocument(
            $openapiVersion,
            new InfoObject('DynamicRefValidatorTest', '1.0.0'),
            components: new Components(schemas: ['Subject' => $resource]),
        );

        return new DynamicRefValidator(new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
            document: $document,
        ));
    }

    private function rootValidatorForResource(Schema $resource): SchemaValidatorWithContext
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('DynamicRefValidatorTest', '1.0.0'),
            components: new Components(schemas: ['Subject' => $resource]),
        );
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $document,
            refResolver: $refResolver,
        );

        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
            formatRegistry: BuiltinFormats::create(),
            document: $document,
        );

        return $dependencies->rootSchemaValidator(
            $dependencies->document,
            new ValidatorConfiguration(),
        );
    }
}
