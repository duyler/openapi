<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentEncodingValidator::class)]
class ContentEncodingValidatorTest extends TestCase
{
    private ContentEncodingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContentEncodingValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: new FormatRegistry()));
    }

    #[Test]
    public function skip_when_content_encoding_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_data_is_not_string(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_valid_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('SGVsbG8gd29ybGQ=', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->expectException(InvalidContentEncodingException::class);

        $this->validator->validate('not-base64!!!', $schema);
    }

    #[Test]
    public function throw_error_for_non_base64_characters(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->expectException(InvalidContentEncodingException::class);

        $this->validator->validate('hello world', $schema);
    }

    #[Test]
    public function validate_empty_base64(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_unknown_encoding(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'binary');

        $this->validator->validate('any data', $schema);

        $this->expectNotToPerformAssertions();
    }
}
