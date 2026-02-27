<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;

/**
 * @internal
 */
final class RefResolverTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function resolve_local_ref_to_schema(): void
    {
        $userSchema = new Schema(title: "User");
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve(
            "#/components/schemas/User",
            $document,
        );

        $this->assertSame($userSchema, $resolved);
        $this->assertSame("User", $resolved->title);
    }

    #[Test]
    public function resolve_nested_schema(): void
    {
        $addressSchema = new Schema(title: "Address");
        $userSchema = new Schema(
            title: "User",
            properties: [
                "address" => $addressSchema,
            ],
        );

        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                    "Address" => $addressSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve(
            "#/components/schemas/Address",
            $document,
        );

        $this->assertSame($addressSchema, $resolved);
        $this->assertSame("Address", $resolved->title);
    }

    #[Test]
    public function throw_error_for_invalid_ref(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/invalid/path": Property does not exist',
        );

        $this->resolver->resolve("#/invalid/path", $document);
    }

    #[Test]
    public function throw_error_for_missing_component(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/components/schemas/Missing": Value is null',
        );

        $this->resolver->resolve("#/components/schemas/Missing", $document);
    }

    #[Test]
    public function throw_error_for_non_local_ref(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "http://example.com/schema": Only local refs',
        );

        $this->resolver->resolve("http://example.com/schema", $document);
    }

    #[Test]
    public function cache_resolved_refs(): void
    {
        $userSchema = new Schema(title: "User");
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $first = $this->resolver->resolve(
            "#/components/schemas/User",
            $document,
        );
        $second = $this->resolver->resolve(
            "#/components/schemas/User",
            $document,
        );

        $this->assertSame($first, $second);
    }

    #[Test]
    public function throw_error_for_ref_to_non_object(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/openapi": Value is not an object or array',
        );

        $this->resolver->resolve("#/openapi", $document);
    }

    #[Test]
    public function resolve_ref_to_nested_property(): void
    {
        $addressSchema = new Schema(title: "Address");
        $userSchema = new Schema(
            title: "User",
            properties: [
                "address" => $addressSchema,
            ],
        );

        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $resolved = $this->resolver->resolve(
            "#/components/schemas/User/properties/address",
            $document,
        );

        $this->assertSame($addressSchema, $resolved);
        $this->assertSame("Address", $resolved->title);
    }

    #[Test]
    public function throw_error_for_nonexistent_property_in_path(): void
    {
        $userSchema = new Schema(title: "User");
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/components/schemas/User/nonexistent": Property does not exist',
        );

        $this->resolver->resolve(
            "#/components/schemas/User/nonexistent",
            $document,
        );
    }

    #[Test]
    public function throw_error_for_null_value_in_path(): void
    {
        $userSchema = new Schema(
            title: "User",
            properties: [
                "address" => null,
            ],
        );
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/components/schemas/User/properties/address": Value is null',
        );

        $this->resolver->resolve(
            "#/components/schemas/User/properties/address",
            $document,
        );
    }

    #[Test]
    public function throw_error_for_ref_to_string_value(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/info/title": Value is not an object or array',
        );

        $this->resolver->resolve("#/info/title", $document);
    }

    #[Test]
    public function cache_is_document_specific(): void
    {
        $userSchema = new Schema(title: "User");

        $document1 = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $document2 = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Another API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $resolvedFromDoc1 = $this->resolver->resolve(
            "#/components/schemas/User",
            $document1,
        );
        $resolvedFromDoc2 = $this->resolver->resolve(
            "#/components/schemas/User",
            $document2,
        );

        $this->assertSame($resolvedFromDoc1, $resolvedFromDoc2);
        $this->assertSame($userSchema, $resolvedFromDoc1);
    }

    #[Test]
    public function throw_error_for_ref_to_non_schema_object(): void
    {
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/components": Value is null',
        );

        $this->resolver->resolve("#/components", $document);
    }

    #[Test]
    public function throw_error_for_ref_to_property_array(): void
    {
        $userSchema = new Schema(
            title: "User",
            properties: [
                "tags" => ["tag1", "tag2"],
            ],
        );

        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "User" => $userSchema,
                ],
            ),
        );

        $this->expectException(UnresolvableRefException::class);
        $this->expectExceptionMessage(
            'Cannot resolve $ref "#/components/schemas/User/properties/tags/0": Value is not an object or array',
        );

        $this->resolver->resolve(
            "#/components/schemas/User/properties/tags/0",
            $document,
        );
    }

    #[Test]
    public function schema_has_discriminator_returns_true(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($schema, $document),
        );
    }

    #[Test]
    public function schema_without_discriminator_returns_false(): void
    {
        $schema = new Schema();
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($schema, $document),
        );
    }

    #[Test]
    public function schema_with_ref_to_schema_with_discriminator_returns_true(): void
    {
        $discriminatorSchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $refSchema = new Schema(ref: "#/components/schemas/Discriminated");

        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "Discriminated" => $discriminatorSchema,
                ],
            ),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($refSchema, $document),
        );
    }

    #[Test]
    public function schema_with_ref_to_schema_without_discriminator_returns_false(): void
    {
        $simpleSchema = new Schema();
        $refSchema = new Schema(ref: "#/components/schemas/Simple");

        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "Simple" => $simpleSchema,
                ],
            ),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($refSchema, $document),
        );
    }

    #[Test]
    public function schema_with_property_containing_discriminator_returns_true(): void
    {
        $propertySchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $parentSchema = new Schema(properties: ["nested" => $propertySchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($parentSchema, $document),
        );
    }

    #[Test]
    public function schema_with_property_without_discriminator_returns_false(): void
    {
        $propertySchema = new Schema();
        $parentSchema = new Schema(properties: ["nested" => $propertySchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($parentSchema, $document),
        );
    }

    #[Test]
    public function schema_with_items_containing_discriminator_returns_true(): void
    {
        $itemsSchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $arraySchema = new Schema(items: $itemsSchema);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($arraySchema, $document),
        );
    }

    #[Test]
    public function schema_with_items_without_discriminator_returns_false(): void
    {
        $itemsSchema = new Schema();
        $arraySchema = new Schema(items: $itemsSchema);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($arraySchema, $document),
        );
    }

    #[Test]
    public function schema_with_oneof_containing_discriminator_returns_true(): void
    {
        $discriminatorSchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $oneofSchema = new Schema(oneOf: [$discriminatorSchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($oneofSchema, $document),
        );
    }

    #[Test]
    public function schema_with_oneof_without_discriminator_returns_false(): void
    {
        $simpleSchema = new Schema();
        $oneofSchema = new Schema(oneOf: [$simpleSchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($oneofSchema, $document),
        );
    }

    #[Test]
    public function schema_with_anyof_containing_discriminator_returns_true(): void
    {
        $discriminatorSchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $anyofSchema = new Schema(anyOf: [$discriminatorSchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($anyofSchema, $document),
        );
    }

    #[Test]
    public function schema_with_anyof_without_discriminator_returns_false(): void
    {
        $simpleSchema = new Schema();
        $anyofSchema = new Schema(anyOf: [$simpleSchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($anyofSchema, $document),
        );
    }

    #[Test]
    public function cyclic_ref_returns_false(): void
    {
        $schema = new Schema(ref: "#/components/schemas/Cyclic");
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
            components: new Components(
                schemas: [
                    "Cyclic" => $schema,
                ],
            ),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($schema, $document),
        );
    }

    #[Test]
    public function unresolvable_ref_returns_false(): void
    {
        $schema = new Schema(ref: "#/components/schemas/NonExistent");
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertFalse(
            $this->resolver->schemaHasDiscriminator($schema, $document),
        );
    }

    #[Test]
    public function nested_property_discriminator_returns_true(): void
    {
        $deepSchema = new Schema(
            discriminator: new Discriminator(propertyName: "type"),
        );
        $midSchema = new Schema(properties: ["deep" => $deepSchema]);
        $topSchema = new Schema(properties: ["mid" => $midSchema]);
        $document = new OpenApiDocument(
            "3.1.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertTrue(
            $this->resolver->schemaHasDiscriminator($topSchema, $document),
        );
    }

    #[Test]
    public function get_base_uri_returns_self_from_document(): void
    {
        $document = new OpenApiDocument(
            "3.2.0",
            new InfoObject("Test API", "1.0.0"),
            self: "https://api.example.com/openapi.json",
        );

        $this->assertSame(
            "https://api.example.com/openapi.json",
            $this->resolver->getBaseUri($document),
        );
    }

    #[Test]
    public function get_base_uri_returns_null_when_self_not_set(): void
    {
        $document = new OpenApiDocument(
            "3.2.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->assertNull($this->resolver->getBaseUri($document));
    }

    #[Test]
    public function resolve_relative_ref_using_self(): void
    {
        $document = new OpenApiDocument(
            "3.2.0",
            new InfoObject("Test API", "1.0.0"),
            self: "https://api.example.com/schemas/main.json",
        );

        $resolved = $this->resolver->resolveRelativeRef(
            "schemas/user.yaml",
            $document,
        );

        $this->assertSame(
            "https://api.example.com/schemas/schemas/user.yaml",
            $resolved,
        );
    }

    #[Test]
    public function throws_for_relative_ref_without_self(): void
    {
        $document = new OpenApiDocument(
            "3.2.0",
            new InfoObject("Test API", "1.0.0"),
        );

        $this->expectException(RefResolutionException::class);
        $this->expectExceptionMessage(
            "Cannot resolve relative reference 'schemas/user.yaml' without document \$self or base URI",
        );

        $this->resolver->resolveRelativeRef("schemas/user.yaml", $document);
    }

    #[Test]
    public function combines_uris_correctly(): void
    {
        $combined = $this->resolver->combineUris(
            "https://api.example.com/v1/openapi.json",
            "schemas/user.yaml",
        );

        $this->assertSame(
            "https://api.example.com/v1/schemas/user.yaml",
            $combined,
        );
    }

    #[Test]
    public function combines_uris_with_nested_path(): void
    {
        $combined = $this->resolver->combineUris(
            "https://api.example.com/schemas/v2/main.json",
            "components/responses.yaml",
        );

        $this->assertSame(
            "https://api.example.com/schemas/v2/components/responses.yaml",
            $combined,
        );
    }

    #[Test]
    public function combines_uris_with_relative_path(): void
    {
        $combined = $this->resolver->combineUris(
            "https://api.example.com/schemas/main.json",
            "../common/types.yaml",
        );

        $this->assertSame(
            "https://api.example.com/schemas/../common/types.yaml",
            $combined,
        );
    }

    #[Test]
    public function resolves_relative_ref_from_nested_directory(): void
    {
        $document = new OpenApiDocument(
            "3.2.0",
            new InfoObject("Test API", "1.0.0"),
            self: "https://api.example.com/api/v2/openapi.json",
        );

        $resolved = $this->resolver->resolveRelativeRef(
            "paths/users.yaml",
            $document,
        );

        $this->assertSame(
            "https://api.example.com/api/v2/paths/users.yaml",
            $resolved,
        );
    }

    #[Test]
    public function schema_has_ref_returns_true(): void
    {
        $schema = new Schema(ref: "#/components/schemas/User");

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_without_ref_returns_false(): void
    {
        $schema = new Schema(type: "string");

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_property_containing_ref_returns_true(): void
    {
        $propertySchema = new Schema(ref: "#/components/schemas/User");
        $parentSchema = new Schema(properties: ["user" => $propertySchema]);

        $this->assertTrue($this->resolver->schemaHasRef($parentSchema));
    }

    #[Test]
    public function schema_with_property_without_ref_returns_false(): void
    {
        $propertySchema = new Schema(type: "string");
        $parentSchema = new Schema(properties: ["name" => $propertySchema]);

        $this->assertFalse($this->resolver->schemaHasRef($parentSchema));
    }

    #[Test]
    public function schema_with_items_containing_ref_returns_true(): void
    {
        $itemsSchema = new Schema(ref: "#/components/schemas/Item");
        $arraySchema = new Schema(items: $itemsSchema);

        $this->assertTrue($this->resolver->schemaHasRef($arraySchema));
    }

    #[Test]
    public function schema_with_items_without_ref_returns_false(): void
    {
        $itemsSchema = new Schema(type: "string");
        $arraySchema = new Schema(items: $itemsSchema);

        $this->assertFalse($this->resolver->schemaHasRef($arraySchema));
    }

    #[Test]
    public function schema_with_prefix_items_containing_ref_returns_true(): void
    {
        $prefixItemSchema = new Schema(ref: "#/components/schemas/Item");
        $schema = new Schema(prefixItems: [$prefixItemSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_prefix_items_without_ref_returns_false(): void
    {
        $prefixItemSchema = new Schema(type: "string");
        $schema = new Schema(prefixItems: [$prefixItemSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_allof_containing_ref_returns_true(): void
    {
        $subSchema = new Schema(ref: "#/components/schemas/Base");
        $schema = new Schema(allOf: [$subSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_allof_without_ref_returns_false(): void
    {
        $subSchema = new Schema(type: "object");
        $schema = new Schema(allOf: [$subSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_anyof_containing_ref_returns_true(): void
    {
        $subSchema = new Schema(ref: "#/components/schemas/Option");
        $schema = new Schema(anyOf: [$subSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_anyof_without_ref_returns_false(): void
    {
        $subSchema = new Schema(type: "string");
        $schema = new Schema(anyOf: [$subSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_oneof_containing_ref_returns_true(): void
    {
        $subSchema = new Schema(ref: "#/components/schemas/Variant");
        $schema = new Schema(oneOf: [$subSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_oneof_without_ref_returns_false(): void
    {
        $subSchema = new Schema(type: "string");
        $schema = new Schema(oneOf: [$subSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_not_containing_ref_returns_true(): void
    {
        $notSchema = new Schema(ref: "#/components/schemas/Forbidden");
        $schema = new Schema(not: $notSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_not_without_ref_returns_false(): void
    {
        $notSchema = new Schema(type: "null");
        $schema = new Schema(not: $notSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_if_containing_ref_returns_true(): void
    {
        $ifSchema = new Schema(ref: "#/components/schemas/Condition");
        $schema = new Schema(if: $ifSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_if_without_ref_returns_false(): void
    {
        $ifSchema = new Schema(type: "object");
        $schema = new Schema(if: $ifSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_then_containing_ref_returns_true(): void
    {
        $thenSchema = new Schema(ref: "#/components/schemas/ThenSchema");
        $schema = new Schema(then: $thenSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_then_without_ref_returns_false(): void
    {
        $thenSchema = new Schema(type: "object");
        $schema = new Schema(then: $thenSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_else_containing_ref_returns_true(): void
    {
        $elseSchema = new Schema(ref: "#/components/schemas/ElseSchema");
        $schema = new Schema(else: $elseSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_else_without_ref_returns_false(): void
    {
        $elseSchema = new Schema(type: "object");
        $schema = new Schema(else: $elseSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_contains_containing_ref_returns_true(): void
    {
        $containsSchema = new Schema(ref: "#/components/schemas/Item");
        $schema = new Schema(contains: $containsSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_contains_without_ref_returns_false(): void
    {
        $containsSchema = new Schema(type: "string");
        $schema = new Schema(contains: $containsSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_pattern_properties_containing_ref_returns_true(): void
    {
        $patternSchema = new Schema(ref: "#/components/schemas/Value");
        $schema = new Schema(patternProperties: ["^x-" => $patternSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_pattern_properties_without_ref_returns_false(): void
    {
        $patternSchema = new Schema(type: "string");
        $schema = new Schema(patternProperties: ["^x-" => $patternSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_dependent_schemas_containing_ref_returns_true(): void
    {
        $dependentSchema = new Schema(ref: "#/components/schemas/Dependent");
        $schema = new Schema(dependentSchemas: ["foo" => $dependentSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_dependent_schemas_without_ref_returns_false(): void
    {
        $dependentSchema = new Schema(type: "object");
        $schema = new Schema(dependentSchemas: ["foo" => $dependentSchema]);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_property_names_containing_ref_returns_true(): void
    {
        $propertyNamesSchema = new Schema(
            ref: "#/components/schemas/NamePattern",
        );
        $schema = new Schema(propertyNames: $propertyNamesSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_property_names_without_ref_returns_false(): void
    {
        $propertyNamesSchema = new Schema(type: "string");
        $schema = new Schema(propertyNames: $propertyNamesSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_unevaluated_items_containing_ref_returns_true(): void
    {
        $unevaluatedItemsSchema = new Schema(ref: "#/components/schemas/Item");
        $schema = new Schema(unevaluatedItems: $unevaluatedItemsSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_unevaluated_items_without_ref_returns_false(): void
    {
        $unevaluatedItemsSchema = new Schema(type: "string");
        $schema = new Schema(unevaluatedItems: $unevaluatedItemsSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_additional_properties_containing_ref_returns_true(): void
    {
        $additionalPropertiesSchema = new Schema(
            ref: "#/components/schemas/Value",
        );
        $schema = new Schema(additionalProperties: $additionalPropertiesSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_additional_properties_bool_returns_false(): void
    {
        $schema = new Schema(additionalProperties: true);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_with_nested_refs_returns_true(): void
    {
        $deepSchema = new Schema(ref: "#/components/schemas/Deep");
        $midSchema = new Schema(properties: ["deep" => $deepSchema]);
        $topSchema = new Schema(properties: ["mid" => $midSchema]);

        $this->assertTrue($this->resolver->schemaHasRef($topSchema));
    }

    #[Test]
    public function schema_has_ref_in_property_names(): void
    {
        $propertyNamesSchema = new Schema(
            ref: "#/components/schemas/NamePattern",
        );
        $schema = new Schema(propertyNames: $propertyNamesSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_has_ref_in_unevaluated_items(): void
    {
        $unevaluatedItemsSchema = new Schema(ref: "#/components/schemas/Item");
        $schema = new Schema(unevaluatedItems: $unevaluatedItemsSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_has_ref_in_additional_properties_as_schema(): void
    {
        $additionalPropertiesSchema = new Schema(
            ref: "#/components/schemas/Value",
        );
        $schema = new Schema(additionalProperties: $additionalPropertiesSchema);

        $this->assertTrue($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_without_ref_in_property_names(): void
    {
        $propertyNamesSchema = new Schema(type: "string");
        $schema = new Schema(propertyNames: $propertyNamesSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_without_ref_in_unevaluated_items(): void
    {
        $unevaluatedItemsSchema = new Schema(type: "string");
        $schema = new Schema(unevaluatedItems: $unevaluatedItemsSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }

    #[Test]
    public function schema_without_ref_in_additional_properties_as_schema(): void
    {
        $additionalPropertiesSchema = new Schema(type: "string");
        $schema = new Schema(additionalProperties: $additionalPropertiesSchema);

        $this->assertFalse($this->resolver->schemaHasRef($schema));
    }
}
