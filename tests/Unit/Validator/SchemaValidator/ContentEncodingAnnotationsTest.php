<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentEncodingValidator::class)]
class ContentEncodingAnnotationsTest extends TestCase
{
    private ContentEncodingValidator $validator;

    private ValidationContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = $this->createValidator(false);
        $this->context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
    }

    #[Test]
    public function invalid_base64_does_not_fail_validation_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('not-base64!!!', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame([['path' => '/', 'value' => 'base64']], $annotations['contentEncoding']);
    }

    #[Test]
    public function annotation_is_recorded_at_instance_path(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');
        $this->context->enterBreadcrumb('payload');

        $this->validator->validate('not-base64!!!', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame([['path' => '/payload', 'value' => 'base64']], $annotations['contentEncoding']);
    }

    #[Test]
    public function unknown_encoding_is_annotated_without_assertion_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'binary');

        $this->validator->validate('any data', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame([['path' => '/', 'value' => 'binary']], $annotations['contentEncoding']);
    }

    #[Test]
    public function valid_base64_is_annotated_without_assertion_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('SGVsbG8gd29ybGQ=', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame([['path' => '/', 'value' => 'base64']], $annotations['contentEncoding']);
    }

    #[Test]
    public function default_mode_without_context_still_passes(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate('not-base64!!!', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function non_string_data_is_skipped_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->validator->validate(123, $schema, $this->context);

        $this->assertSame([], $this->context->annotations());
    }

    #[Test]
    public function assertion_mode_throws_for_invalid_base64(): void
    {
        $validator = $this->createValidator(true);
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $this->expectException(InvalidContentEncodingException::class);

        $validator->validate('not-base64!!!', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_still_collects_annotation_for_valid_base64(): void
    {
        $validator = $this->createValidator(true);
        $schema = new Schema(type: 'string', contentEncoding: 'base64');

        $validator->validate('SGVsbG8gd29ybGQ=', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame([['path' => '/', 'value' => 'base64']], $annotations['contentEncoding']);
    }

    private function createValidator(bool $contentAssertionsEnabled): ContentEncodingValidator
    {
        return new ContentEncodingValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), new FormatRegistry()),
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            contentAssertionsEnabled: $contentAssertionsEnabled,
        ));
    }
}
