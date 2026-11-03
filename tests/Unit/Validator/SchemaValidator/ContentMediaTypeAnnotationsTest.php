<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Test\Support\RecordingEventDispatcher;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentMediaTypeException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function base64_encode;
use function sprintf;

#[CoversClass(ContentMediaTypeValidator::class)]
class ContentMediaTypeAnnotationsTest extends TestCase
{
    private ContentMediaTypeValidator $validator;

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
    public function invalid_json_does_not_fail_validation_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->validator->validate('not json at all', $schema, $this->context);

        $annotations = $this->context->annotations();

        $this->assertSame(
            [['path' => '/', 'value' => 'application/json']],
            $annotations['contentMediaType'],
        );
        $this->assertSame([], $this->dispatcher->events);
    }

    #[Test]
    public function unrecognized_media_type_emits_warning_and_annotation(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-weird');

        $this->validator->validate('any data', $schema, $this->context);

        $this->assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame('/', $event->propertyPath);
        $this->assertSame('contentMediaType', $event->propertyName);
        $this->assertSame(
            'Unrecognized contentMediaType "application/x-weird" is collected as an annotation',
            $event->message,
        );
        $this->assertNull($event->schemaRef);
        $this->assertSame(
            [['path' => '/', 'value' => 'application/x-weird']],
            $this->context->annotations()['contentMediaType'],
        );
    }

    #[Test]
    public function warning_event_carries_schema_ref(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Payload', contentMediaType: 'application/x-weird');

        $this->validator->validate('any data', $schema, $this->context);

        $event = $this->dispatcher->events[0];

        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame('#/components/schemas/Payload', $event->schemaRef);
    }

    #[Test]
    public function warning_is_emitted_with_path_when_context_present(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-weird');
        $this->context->enterBreadcrumb('payload');

        $this->validator->validate('any data', $schema, $this->context);

        $event = $this->dispatcher->events[0];

        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame('/payload', $event->propertyPath);
    }

    #[Test]
    public function unrecognized_media_type_without_context_still_passes(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-weird');

        $this->validator->validate('any data', $schema);

        $this->assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];

        $this->assertInstanceOf(ValidationWarningEvent::class, $event);
        $this->assertSame('/', $event->propertyPath);
    }

    #[Test]
    public function default_mode_does_not_decode_before_media_check(): void
    {
        $schema = new Schema(type: 'string', contentEncoding: 'base64', contentMediaType: 'application/json');
        $payload = base64_encode('not json at all');

        $this->validator->validate($payload, $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'application/json']],
            $this->context->annotations()['contentMediaType'],
        );
    }

    #[Test]
    public function non_string_data_is_skipped_in_default_mode(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->validator->validate(123, $schema, $this->context);

        $this->assertSame([], $this->context->annotations());
    }

    #[Test]
    public function assertion_mode_throws_for_invalid_json(): void
    {
        $validator = $this->createValidator(true);
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $validator->validate('not json at all', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_decodes_base64_before_media_check(): void
    {
        $validator = $this->createValidator(true);
        $schema = new Schema(type: 'string', contentEncoding: 'base64', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $validator->validate(base64_encode('not json at all'), $schema, $this->context);
    }

    #[Test]
    public function null_media_type_is_skipped_in_default_mode(): void
    {
        $schema = new Schema(type: 'string');

        $succeeded = false;

        try {
            $this->validator->validate('any value', $schema, $this->context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            $this->fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        $this->assertTrue($succeeded);
        $this->assertSame([], $this->dispatcher->events);
    }

    private function createValidator(bool $contentAssertionsEnabled): ContentMediaTypeValidator
    {
        return new ContentMediaTypeValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), new FormatRegistry()),
            pool: new ValidatorPool(),
            formatRegistry: new FormatRegistry(),
            eventDispatcher: $this->dispatcher,
            contentAssertionsEnabled: $contentAssertionsEnabled,
        ));
    }
}
