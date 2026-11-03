<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Support\RecordingEventDispatcher;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ContentEncodingValidator::class)]
class ContentEncodingAnnotationsTest extends TestCase
{
    private ContentEncodingValidator $validator;

    private ValidationContext $context;

    private RecordingEventDispatcher $dispatcher;

    #[Override]
    protected function setUp(): void
    {
        $this->dispatcher = new RecordingEventDispatcher();
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
    public function unrecognized_encoding_emits_warning_and_annotation_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'uuencode');

        $this->validator->validate('any data', $schema, $this->context);

        $this->assertWarningRecorded($schema, '/');
        $this->assertSame(
            [['path' => '/', 'value' => 'uuencode']],
            $this->context->annotations()['contentEncoding'],
        );
    }

    #[Test]
    public function unrecognized_encoding_warning_carries_schema_ref(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Payload', contentEncoding: 'uuencode');

        $this->validator->validate('any data', $schema, $this->context);

        $event = $this->dispatcher->events[0];

        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame('#/components/schemas/Payload', $event->schemaRef);
    }

    #[Test]
    public function unrecognized_encoding_warning_carries_instance_path(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'uuencode');
        $this->context->enterBreadcrumb('payload');

        $this->validator->validate('any data', $schema, $this->context);

        $this->assertWarningRecorded($schema, '/payload');
    }

    #[Test]
    public function recognized_encoding_does_not_emit_warning_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'quoted-printable');

        $this->validator->validate('any data', $schema, $this->context);

        $this->assertSame([], $this->dispatcher->events);
        $this->assertSame(
            [['path' => '/', 'value' => 'quoted-printable']],
            $this->context->annotations()['contentEncoding'],
        );
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

    #[Test]
    public function assertion_mode_does_not_throw_for_unknown_encoding(): void
    {
        $validator = $this->createValidator(true);
        $schema = new Schema(type: 'string', contentEncoding: 'uuencode');

        $validator->validate('any data', $schema, $this->context);

        $this->assertWarningRecorded($schema, '/');
        $this->assertSame(
            [['path' => '/', 'value' => 'uuencode']],
            $this->context->annotations()['contentEncoding'],
        );
    }

    private function assertWarningRecorded(Schema $schema, string $propertyPath): void
    {
        $this->assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];

        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame($propertyPath, $event->propertyPath);
        $this->assertSame('contentEncoding', $event->propertyName);
        $this->assertSame(
            sprintf('Unrecognized contentEncoding "%s" is collected as an annotation', $schema->contentEncoding),
            $event->message,
        );
    }

    private function createValidator(bool $contentAssertionsEnabled): ContentEncodingValidator
    {
        return new ContentEncodingValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), new FormatRegistry()),
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            eventDispatcher: $this->dispatcher,
            contentAssertionsEnabled: $contentAssertionsEnabled,
        ));
    }
}
