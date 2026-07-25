<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\ObjectConstraints;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Golden-master + unit coverage for the ObjectConstraints collaborator.
 *
 * The emitted code must match the pre-refactor ValidatorCompiler output
 * byte-for-byte so compiled validators behave identically to the baseline.
 */
final class ObjectConstraintsTest extends TestCase
{
    private ObjectConstraints $constraints;

    protected function setUp(): void
    {
        $this->constraints = new ObjectConstraints();
    }

    #[Test]
    public function generate_required_check_emits_nothing_for_empty_required_list(): void
    {
        $code = $this->constraints->generateRequiredCheck([], '$data');

        self::assertSame("\n", $code, 'Empty required list still emits the trailing blank separator line.');
    }

    #[Test]
    public function generate_required_check_emits_array_key_exists_check(): void
    {
        $code = $this->constraints->generateRequiredCheck(['name', 'age'], '$data');

        self::assertStringContainsString("if (false === array_key_exists('name', \$data))", $code);
        self::assertStringContainsString("if (false === array_key_exists('age', \$data))", $code);
    }

    #[Test]
    public function generate_required_check_emits_runtime_exception_with_property_name(): void
    {
        $code = $this->constraints->generateRequiredCheck(['name'], '$data');

        self::assertStringContainsString(
            "throw new \\RuntimeException(sprintf('Required property missing: %s', 'name'));",
            $code,
        );
    }

    #[Test]
    public function generate_required_check_substitutes_data_var_for_nested_paths(): void
    {
        $code = $this->constraints->generateRequiredCheck(['id'], "\$data['user']");

        self::assertStringContainsString("if (false === array_key_exists('id', \$data['user']))", $code);
    }

    #[Test]
    public function generate_required_check_byte_identical_to_pinned_golden_master(): void
    {
        // Pinned byte-for-byte: any change to the emitted required-check
        // block breaks this test, including the trailing blank separator.
        $code = $this->constraints->generateRequiredCheck(['name'], '$data');

        $expected = <<<'PHP'
        if (false === array_key_exists('name', $data)) {
            throw new \RuntimeException(sprintf('Required property missing: %s', 'name'));
        }
PHP;

        self::assertSame($expected . "\n\n", $code);
    }

    #[Test]
    public function generate_additional_properties_check_emits_nothing_when_properties_null(): void
    {
        // additionalProperties:false without declared properties is a
        // no-op: every key would be "additional", so the compiler emits
        // nothing rather than a blanket reject.
        $code = $this->constraints->generateAdditionalPropertiesCheck(new Schema());

        self::assertSame('', $code);
    }

    #[Test]
    public function generate_additional_properties_check_emits_allowlist_loop(): void
    {
        $code = $this->constraints->generateAdditionalPropertiesCheck(
            new Schema(
                properties: [
                    'name' => new Schema(type: 'string'),
                    'age' => new Schema(type: 'integer'),
                ],
            ),
        );

        self::assertStringContainsString('foreach (array_keys($data) as $key)', $code);
        // The exported allowlist must list every declared property.
        self::assertStringContainsString("'name'", $code);
        self::assertStringContainsString("'age'", $code);
        self::assertStringContainsString('in_array($key', $code);
    }

    #[Test]
    public function generate_additional_properties_check_emits_runtime_exception_with_key(): void
    {
        $code = $this->constraints->generateAdditionalPropertiesCheck(
            new Schema(properties: ['name' => new Schema(type: 'string')]),
        );

        self::assertStringContainsString(
            "throw new \\RuntimeException(sprintf('Additional property not allowed: %s', var_export(\$key, true)));",
            $code,
        );
    }

    #[Test]
    public function generate_additional_properties_check_substitutes_data_var_for_nested_paths(): void
    {
        $code = $this->constraints->generateAdditionalPropertiesCheck(
            new Schema(properties: ['name' => new Schema(type: 'string')]),
            "\$data['user']",
        );

        self::assertStringContainsString("foreach (array_keys(\$data['user']) as \$key)", $code);
    }

    #[Test]
    public function generate_additional_properties_check_byte_identical_to_pinned_golden_master(): void
    {
        $code = $this->constraints->generateAdditionalPropertiesCheck(
            new Schema(properties: ['name' => new Schema(type: 'string')]),
            '$data',
        );

        $expected = <<<'PHP'
        foreach (array_keys($data) as $key) {
            if (false === in_array($key, array (
  0 => 'name',
), true)) {
                throw new \RuntimeException(sprintf('Additional property not allowed: %s', var_export($key, true)));
            }
        }
PHP;

        self::assertSame($expected . "\n\n", $code);
    }
}
