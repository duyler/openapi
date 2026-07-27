<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\ArrayConstraints;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Golden-master + anti-test coverage for the ArrayConstraints collaborator.
 *
 * The emitted code must match the pre-refactor ValidatorCompiler output
 * byte-for-byte so compiled validators behave identically to the baseline.
 * The MAX_UNIQUE_CHECK=100000 cap is preserved verbatim (it defends
 * against quadratic uniqueness scans; lowering or removing it would
 * re-introduce a DoS vector — CWE-400, CWE-770).
 */
final class ArrayConstraintsTest extends TestCase
{
    private ArrayConstraints $constraints;

    protected function setUp(): void
    {
        $this->constraints = new ArrayConstraints();
    }

    #[Test]
    public function generate_length_constraints_emits_nothing_for_empty_schema(): void
    {
        $code = $this->constraints->generateLengthConstraints(new Schema(), '$data');

        self::assertSame('', $code, 'Schema without minItems/maxItems/uniqueItems must produce zero codegen.');
    }

    #[Test]
    public function generate_length_constraints_emits_min_items_check(): void
    {
        $code = $this->constraints->generateLengthConstraints(new Schema(minItems: 1), '$data');

        self::assertStringContainsString('if (count($data) < 1)', $code);
        self::assertStringContainsString("throw new \\RuntimeException('Array too short')", $code);
    }

    #[Test]
    public function generate_length_constraints_emits_max_items_check(): void
    {
        $code = $this->constraints->generateLengthConstraints(new Schema(maxItems: 10), '$data');

        self::assertStringContainsString('if (count($data) > 10)', $code);
        self::assertStringContainsString("throw new \\RuntimeException('Array too long')", $code);
    }

    #[Test]
    public function generate_length_constraints_substitutes_data_var_for_nested_paths(): void
    {
        $code = $this->constraints->generateLengthConstraints(
            new Schema(minItems: 1),
            "\$data['tags']",
        );

        self::assertStringContainsString("if (count(\$data['tags']) < 1)", $code);
    }

    #[Test]
    public function generate_length_constraints_emits_unique_items_check_when_unique_items_true(): void
    {
        $code = $this->constraints->generateLengthConstraints(new Schema(uniqueItems: true), '$data');

        self::assertStringContainsString('$__seen = [];', $code);
        self::assertStringContainsString('foreach ($data as $__item)', $code);
    }

    #[Test]
    public function generate_length_constraints_skips_unique_items_check_when_unique_items_false(): void
    {
        // uniqueItems: false explicitly means duplicates are allowed;
        // no uniqueness check is emitted.
        $code = $this->constraints->generateLengthConstraints(new Schema(uniqueItems: false), '$data');

        self::assertStringNotContainsString('$__seen', $code);
    }

    #[Test]
    public function generate_length_constraints_skips_unique_items_check_when_unique_items_null(): void
    {
        $code = $this->constraints->generateLengthConstraints(new Schema(), '$data');

        self::assertStringNotContainsString('$__seen', $code);
    }

    #[Test]
    public function generate_length_constraints_emits_combined_block_in_canonical_order(): void
    {
        // minItems, then maxItems, then uniqueItems — matches the
        // pre-refactor emission order so compiled validators fail in
        // the same order as the baseline.
        $code = $this->constraints->generateLengthConstraints(
            new Schema(minItems: 2, maxItems: 5, uniqueItems: true),
            '$data',
        );

        $minPos = strpos($code, 'count($data) < 2');
        $maxPos = strpos($code, 'count($data) > 5');
        $uniquePos = strpos($code, '$__seen = [];');

        self::assertNotFalse($minPos);
        self::assertNotFalse($maxPos);
        self::assertNotFalse($uniquePos);

        self::assertLessThan($maxPos, $minPos, 'minItems check must precede maxItems check');
        self::assertLessThan($uniquePos, $maxPos, 'maxItems check must precede uniqueItems check');
    }

    #[Test]
    public function generate_unique_items_check_preserves_canonical_key_helper_invocation(): void
    {
        // The uniqueItems block relies on the canonicalJsonKey helper
        // which the compiler appends to the class footer (via
        // EqualityHelpers). The block must emit `$this->canonicalJsonKey(...)`
        // verbatim; any change in helper name breaks the call site.
        $code = $this->constraints->generateUniqueItemsCheck('$data');

        self::assertStringContainsString('$__key = $this->canonicalJsonKey($__item);', $code);
    }

    #[Test]
    public function generate_unique_items_check_preserves_json_exception_to_runtime_exception_wrapper(): void
    {
        // The standalone-validator contract (README "Compiler Limitations")
        // says generated validators throw only generic RuntimeException.
        // json_encode failures (JSON_THROW_ON_ERROR) are caught as
        // JsonException and rethrown as RuntimeException to preserve
        // the contract. Removing this wrapper would leak JsonException.
        $code = $this->constraints->generateUniqueItemsCheck('$data');

        self::assertStringContainsString('try {', $code);
        self::assertStringContainsString('} catch (\\JsonException $__e) {', $code);
        self::assertStringContainsString("throw new \\RuntimeException(sprintf('Failed to encode value for uniqueness check: %s', \$__e->getMessage()), 0, \$__e);", $code);
    }

    #[Test]
    public function generate_unique_items_check_preserves_max_unique_check_cap_literal(): void
    {
        // DoS defence: the cap matches ArrayLengthValidator::MAX_UNIQUE_CHECK
        // (100000). The literal must appear verbatim so quadratic
        // uniqueness scans are bounded. CWE-400, CWE-770.
        $code = $this->constraints->generateUniqueItemsCheck('$data');

        self::assertStringContainsString('if (100000 < count($__seen))', $code);
        self::assertStringContainsString("throw new \\RuntimeException('Too many items for unique check');", $code);
    }

    #[Test]
    public function generate_unique_items_check_byte_identical_to_pinned_golden_master(): void
    {
        // Pinned byte-for-byte: any change to the emitted uniqueItems
        // block breaks this test, including whitespace. Note: PHP 7.3+
        // flexible heredoc strips one trailing newline before the closer,
        // so the body is assembled with an explicit "\n" to preserve
        // the byte-identity contract.
        $code = $this->constraints->generateUniqueItemsCheck('$data');

        $expected = <<<'PHP'
        $__seen = [];
        foreach ($data as $__item) {
            try {
                $__key = $this->canonicalJsonKey($__item);
            } catch (\JsonException $__e) {
                throw new \RuntimeException(sprintf('Failed to encode value for uniqueness check: %s', $__e->getMessage()), 0, $__e);
            }
            if (isset($__seen[$__key])) {
                throw new \RuntimeException('Array items must be unique');
            }
            $__seen[$__key] = true;
            if (100000 < count($__seen)) {
                throw new \RuntimeException('Too many items for unique check');
            }
        }
PHP;

        self::assertSame($expected . "\n\n", $code);
    }

    #[Test]
    public function generate_unique_items_check_substitutes_data_var_for_nested_paths(): void
    {
        $code = $this->constraints->generateUniqueItemsCheck("\$data['tags']");

        self::assertStringContainsString("foreach (\$data['tags'] as \$__item)", $code);
    }

    #[Test]
    public function generate_unique_items_check_defaults_data_var_to_dollar_data(): void
    {
        $code = $this->constraints->generateUniqueItemsCheck();

        self::assertStringContainsString('foreach ($data as $__item)', $code);
    }
}
