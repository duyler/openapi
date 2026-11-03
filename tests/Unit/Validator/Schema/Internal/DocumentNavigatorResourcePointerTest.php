<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\Internal\DocumentNavigator;
use Duyler\OpenApi\Validator\Schema\Internal\ResourceRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakMap;

/**
 * @internal
 */
final class DocumentNavigatorResourcePointerTest extends TestCase
{
    #[Test]
    public function empty_fragment_resolves_to_root_of_resource(): void
    {
        $subject = new Schema(title: 'Subject', properties: ['foo' => new Schema(ref: '#')]);
        $document = $this->documentWithSchemas(['Subject' => $subject]);

        $result = $this->resolve($document, '#', 'Root');

        self::assertSame($subject, $result);
    }

    #[Test]
    public function tilde_zero_one_unescapes_after_percent_decoding(): void
    {
        $target = new Schema(title: 'TildeOne');
        $document = $this->documentWithSchemas(['Subject' => new Schema(defs: ['~1' => $target])]);

        $result = $this->resolve($document, '#/$defs/~01', 'Root');

        self::assertSame($target, $result);
    }

    #[Test]
    public function percent_encoded_percent_decodes_before_tilde_unescape(): void
    {
        $target = new Schema(title: 'Percent');
        $document = $this->documentWithSchemas(['Subject' => new Schema(defs: ['%' => $target])]);

        $result = $this->resolve($document, '#/$defs/%25', 'Root');

        self::assertSame($target, $result);
    }

    #[Test]
    public function percent_encoded_quote_decodes_to_definition_key(): void
    {
        $target = new Schema(title: 'Quote');
        $document = $this->documentWithSchemas(['Subject' => new Schema(defs: ['foo"bar' => $target])]);

        $result = $this->resolve($document, '#/$defs/foo%22bar', 'Root');

        self::assertSame($target, $result);
    }

    #[Test]
    public function pointer_without_defs_prefix_resolves_from_resource_root(): void
    {
        $foo = new Schema(title: 'Foo');
        $document = $this->documentWithSchemas(['Subject' => new Schema(properties: ['foo' => $foo])]);

        $result = $this->resolve($document, '#/properties/foo', 'Root');

        self::assertSame($foo, $result);
    }

    #[Test]
    public function pointer_into_prefix_items_resolves_from_resource_root(): void
    {
        $first = new Schema(title: 'First');
        $document = $this->documentWithSchemas(['Subject' => new Schema(prefixItems: [$first])]);

        $result = $this->resolve($document, '#/prefixItems/0', 'Root');

        self::assertSame($first, $result);
    }

    #[Test]
    public function bare_name_fragment_resolves_anchor_of_embedded_resource(): void
    {
        $anchored = new Schema(id: 'embedded.json', anchor: 'node', title: 'Anchored');
        $document = $this->documentWithSchemas(['Subject' => new Schema(defs: ['A' => $anchored])]);

        $result = $this->resolve($document, '#node', 'Root');

        self::assertSame($anchored, $result);
    }

    #[Test]
    public function pointer_to_extras_keyword_resolves_raw_schema_value(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(
                extras: ['unknown-keyword' => ['type' => 'integer']],
            ),
        ]);

        $result = $this->resolve($document, '#/unknown-keyword', 'Root');

        self::assertInstanceOf(Schema::class, $result);
        self::assertSame('integer', $result->type);
    }

    #[Test]
    public function pointer_to_extras_keyword_of_subschema_resolves_raw_value(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(
                properties: [
                    'foo' => new Schema(extras: ['unknown-keyword' => ['type' => 'integer']]),
                ],
            ),
        ]);

        $result = $this->resolve($document, '#/properties/foo/unknown-keyword', 'Root');

        self::assertInstanceOf(Schema::class, $result);
        self::assertSame('integer', $result->type);
    }

    #[Test]
    public function pointer_to_extras_keyword_with_slash_resolves_via_unescape(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(
                extras: ['unknown/keyword' => ['type' => 'integer']],
            ),
        ]);

        $result = $this->resolve($document, '#/unknown~1keyword', 'Root');

        self::assertInstanceOf(Schema::class, $result);
        self::assertSame('integer', $result->type);
    }

    #[Test]
    public function pointer_to_examples_element_resolves_list_entry(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(examples: [['type' => 'string']]),
        ]);

        $result = $this->resolve($document, '#/examples/0', 'Root');

        self::assertInstanceOf(Schema::class, $result);
        self::assertSame('string', $result->type);
    }

    #[Test]
    public function defs_collection_itself_is_not_a_schema_target(): void
    {
        $document = $this->documentWithSchemas([
            'Subject' => new Schema(defs: ['X' => new Schema(title: 'X')]),
        ]);

        $this->expectException(UnresolvableRefException::class);

        $this->resolve($document, '#/$defs', 'Root');
    }

    private function resolve(OpenApiDocument $document, string $ref, string $subject): Schema
    {
        $navigator = new DocumentNavigator(
            builtinFileResolver: new FileExternalRefResolver(),
            registryProvider: $this->registryProvider(),
        );

        [$result,] = $navigator->resolveRef($ref, $document, [], new WeakMap());

        if (false === $result instanceof Schema) {
            self::fail('Expected Schema result for ref ' . $ref . ' in ' . $subject);
        }

        return $result;
    }

    private function registryProvider(): Closure
    {
        return static fn(OpenApiDocument $document): ResourceRegistry => ResourceRegistry::forDocument(
            $document,
            new RefResolver(),
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
