<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\Internal\DocumentNavigator;
use Duyler\OpenApi\Validator\Schema\Internal\ResourceRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WeakMap;

/**
 * @internal
 */
final class DocumentNavigatorTest extends TestCase
{
    private DocumentNavigator $navigator;

    protected function setUp(): void
    {
        $this->navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
        );
    }

    #[Test]
    public function navigate_walks_json_pointer_segments(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = $this->documentWithSchemas(['User' => $userSchema]);

        $result = $this->navigator->navigate($document, ['components', 'schemas', 'User']);

        self::assertSame($userSchema, $result);
    }

    #[Test]
    public function navigate_decodes_tilde_escape(): void
    {
        $tildeSchema = new Schema(title: 'Tilde');
        $parentSchema = new Schema(title: 'Parent', properties: ['~foo' => $tildeSchema]);
        $document = $this->documentWithSchemas(['Parent' => $parentSchema]);

        $result = $this->navigator->navigate(
            $document,
            ['components', 'schemas', 'Parent', 'properties', '~0foo'],
        );

        self::assertSame($tildeSchema, $result);
    }

    #[Test]
    public function navigate_decodes_slash_escape(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = $this->documentWithSchemas(['/user' => $userSchema]);

        $result = $this->navigator->navigate(
            $document,
            ['components', 'schemas', '~1user'],
        );

        self::assertSame($userSchema, $result);
    }

    #[Test]
    public function navigate_throws_when_array_schema_key_missing(): void
    {
        $document = $this->documentWithSchemas([]);

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Array key does not exist');

        $this->navigator->navigate($document, ['components', 'schemas', 'Missing']);
    }

    #[Test]
    public function navigate_throws_when_target_is_scalar_string(): void
    {
        $document = new OpenApiDocument('3.2.0', new InfoObject('Test', '1.0.0'));

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Value is not an object or array');

        $this->navigator->navigate($document, ['info', 'title']);
    }

    #[Test]
    public function navigate_throws_when_object_property_missing(): void
    {
        $document = new OpenApiDocument('3.2.0', new InfoObject('Test', '1.0.0'));

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Property does not exist');

        $this->navigator->navigate($document, ['invalid', 'path']);
    }

    #[Test]
    public function navigate_throws_when_array_key_missing(): void
    {
        $userSchema = new Schema(properties: ['list' => ['a' => new Schema(type: 'string')]]);
        $document = $this->documentWithSchemas(['User' => $userSchema]);

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Array key does not exist');

        $this->navigator->navigate(
            $document,
            ['components', 'schemas', 'User', 'properties', 'list', 'missing'],
        );
    }

    #[Test]
    public function navigate_throws_when_value_is_null(): void
    {
        $userSchema = new Schema(title: 'User', properties: ['address' => null]);
        $document = $this->documentWithSchemas(['User' => $userSchema]);

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Value is null');

        $this->navigator->navigate(
            $document,
            ['components', 'schemas', 'User', 'properties', 'address'],
        );
    }

    #[Test]
    public function navigate_enforces_max_depth_bound_on_recursion(): void
    {
        $leaf = new Schema(type: 'string');
        $current = $leaf;
        for ($i = 0; $i < 70; ++$i) {
            $current = new Schema(properties: ['p' => $current]);
        }
        $document = $this->documentWithSchemas(['Root' => $current]);

        $parts = array_merge(['components', 'schemas', 'Root']);
        for ($i = 0; $i < 70; ++$i) {
            $parts[] = 'properties';
            $parts[] = 'p';
        }

        $this->expectException(SchemaDepthExceededException::class);

        $this->navigator->navigate($document, $parts);
    }

    #[Test]
    public function format_circular_path_appends_closing_ref(): void
    {
        $result = $this->navigator->formatCircularPath(
            ['#/A' => true, '#/B' => true],
            '#/A',
        );

        self::assertSame('#/A -> #/B -> #/A', $result);
    }

    #[Test]
    public function resolve_ref_returns_cached_result_on_repeat_call(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = $this->documentWithSchemas(['User' => $userSchema]);

        /** @var WeakMap $cache */
        $cache = new WeakMap();

        [$first,] = $this->navigator->resolveRef('#/components/schemas/User', $document, [], $cache);
        [$second,] = $this->navigator->resolveRef('#/components/schemas/User', $document, [], $cache);

        self::assertSame($first, $second);
        self::assertSame($userSchema, $first);
    }

    #[Test]
    public function resolve_ref_detects_circular_reference(): void
    {
        $schemaA = new Schema(ref: '#/components/schemas/B');
        $schemaB = new Schema(ref: '#/components/schemas/A');
        $document = $this->documentWithSchemas(['A' => $schemaA, 'B' => $schemaB]);

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $this->navigator->resolveRef('#/components/schemas/A', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_ref_enforces_depth_on_linear_chain(): void
    {
        $schemas = [];
        for ($i = 0; $i < 100; ++$i) {
            $schemas['S' . $i] = $i < 99
                ? new Schema(ref: '#/components/schemas/S' . ($i + 1))
                : new Schema(title: 'Final', type: 'string');
        }
        $document = $this->documentWithSchemas($schemas);

        $this->expectException(SchemaDepthExceededException::class);

        $this->navigator->resolveRef('#/components/schemas/S0', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_ref_returns_external_resolver_result_for_non_local_ref(): void
    {
        $externalSchema = new Schema(title: 'External');
        $external = new readonly class ($externalSchema) implements ExternalRefResolverInterface {
            public function __construct(private Schema $schema) {}

            public function resolve(string $ref): Schema
            {
                return $this->schema;
            }
        };
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            externalRefResolver: $external,
        );

        [$result,] = $navigator->resolveRef('https://example.com/x.json', new OpenApiDocument('', new InfoObject('', '')), [], new WeakMap());

        self::assertSame($externalSchema, $result);
    }

    #[Test]
    public function resolve_ref_surfaces_external_security_violation_as_unresolvable(): void
    {
        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('External ref not resolved');

        $this->navigator->resolveRef(
            'https://example.com/x.json',
            new OpenApiDocument('', new InfoObject('', '')),
            [],
            new WeakMap(),
        );
    }

    #[Test]
    public function navigate_throws_when_final_target_is_container(): void
    {
        $document = $this->documentWithSchemas(['User' => new Schema(title: 'User')]);

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Target is not a Schema, Parameter, RequestBody, or Response');

        $this->navigator->navigate($document, ['components', 'schemas']);
    }

    #[Test]
    public function resolve_defs_pointer_without_registry_falls_back_and_throws(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['X' => new Schema(title: 'X')]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Property does not exist');

        $navigator->resolveRef('#/$defs/X', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_defs_pointer_miss_falls_back_to_raw_navigation(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['X' => new Schema(title: 'X')]),
        ]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        try {
            $navigator->resolveRef('#/$defs/missing', $document, [], new WeakMap());
            self::fail('Expected UnresolvableRefException for missing defs key');
        } catch (UnresolvableRefException $exception) {
            self::assertSame('Property does not exist', $exception->reason());
        }

        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function resolve_defs_map_pointer_falls_back_when_target_is_map(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['X' => new Schema(title: 'X')]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        $this->expectException(UnresolvableRefException::class);

        $navigator->resolveRef('#/$defs', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_plain_name_fragment_caches_result(): void
    {
        $target = new Schema(anchor: 'myAnchor');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $target]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );
        /** @var WeakMap $cache */
        $cache = new WeakMap();

        [, $visited] = $navigator->resolveRef('#myAnchor', $document, [], $cache);
        [$second] = $navigator->resolveRef('#myAnchor', $document, [], $cache);

        self::assertSame($target, $second);
    }

    #[Test]
    public function resolve_uri_ref_caches_result(): void
    {
        $resource = new Schema(id: 'a.json', type: 'integer');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $resource]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );
        /** @var WeakMap $cache */
        $cache = new WeakMap();

        [$first,] = $navigator->resolveRef('a.json', $document, [], $cache);
        [$second] = $navigator->resolveRef('a.json', $document, [], $cache);

        self::assertSame($resource, $first);
        self::assertSame($resource, $second);
    }

    #[Test]
    public function resolve_uri_ref_with_unknown_anchor_throws_after_resource_hit(): void
    {
        $resource = new Schema(id: 'a.json', type: 'integer');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $resource]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Fragment does not resolve to an anchor in the document');

        $navigator->resolveRef('a.json#unknown', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_uri_resource_root_without_fragment(): void
    {
        $resource = new Schema(id: 'a.json', type: 'integer');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $resource]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        [$result,] = $navigator->resolveRef('a.json#', $document, [], new WeakMap());

        self::assertSame($resource, $result);
    }

    #[Test]
    public function resolve_uri_ref_with_pointer_fragment_into_resource(): void
    {
        $leaf = new Schema(title: 'Leaf');
        $resource = new Schema(id: 'nested.json', defs: ['B' => $leaf]);
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $resource]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        [$result,] = $navigator->resolveRef('nested.json#/$defs/B', $document, [], new WeakMap());

        self::assertSame($leaf, $result);
    }

    #[Test]
    public function resolve_uri_ref_follows_ref_of_resource_root(): void
    {
        $target = new Schema(anchor: 'targetAnchor', type: 'string');
        $resource = new Schema(id: 'a.json', ref: '#targetAnchor');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $resource, 'T' => $target]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        [$result,] = $navigator->resolveRef('a.json', $document, [], new WeakMap());

        self::assertSame($target, $result);
    }

    #[Test]
    public function resolve_empty_ref_returns_base_resource_root(): void
    {
        $root = new Schema(id: 'https://api.example.com/openapi.json', type: 'object');
        $document = $this->documentWithSchemas(['Root' => $root]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        [$result,] = $navigator->resolveRef(
            '',
            $document,
            [],
            new WeakMap(),
            0,
            'https://api.example.com/openapi.json',
        );

        self::assertSame($root, $result);
    }

    #[Test]
    public function resolve_empty_ref_without_base_falls_back_to_external(): void
    {
        $document = $this->documentWithSchemas(['Root' => new Schema(id: 'a.json')]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('External ref has empty file path');

        $navigator->resolveRef('', $document, [], new WeakMap());
    }

    #[Test]
    public function resolve_defs_pointer_with_unknown_base_uses_all_roots(): void
    {
        $target = new Schema(title: 'Target');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(id: 'https://api.example.com/root.json', defs: ['X' => $target]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registrySpy($document)->closure(),
        );

        [$result,] = $navigator->resolveRef(
            '#/$defs/X',
            $document,
            [],
            new WeakMap(),
            0,
            'https://api.example.com/unrelated.json',
        );

        self::assertSame($target, $result);
    }

    #[Test]
    public function resolve_defs_pointer_uses_registry_provider(): void
    {
        $target = new Schema(title: 'Target');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['a/b' => $target]),
        ]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        [$result,] = $navigator->resolveRef('#/$defs/a~1b', $document, [], new WeakMap());

        self::assertSame($target, $result);
        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function resolve_plain_name_fragment_uses_registry_provider(): void
    {
        $target = new Schema(anchor: 'myAnchor', title: 'Anchored');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $target]),
        ]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        [$result,] = $navigator->resolveRef('#myAnchor', $document, [], new WeakMap());

        self::assertSame($target, $result);
        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function resolve_uri_ref_with_anchor_scoped_to_resource_uses_registry(): void
    {
        $numberSchema = new Schema(id: 'child2', anchor: 'my_anchor', type: 'number');
        $stringSchema = new Schema(anchor: 'my_anchor', type: 'string');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(
                id: 'http://localhost:1234/draft2020-12/foobar',
                defs: ['A' => new Schema(id: 'child1', allOf: [$numberSchema, $stringSchema])],
            ),
        ]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        [$result,] = $navigator->resolveRef(
            'child1#my_anchor',
            $document,
            [],
            new WeakMap(),
            0,
            'http://localhost:1234/draft2020-12/foobar',
        );

        self::assertSame($stringSchema, $result);
        self::assertSame(1, $spy->calls);
    }

    #[Test]
    public function plain_name_fragment_without_registry_throws_unresolvable(): void
    {
        $target = new Schema(anchor: 'myAnchor');
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['A' => $target]),
        ]);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Fragment does not resolve to an anchor in the document');

        $navigator->resolveRef('#myAnchor', $document, [], new WeakMap());
    }

    #[Test]
    public function document_pointer_does_not_invoke_registry_provider(): void
    {
        $userSchema = new Schema(title: 'User');
        $document = $this->documentWithSchemas(['User' => $userSchema]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        [$result,] = $navigator->resolveRef('#/components/schemas/User', $document, [], new WeakMap());

        self::assertSame($userSchema, $result);
        self::assertSame(0, $spy->calls);
    }

    #[Test]
    public function non_defs_pointer_falls_back_to_raw_navigation_when_registry_misses(): void
    {
        $target = new Schema(title: 'Raw');
        $document = $this->documentWithSchemas(['Subject' => new Schema(defs: ['X' => new Schema(title: 'Other')])]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        [$result,] = $navigator->resolveRef('#/components/schemas/Subject', $document, [], new WeakMap());

        self::assertSame($document->components->schemas['Subject'], $result);
    }

    #[Test]
    public function defs_pointer_cycle_is_detected_via_visited_set(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: [
                'a' => new Schema(ref: '#/$defs/b'),
                'b' => new Schema(ref: '#/$defs/a'),
            ]),
        ]);
        $spy = $this->registrySpy($document);
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $spy->closure(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $navigator->resolveRef('#/$defs/a', $document, [], new WeakMap());
    }

    private function registrySpy(OpenApiDocument $document): RegistrySpy
    {
        return new RegistrySpy($document);
    }

    private function documentWithSchemas(array $schemas): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('Test', '1.0.0'),
            components: new Components(schemas: $schemas),
        );
    }
}

final class RegistrySpy
{
    public int $calls = 0;

    public function __construct(private readonly OpenApiDocument $document) {}

    public function closure(): Closure
    {
        return function (OpenApiDocument $document): ResourceRegistry {
            ++$this->calls;

            return ResourceRegistry::forDocument($document, new RefResolver());
        };
    }
}
