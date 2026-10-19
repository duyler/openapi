<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\TooManyErrorsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;

/**
 * Regression for issue #54, driven through the public builder path with the
 * shape that surfaced it in the wild: a JSON:API `included` array whose items
 * are an anyOf over allOf-composed resource schemas.
 *
 * Each non-matching resource branch emits ~12 errors (a `type` enum miss plus
 * an `additionalProperties: false` attribute object), so two branches ahead of
 * the matching one exceed MAX_COMPOSITION_ERRORS. Before the fix the cap
 * returned out of the branch loop, so `MatchingLast` failed while the
 * identical `MatchingFirst` branch set passed.
 *
 * @internal
 */
#[CoversClass(OpenApiValidatorBuilder::class)]
final class CompositionBranchOrderTest extends TestCase
{
    private const string JSON_API_INCLUDED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: composition-branch-order
  version: 1.0.0
paths: {}
components:
  schemas:
    Patient:
      allOf:
        - type: object
          required: [type]
          properties:
            type: { type: string, enum: [patients] }
        - type: object
          properties:
            attributes:
              type: object
              additionalProperties: false
              properties:
                first_name: { type: string }
                last_name: { type: string }
    Flow:
      allOf:
        - type: object
          required: [type]
          properties:
            type: { type: string, enum: [flows] }
        - type: object
          properties:
            attributes:
              type: object
              additionalProperties: false
              properties:
                a: { type: string }
                b: { type: string }
                c: { type: string }
                d: { type: string }
                e: { type: string }
                f: { type: string }
                g: { type: string }
                h: { type: string }
                i: { type: string }
                j: { type: string }
                k: { type: string }
    MatchingLast:
      type: array
      items:
        anyOf:
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Flow'
    MatchingFirst:
      type: array
      items:
        anyOf:
          - $ref: '#/components/schemas/Flow'
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
    NoMatch:
      type: array
      items:
        anyOf:
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
          - $ref: '#/components/schemas/Patient'
YAML;

    #[Test]
    public function any_of_in_array_items_ignores_branch_order_when_the_matching_branch_is_last(): void
    {
        $this->validator()->validateSchema($this->included(), '#/components/schemas/MatchingLast');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function any_of_in_array_items_ignores_branch_order_when_the_matching_branch_is_first(): void
    {
        $this->validator()->validateSchema($this->included(), '#/components/schemas/MatchingFirst');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unmatched_item_still_reports_a_capped_and_summarised_error_set(): void
    {
        $caught = null;

        try {
            $this->validator()->validateSchema($this->included(), '#/components/schemas/NoMatch');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'anyOf must fail when no branch matches');

        $errors = $caught->getErrors();
        $markers = array_filter($errors, static fn($error): bool => $error instanceof TooManyErrorsError);

        self::assertCount(21, $errors, 'Cap is 20 collected errors plus one TooManyErrorsError marker');
        self::assertCount(1, $markers, 'Exactly one summary error must be appended');
    }

    private function validator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::JSON_API_INCLUDED_SPEC)
            ->build();
    }

    /**
     * A single `flows` resource: matches the Flow schema and nothing else.
     *
     * @return array<int, array<string, mixed>>
     */
    private function included(): array
    {
        return [
            [
                'type' => 'flows',
                'attributes' => [
                    'a' => 'x', 'b' => 'x', 'c' => 'x', 'd' => 'x', 'e' => 'x', 'f' => 'x',
                    'g' => 'x', 'h' => 'x', 'i' => 'x', 'j' => 'x', 'k' => 'x',
                ],
            ],
        ];
    }
}
