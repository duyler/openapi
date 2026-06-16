<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(PatternValidator::class)]
class PatternValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PatternValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PatternValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function validate_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-z]+$/');

        $succeeded = false;

        try {
            $this->validator->validate('hello', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_pattern_mismatch(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-z]+$/');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('Hello123', $schema);
    }

    #[Test]
    public function skip_validation_for_non_string(): void
    {
        $schema = new Schema(type: 'integer', pattern: '/^[0-9]+$/');

        $succeeded = false;

        try {
            $this->validator->validate(123, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_no_pattern(): void
    {
        $schema = new Schema(type: 'string');

        $succeeded = false;

        try {
            $this->validator->validate('any string', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_pattern_is_empty(): void
    {
        $schema = new Schema(type: 'string', pattern: '');

        $succeeded = false;

        try {
            $this->validator->validate('any string', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_email_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        $succeeded = false;

        try {
            $this->validator->validate('test@example.com', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_email(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('not-an-email', $schema);
    }

    #[Test]
    public function validate_numeric_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^\d+$/');

        $succeeded = false;

        try {
            $this->validator->validate('12345', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_non_numeric_string(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^\d+$/');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('123abc', $schema);
    }

    #[Test]
    public function validate_pattern_with_unicode(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[\p{L}]+$/u');

        $succeeded = false;

        try {
            $this->validator->validate('Привет', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_empty_string_when_pattern_allows_it(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^.*$/');

        $succeeded = false;

        try {
            $this->validator->validate('', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function invalid_regex_pattern_throws_exception(): void
    {
        $schema = new Schema(type: 'string', pattern: '[invalid');

        $this->expectException(InvalidPatternException::class);

        $this->validator->validate('any string', $schema);
    }

    #[Test]
    public function pattern_with_unclosed_bracket_throws_exception(): void
    {
        $schema = new Schema(type: 'string', pattern: '[0-9');

        $this->expectException(InvalidPatternException::class);

        $this->validator->validate('123', $schema);
    }

    #[Test]
    public function validate_pattern_without_slashes(): void
    {
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $succeeded = false;

        try {
            $this->validator->validate('hello', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_pattern_without_slashes_mismatch(): void
    {
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('Hello', $schema);
    }

    #[Test]
    public function skip_for_null_pattern(): void
    {
        $schema = new Schema(type: 'string');

        $succeeded = false;

        try {
            $this->validator->validate('any string', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function catastrophic_backtracking_throws_exception(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^(a+)+$/');

        $longString = str_repeat('a', 1000) . 'b';

        $this->expectException(InvalidPatternException::class);

        $this->validator->validate($longString, $schema);
    }

    #[Test]
    public function validate_pattern_with_forward_slash_inside(): void
    {
        $schema = new Schema(type: 'string', pattern: 'path/to/resource');

        $succeeded = false;

        try {
            $this->validator->validate('/some/path/to/resource/here', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_pattern_with_tilde_inside(): void
    {
        $schema = new Schema(type: 'string', pattern: 'hello~world');

        $succeeded = false;

        try {
            $this->validator->validate('say hello~world now', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_pattern_with_hash_inside(): void
    {
        $schema = new Schema(type: 'string', pattern: 'section#anchor');

        $succeeded = false;

        try {
            $this->validator->validate('page section#anchor end', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_pattern_with_slash_tilde_and_hash(): void
    {
        $schema = new Schema(type: 'string', pattern: 'path/~value#frag');

        $succeeded = false;

        try {
            $this->validator->validate('url/path/~value#frag/end', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function mismatch_pattern_with_forward_slash_inside(): void
    {
        $schema = new Schema(type: 'string', pattern: '^path/to/resource$');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('other/value', $schema);
    }

    #[Test]
    public function validate_pattern_with_all_delimiter_candidates_and_slash(): void
    {
        // Pattern contains all 8 delimiter candidates (#~!|@%+;) AND /
        $schema = new Schema(type: 'string', pattern: '#~!|@%+;path/to/resource');

        $succeeded = false;

        try {
            $this->validator->validate('prefix #~!|@%+;path/to/resource suffix', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function mismatch_pattern_with_all_delimiter_candidates_and_slash(): void
    {
        $schema = new Schema(type: 'string', pattern: '^#~!|@%+;path/to/resource$');

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate('no match here', $schema);
    }
}
