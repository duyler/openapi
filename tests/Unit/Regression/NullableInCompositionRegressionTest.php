<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression suite for issue #66: the `null` pre-check that runs before a
 * property or array item is validated inspected only the immediate schema
 * node. A composition node (`allOf`/`anyOf`/`oneOf`) carries neither `type`
 * nor `nullable` — both live in its branches — so a permitted `null` was
 * rejected by InvalidDataTypeException before a single branch ran.
 *
 * Anti-test: restoring the node-local `$allowNull` computation makes every
 * composition case below throw instead of pass.
 *
 * A 3.0 `allOf: [{$ref: <nullable target>}]` is deliberately absent: it also
 * trips the `$ref` sibling merge of issue #64, which discards the target's
 * `nullable` before this pre-check ever runs. The `$ref` branch covered here
 * reaches its nullability through a composition instead, so it exercises the
 * deferral without depending on that separate fix.
 *
 * @internal
 */
final class NullableInCompositionRegressionTest extends TestCase
{
    private const string SPEC_YAML = <<<'YAML'
openapi: 3.0.0
info: { title: Nullable Composition API, version: 1.0.0 }
paths: {}
components:
  schemas:
    NullableStringComposition:
      allOf: [{type: string, nullable: true}]

    P_inline:       {type: object, properties: {p: {type: string, nullable: true}}}
    P_allOf_inline: {type: object, properties: {p: {allOf: [{type: string, nullable: true}]}}}
    P_anyOf_inline: {type: object, properties: {p: {anyOf: [{type: string, nullable: true}]}}}
    P_oneOf_inline: {type: object, properties: {p: {oneOf: [{type: string, nullable: true}]}}}
    P_allOf_ref:    {type: object, properties: {p: {allOf: [{$ref: '#/components/schemas/NullableStringComposition'}]}}}
    P_allOf_nullable_sibling:
      {type: object, properties: {p: {nullable: true, allOf: [{type: string, nullable: true}]}}}

    P_allOf_non_nullable: {type: object, properties: {p: {allOf: [{type: string}]}}}

    A_inline: {type: array, items: {type: string, nullable: true}}
    A_allOf:  {type: array, items: {allOf: [{type: string, nullable: true}]}}
    A_anyOf:  {type: array, items: {anyOf: [{type: string, nullable: true}]}}
    A_oneOf:  {type: array, items: {oneOf: [{type: string, nullable: true}]}}

    A_allOf_non_nullable: {type: array, items: {allOf: [{type: string}]}}

    # The same rule lives behind six further keywords; each reaches the
    # pre-check through a different validator.
    Nested_properties: {allOf: [{type: object, properties: {p: {allOf: [{type: string, nullable: true}]}}}]}
    Nested_items:      {allOf: [{type: array, items: {allOf: [{type: string, nullable: true}]}}]}
    Dependent_schemas:
      type: object
      properties: {a: {type: string}}
      dependentSchemas:
        a: {type: object, properties: {p: {allOf: [{type: string, nullable: true}]}}}
    If_then:
      if: {type: object}
      then: {type: object, properties: {p: {allOf: [{type: string, nullable: true}]}}}
    Prefix_items: {type: array, prefixItems: [{allOf: [{type: string, nullable: true}]}]}
    Discriminated_oneOf: {type: object, properties: {p: {oneOf: [{allOf: [{type: string, nullable: true}]}]}}}

    # `not` is reached only once the node beside it defers, so the outer
    # allOf is what carries null this far. The inner composition matches
    # null, which means `not` must reject it.
    Not_matching_composition:
      allOf: [{type: string, nullable: true}]
      not: {allOf: [{type: string, nullable: true}]}
YAML;

    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function nullAcceptingSchemaProvider(): iterable
    {
        yield 'inline nullable property' => ['P_inline', ['p' => null]];
        yield 'allOf-wrapped nullable property' => ['P_allOf_inline', ['p' => null]];
        yield 'anyOf-wrapped nullable property' => ['P_anyOf_inline', ['p' => null]];
        yield 'oneOf-wrapped nullable property' => ['P_oneOf_inline', ['p' => null]];
        yield 'allOf-wrapped $ref to a nullable composition' => ['P_allOf_ref', ['p' => null]];
        yield 'allOf with nullable sibling' => ['P_allOf_nullable_sibling', ['p' => null]];
        yield 'inline nullable item' => ['A_inline', [null]];
        yield 'allOf-wrapped nullable item' => ['A_allOf', [null]];
        yield 'anyOf-wrapped nullable item' => ['A_anyOf', [null]];
        yield 'oneOf-wrapped nullable item' => ['A_oneOf', [null]];
        yield 'properties behind an allOf branch' => ['Nested_properties', ['p' => null]];
        yield 'items behind an allOf branch' => ['Nested_items', [null]];
        yield 'property of a dependent schema' => ['Dependent_schemas', ['a' => 'x', 'p' => null]];
        yield 'property of a then branch' => ['If_then', ['p' => null]];
        yield 'prefixItems entry' => ['Prefix_items', [null]];
        yield 'oneOf branch that is itself a composition' => ['Discriminated_oneOf', ['p' => null]];
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nullRejectingSchemaProvider(): iterable
    {
        yield 'allOf-wrapped non-nullable property' => ['P_allOf_non_nullable', ['p' => null]];
        yield 'allOf-wrapped non-nullable item' => ['A_allOf_non_nullable', [null]];
        yield 'null matched by a composition under not' => ['Not_matching_composition', null];
    }

    #[Test]
    #[DataProvider('nullAcceptingSchemaProvider')]
    public function null_is_accepted_when_a_composition_branch_permits_it(string $schemaName, array $data): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_YAML)->build();

        $validator->validateSchema($data, '#/components/schemas/' . $schemaName);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('nullRejectingSchemaProvider')]
    public function null_is_still_rejected_when_no_composition_branch_permits_it(string $schemaName, mixed $data): void
    {
        $validator = OpenApiValidatorBuilder::create()->fromYamlString(self::SPEC_YAML)->build();

        $this->expectException(ValidationException::class);

        $validator->validateSchema($data, '#/components/schemas/' . $schemaName);
    }
}
