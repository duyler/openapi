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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
        // 70-segment pointer through nested properties on the same schema
        // cannot be constructed without recursion, so the bound check
        // fires via the per-segment MAX_DEPTH guard.
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

    private function documentWithSchemas(array $schemas): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('Test', '1.0.0'),
            components: new Components(schemas: $schemas),
        );
    }
}
