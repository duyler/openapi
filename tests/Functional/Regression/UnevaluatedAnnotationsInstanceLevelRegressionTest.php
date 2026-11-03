<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sprintf;

final class UnevaluatedAnnotationsInstanceLevelRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Unevaluated Annotations Instance Level
  version: '1'
paths: {}
components:
  schemas:
    ItemsProbe:
      allOf:
        - unevaluatedItems:
            type: integer
      unevaluatedItems: false
    ItemsProbeFailedBranch:
      allOf:
        - unevaluatedItems:
            type: string
      unevaluatedItems: false
    ItemsProbePlainFalse:
      unevaluatedItems: false
    PropertiesProbe:
      allOf:
        - unevaluatedProperties:
            type: integer
      unevaluatedProperties: false
    PropertiesProbeFailedBranch:
      allOf:
        - unevaluatedProperties:
            type: string
      unevaluatedProperties: false
    PropertiesProbePlainFalse:
      unevaluatedProperties: false
YAML;

    #[Test]
    public function items_annotated_by_allOf_branch_pass_parent_unevaluated_items_false(): void
    {
        $this->assertSchemaValid([1, 2], '#/components/schemas/ItemsProbe');
    }

    #[Test]
    public function properties_annotated_by_allOf_branch_pass_parent_unevaluated_properties_false(): void
    {
        $this->assertSchemaValid(['x' => 1], '#/components/schemas/PropertiesProbe');
    }

    #[Test]
    public function parent_unevaluated_items_false_without_annotating_branch_rejects_items(): void
    {
        $this->assertSchemaInvalid([1, 2], '#/components/schemas/ItemsProbePlainFalse');
    }

    #[Test]
    public function parent_unevaluated_properties_false_without_annotating_branch_rejects_properties(): void
    {
        $this->assertSchemaInvalid(['x' => 1], '#/components/schemas/PropertiesProbePlainFalse');
    }

    #[Test]
    public function items_annotated_by_failed_allOf_branch_are_rejected_by_parent_unevaluated_items_false(): void
    {
        $this->assertSchemaInvalid([1, 2], '#/components/schemas/ItemsProbeFailedBranch');
    }

    #[Test]
    public function properties_annotated_by_failed_allOf_branch_are_rejected_by_parent_unevaluated_properties_false(): void
    {
        $this->assertSchemaInvalid(['x' => 1], '#/components/schemas/PropertiesProbeFailedBranch');
    }

    private function assertSchemaValid(mixed $data, string $schemaRef): void
    {
        $caught = null;

        try {
            $this->build()->validateSchema($data, $schemaRef);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            sprintf('Instance must be VALID against %s, got: %s', $schemaRef, null === $caught ? '' : $caught->getMessage()),
        );
    }

    private function assertSchemaInvalid(mixed $data, string $schemaRef): void
    {
        $caught = null;

        try {
            $this->build()->validateSchema($data, $schemaRef);
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, sprintf('Instance must be INVALID against %s', $schemaRef));
    }

    private function build(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }
}
