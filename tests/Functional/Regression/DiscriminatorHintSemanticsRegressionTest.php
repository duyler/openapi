<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DiscriminatorHintSemanticsRegressionTest extends TestCase
{
    private const string SPEC_32 = <<<'YAML'
openapi: 3.2.0
info:
  title: Discriminator Hint Semantics
  version: '1'
paths: {}
components:
  schemas:
    Pet:
      discriminator:
        propertyName: kind
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required: [kind, sound]
      properties:
        kind:
          type: string
        sound:
          type: string
    Dog:
      type: object
      required: [kind, bark]
      properties:
        kind:
          type: string
        bark:
          type: string
YAML;

    private const string SPEC_30 = <<<'YAML'
openapi: 3.0.3
info:
  title: Discriminator Decisive 30
  version: '1'
paths: {}
components:
  schemas:
    Pet:
      discriminator:
        propertyName: kind
        mapping:
          cat: '#/components/schemas/Cat'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Cat:
      type: object
      required: [kind, sound]
      properties:
        kind:
          type: string
        sound:
          type: string
YAML;

    private const string SPEC_31 = <<<'YAML'
openapi: 3.1.0
info:
  title: Discriminator Decisive 31
  version: '1'
paths: {}
components:
  schemas:
    Pet:
      discriminator:
        propertyName: kind
        mapping:
          cat: '#/components/schemas/Cat'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Cat:
      type: object
      required: [kind, sound]
      properties:
        kind:
          type: string
        sound:
          type: string
YAML;

    #[Test]
    public function unknown_discriminator_value_does_not_reject_payload_in_32(): void
    {
        $validator = $this->build(self::SPEC_32);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown', 'sound' => 'meow'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            'OAS 3.2: discriminator is a hint — unknown value must not reject the payload when a oneOf branch matches.',
        );
    }

    #[Test]
    public function unknown_discriminator_value_with_no_matching_branch_is_invalid_in_32(): void
    {
        $validator = $this->build(self::SPEC_32);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            '3.2 hint fallback still applies oneOf logic: payload matching no branch must be INVALID.',
        );
    }

    #[Test]
    public function known_discriminator_with_mismatching_payload_is_invalid_in_32(): void
    {
        $validator = $this->build(self::SPEC_32);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'cat'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Known discriminator value must validate decisively via the chosen schema (missing required sound → INVALID).',
        );
        self::assertStringContainsString(
            'Missing required properties',
            null === $caught ? '' : $caught->getMessage(),
            'The chosen-schema error (missing required sound) must surface, not a oneOf-branch fallback error.',
        );
    }

    #[Test]
    public function unknown_discriminator_value_is_decisive_in_30(): void
    {
        $validator = $this->build(self::SPEC_30);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown', 'sound' => 'meow'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            '3.0 dialect keeps decisive discriminator semantics (unknown value → rejection).',
        );
    }

    #[Test]
    public function unknown_discriminator_value_is_decisive_in_31(): void
    {
        $validator = $this->build(self::SPEC_31);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown', 'sound' => 'meow'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            '3.1 dialect keeps decisive discriminator semantics (unknown value → rejection).',
        );
    }

    #[Test]
    public function opt_out_restores_decisive_discriminator_in_32(): void
    {
        $validator = $this->build(self::SPEC_32, static fn(OpenApiValidatorBuilder $b) => $b->withDecisiveDiscriminator());

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown', 'sound' => 'meow'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'withDecisiveDiscriminator() must restore 0.10 decisive semantics in 3.2 documents.',
        );
    }

    #[Test]
    public function discriminator_without_one_of_is_unaffected_by_hint_in_32(): void
    {
        $spec = <<<'YAML'
openapi: 3.2.0
info:
  title: Discriminator Without OneOf
  version: '1'
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [kind]
      properties:
        kind:
          type: string
        sound:
          type: string
      discriminator:
        propertyName: kind
        mapping:
          cat: '#/components/schemas/Cat'
YAML;

        $validator = $this->build($spec);

        $caught = null;

        try {
            $validator->validateSchema(['kind' => 'unknown'], '#/components/schemas/Pet');
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'Discriminator without oneOf does not enter the hint fallback path in 3.2 — previous decisive behavior preserved.',
        );
    }

    private function build(string $spec, ?callable $configure = null): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString($spec);

        $builder = null === $configure ? $builder : $configure($builder);

        return $builder->build();
    }
}
