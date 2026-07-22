<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression suite for R4-SEC-012 / R4-TEST-006: YAML billion-laughs
 * defence on the public builder path. Anti-test: removing the
 * pre-parse `assertNoAnchorBomb()` scan lets the Symfony YAML parser
 * materialise the exponentially-expanded document and the test
 * either OOMs the CI runner or times out.
 *
 * Unlike the inline YamlParserTest, this suite drives every case
 * through {@see OpenApiValidatorBuilder::build()} so the regression
 * also fires when the builder skips the parser (e.g. via cache).
 *
 * @internal
 */
final class YamlBillionLaughsRegressionTest extends TestCase
{
    private const string DEEP_CHAIN_BILLION_LAUGHS = <<<'YAML'
openapi: 3.0.3
info:
  title: Billion laughs
  version: 1.0.0
paths: {}
components:
  schemas:
    a: &a ["x","x","x","x","x"]
    b: &b [*a,*a,*a,*a,*a]
    c: &c [*b,*b,*b,*b,*b]
    d: &d [*c,*c,*c,*c,*c]
    e: &e [*d,*d,*d,*d,*d]
    f: &f [*e,*e,*e,*e,*e]
    g: &g [*f,*f,*f,*f,*f]
    h: &h [*g,*g,*g,*g,*g]
    i: &i [*h,*h,*h,*h,*h]
    j: &j [*i,*i,*i,*i,*i]
    k: &k [*j,*j,*j,*j,*j]
YAML;

    private const string MULTI_LINE_BILLION_LAUGHS = <<<'YAML'
openapi: 3.0.3
info:
  title: Multi-line billion laughs
  version: 1.0.0
paths: {}
components:
  schemas:
    a: &a
      - x
      - x
    b: &b
      - *a
      - *a
    c: &c
      - *b
      - *b
    d: &d
      - *c
      - *c
    e: &e
      - *d
      - *d
    f: &f
      - *e
      - *e
    g: &g
      - *f
      - *f
    h: &h
      - *g
      - *g
    i: &i
      - *h
      - *h
    j: &j
      - *i
      - *i
    k: &k
      - *j
      - *j
YAML;

    private const string LEGIT_DEDUP_SPEC = <<<'YAML'
openapi: 3.0.3
info:
  title: Legit dedup
  version: 1.0.0
paths: {}
components:
  schemas:
    Address: &address
      type: object
      required: [street]
      properties:
        street: { type: string }
    User:
      type: object
      required: [home, work]
      properties:
        home: *address
        work: *address
YAML;

    #[Test]
    public function deep_chain_billion_laughs_payload_is_rejected_by_builder(): void
    {
        $previousMemoryLimit = ini_set('memory_limit', '128M');

        try {
            $caught = null;
            try {
                OpenApiValidatorBuilder::create()
                    ->fromYamlString(self::DEEP_CHAIN_BILLION_LAUGHS)
                    ->build();
            } catch (SpecTooLargeException $e) {
                $caught = $e;
            }

            self::assertNotNull(
                $caught,
                'Builder must reject the billion-laughs payload via SpecTooLargeException before YAML expansion.',
            );
        } finally {
            if (false !== $previousMemoryLimit) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    #[Test]
    public function multi_line_billion_laughs_payload_is_rejected_by_builder(): void
    {
        $previousMemoryLimit = ini_set('memory_limit', '128M');

        try {
            $caught = null;
            try {
                OpenApiValidatorBuilder::create()
                    ->fromYamlString(self::MULTI_LINE_BILLION_LAUGHS)
                    ->build();
            } catch (SpecTooLargeException $e) {
                $caught = $e;
            }

            self::assertNotNull(
                $caught,
                'Builder must reject the multi-line billion-laughs payload via SpecTooLargeException.',
            );
        } finally {
            if (false !== $previousMemoryLimit) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    #[Test]
    public function legit_dedup_spec_with_anchors_and_aliases_is_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LEGIT_DEDUP_SPEC)
            ->build();

        $validator->validateSchema(
            ['home' => ['street' => 'a'], 'work' => ['street' => 'b']],
            '#/components/schemas/User',
        );

        self::assertTrue(true);
    }
}
