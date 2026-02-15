<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PatternValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PatternValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PatternValidator($this->pool);
    }

    #[Test]
    public function validate_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-z]+$/');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_no_pattern(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any string', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_pattern_is_empty(): void
    {
        $schema = new Schema(type: 'string', pattern: '');

        $this->validator->validate('any string', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_email_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        $this->validator->validate('test@example.com', $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate('12345', $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate('Привет', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_string_when_pattern_allows_it(): void
    {
        $schema = new Schema(type: 'string', pattern: '/^.*$/');

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_regex_pattern(): void
    {
        $schema = new Schema(type: 'string', pattern: '[invalid');

        $this->expectException(InvalidPatternException::class);

        $this->validator->validate('any string', $schema);
    }

    #[Test]
    public function throw_error_for_pattern_with_unclosed_bracket(): void
    {
        $schema = new Schema(type: 'string', pattern: '[0-9');

        $this->expectException(InvalidPatternException::class);

        $this->validator->validate('123', $schema);
    }

    #[Test]
    public function validate_pattern_without_slashes(): void
    {
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate('any string', $schema);

        $this->expectNotToPerformAssertions();
    }
}
