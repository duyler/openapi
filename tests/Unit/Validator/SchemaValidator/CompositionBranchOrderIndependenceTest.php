<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\TooManyErrorsError;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_pop;
use function sprintf;

/**
 * Regression for issue #54: the MAX_COMPOSITION_ERRORS cap in
 * AbstractCompositionalValidator::validateSchemas() must cap error
 * *collection*, not branch *iteration*.
 *
 * Before the fix, reaching the cap returned from validateSchemas()
 * mid-loop, so branches declared after the noisy ones were never
 * evaluated and $validCount could not grow. anyOf then reported
 * "none did" for data that a later branch matches, making the outcome
 * depend on branch declaration order.
 *
 * Anti-test: restoring the `return` in place of the `capped` flag makes
 * every ordering-sensitive case below fail while the two cap-shape cases
 * keep passing — the cap itself is not what changed.
 *
 * @internal
 */
#[CoversClass(AnyOfValidator::class)]
#[CoversClass(OneOfValidator::class)]
#[CoversClass(AllOfValidator::class)]
final class CompositionBranchOrderIndependenceTest extends TestCase
{
    /**
     * Two of these exceed MAX_COMPOSITION_ERRORS (20) on their own.
     */
    private const int ERRORS_PER_NOISY_BRANCH = 12;

    private ValidatorPool $pool;
    private AnyOfValidator $anyOf;
    private OneOfValidator $oneOf;
    private AllOfValidator $allOf;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $dependencies = new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create());

        $this->anyOf = new AnyOfValidator($dependencies);
        $this->oneOf = new OneOfValidator($dependencies);
        $this->allOf = new AllOfValidator($dependencies);
    }

    /**
     * The third noisy branch matters: it is the one that reaches the
     * post-cap `continue`. A matching branch alone would short-circuit on
     * `$outcome->matched` before the cap is ever consulted, so turning the
     * `continue` back into a `break` would go unnoticed.
     */
    #[Test]
    public function any_of_passes_whatever_the_position_of_the_matching_branch(): void
    {
        $branches = [
            $this->noisyBranch('a'),
            $this->noisyBranch('b'),
            $this->noisyBranch('c'),
            $this->matchingBranch(),
        ];

        $this->anyOf->validate($this->data(), new Schema(anyOf: $branches), $this->context());
        $this->anyOf->validate($this->data(), new Schema(anyOf: $this->matchingFirst($branches)), $this->context());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function one_of_counts_a_match_declared_after_the_error_cap_is_reached(): void
    {
        $schema = new Schema(oneOf: [
            $this->noisyBranch('a'),
            $this->noisyBranch('b'),
            $this->noisyBranch('c'),
            $this->matchingBranch(),
        ]);

        $this->oneOf->validate($this->data(), $schema, $this->context());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function one_of_rejects_two_matches_declared_after_the_error_cap_is_reached(): void
    {
        $schema = new Schema(oneOf: [
            $this->noisyBranch('a'),
            $this->noisyBranch('b'),
            $this->noisyBranch('c'),
            $this->matchingBranch(),
            $this->otherMatchingBranch(),
        ]);

        $this->expectException(OneOfError::class);

        $this->oneOf->validate($this->data(), $schema, $this->context());
    }

    #[Test]
    public function all_of_still_fails_when_a_branch_after_the_error_cap_fails(): void
    {
        $schema = new Schema(allOf: [
            $this->noisyBranch('a'),
            $this->noisyBranch('b'),
            $this->matchingBranch(),
            $this->noisyBranch('c'),
        ]);

        $caught = null;

        try {
            $this->allOf->validate($this->data(), $schema, $this->context());
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'allOf must fail when any branch fails');
        self::assertSame(
            'All of the schemas must match, but 2 failed',
            $caught->getMessage(),
            'Branches failing after the cap are still evaluated, but their errors are no longer collected',
        );
    }

    #[Test]
    public function all_of_still_passes_when_every_branch_matches(): void
    {
        $schema = new Schema(allOf: [$this->matchingBranch(), $this->otherMatchingBranch()]);

        $this->allOf->validate($this->data(), $schema, $this->context());

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unmatched_data_reports_at_most_the_capped_number_of_errors(): void
    {
        $schema = new Schema(anyOf: [$this->noisyBranch('a'), $this->noisyBranch('b'), $this->noisyBranch('c')]);

        $errors = $this->failureErrors($schema);

        self::assertCount(21, $errors, 'Cap is 20 collected errors plus one TooManyErrorsError marker');
    }

    #[Test]
    public function exactly_one_too_many_errors_marker_is_appended_however_many_branches_follow(): void
    {
        $branches = [];

        for ($i = 0; $i < 10; ++$i) {
            $branches[] = $this->noisyBranch('branch' . $i);
        }

        $errors = $this->failureErrors(new Schema(anyOf: $branches));
        $markers = array_filter($errors, static fn($error): bool => $error instanceof TooManyErrorsError);

        self::assertCount(1, $markers, 'Exactly one summary error must be appended');
        self::assertCount(21, $errors, 'Branches after the cap must not append further errors');
    }

    /**
     * @return array<int, ValidationErrorInterface>
     */
    private function failureErrors(Schema $schema): array
    {
        try {
            $this->anyOf->validate($this->data(), $schema, $this->context());
        } catch (ValidationException $e) {
            return $e->getErrors();
        }

        self::fail('anyOf must fail when no branch matches');
    }

    /**
     * @param array<int, Schema> $branches
     *
     * @return array<int, Schema>
     */
    private function matchingFirst(array $branches): array
    {
        $reordered = $branches;
        $last = array_pop($reordered);

        return [$last, ...$reordered];
    }

    /**
     * Emits ERRORS_PER_NOISY_BRANCH errors against {@see self::data()}.
     */
    private function noisyBranch(string $prefix): Schema
    {
        $required = [];

        for ($i = 0; $i < self::ERRORS_PER_NOISY_BRANCH; ++$i) {
            $required[] = sprintf('%s_absent_%d', $prefix, $i);
        }

        return new Schema(type: 'object', required: $required);
    }

    private function matchingBranch(): Schema
    {
        return new Schema(type: 'object', required: ['id']);
    }

    private function otherMatchingBranch(): Schema
    {
        return new Schema(type: 'object', properties: ['id' => new Schema(type: 'string')]);
    }

    /**
     * @return array<string, string>
     */
    private function data(): array
    {
        return ['id' => 'flow-1'];
    }

    private function context(): ValidationContext
    {
        return ValidationContext::create($this->pool);
    }
}
