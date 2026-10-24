<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\FormatAssertionMode;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormatValidator::class)]
class FormatAssertionModeTest extends TestCase
{
    private ValidationContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
    }

    #[Test]
    public function annotation_mode_records_annotation_without_asserting_invalid_email(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, false);
        $schema = new Schema(type: 'string', format: 'email');

        $validator->validate('not-an-email', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function default_mode_throws_for_invalid_email(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: 'string', format: 'email');

        $this->expectException(InvalidFormatException::class);

        $validator->validate('not-an-email', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_collects_annotation_for_valid_email(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: 'string', format: 'email');

        $validator->validate('user@example.com', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function annotation_mode_records_annotation_at_instance_path(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, false);
        $schema = new Schema(type: 'string', format: 'email');
        $this->context->enterBreadcrumb('payload');

        $validator->validate('not-an-email', $schema, $this->context);

        $this->assertSame(
            [['path' => '/payload', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function annotation_mode_ignores_unknown_format_even_with_strict_formats(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, true);
        $schema = new Schema(type: 'string', format: 'x-unknown');

        $validator->validate('any', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'x-unknown']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function annotation_mode_ignores_typeless_unknown_format_even_with_strict_formats(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, true);
        $schema = new Schema(format: 'x-unknown');

        $validator->validate('any', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'x-unknown']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_throws_for_typed_unknown_format_with_strict_formats(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, true);
        $schema = new Schema(type: 'string', format: 'x-unknown');

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unknown format "x-unknown" for type "string" in strict mode');

        $validator->validate('any', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_throws_for_typeless_unknown_format_with_strict_formats(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, true);
        $schema = new Schema(format: 'x-unknown');

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unknown format "x-unknown" in strict mode');

        $validator->validate('any', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_without_strict_formats_skips_unknown_format(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: 'string', format: 'x-unknown');

        $validator->validate('any', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'x-unknown']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_asserts_typeless_known_format_for_matching_instance(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(format: 'email');

        $this->expectException(InvalidFormatException::class);

        $validator->validate('not-an-email', $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_accepts_typeless_known_format_for_matching_instance(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(format: 'email');

        $validator->validate('user@example.com', $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_skips_known_format_outside_instance_domain(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, true);
        $schema = new Schema(format: 'email');

        $validator->validate(42, $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_skips_boolean_instance_for_builtin_domains(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(format: 'email');

        $validator->validate(true, $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function assertion_mode_asserts_typeless_integer_format_range(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(format: 'int32');

        $validator->validate(5, $schema, $this->context);

        $this->expectException(InvalidFormatException::class);

        $validator->validate(9_223_372_036_854_775_807, $schema, $this->context);
    }

    #[Test]
    public function number_domain_rejects_integer_for_typeless_float_format(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(format: 'float');

        $this->expectException(InvalidFormatException::class);

        $validator->validate(42, $schema, $this->context);
    }

    #[Test]
    public function assertion_mode_rejects_integer_for_typed_float_format(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: 'number', format: 'float');

        $this->expectException(InvalidFormatException::class);

        $validator->validate(42, $schema, $this->context);
    }

    #[Test]
    public function annotation_mode_does_not_reject_type_mismatched_instance(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, false);
        $schema = new Schema(type: 'string', format: 'email');

        $validator->validate(42, $schema, $this->context);

        $this->assertSame(
            [['path' => '/', 'value' => 'email']],
            $this->context->annotations()['format'] ?? [],
        );
    }

    #[Test]
    public function type_array_without_string_type_stays_inert(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, true);
        $schema = new Schema(type: [null], format: 'email');

        $validator->validate('not-an-email', $schema, $this->context);

        $this->assertSame([], $this->context->annotations());
    }

    #[Test]
    public function non_scalar_instance_is_skipped(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: 'string', format: 'email');

        $validator->validate(['not-an-email'], $schema, $this->context);

        $this->assertSame([], $this->context->annotations());
    }

    #[Test]
    public function null_instance_is_skipped(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Assertion, false);
        $schema = new Schema(type: [null, 'string'], format: 'email');

        $validator->validate(null, $schema, $this->context);

        $this->assertSame([], $this->context->annotations());
    }

    #[Test]
    public function custom_registry_domain_receives_any_instance(): void
    {
        $formatValidator = new RecordingFormatValidator();
        $registry = new FormatRegistry()->registerFormat('custom', 'x-custom', $formatValidator);
        $validator = new FormatValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(new ValidatorPool(), $registry),
            pool: new ValidatorPool(),
            formatRegistry: $registry,
            formatAssertionMode: FormatAssertionMode::Assertion,
        ));
        $schema = new Schema(format: 'x-custom');

        $validator->validate(true, $schema, $this->context);

        $this->assertSame([true], $formatValidator->received);
    }

    #[Test]
    public function annotation_mode_without_context_stays_silent(): void
    {
        $validator = $this->createValidator(FormatAssertionMode::Annotation, false);
        $schema = new Schema(type: 'string', format: 'email');

        $validator->validate('not-an-email', $schema);

        $this->expectNotToPerformAssertions();
    }

    private function createValidator(FormatAssertionMode $mode, bool $strictFormats): FormatValidator
    {
        return new FormatValidator(new SchemaValidatorDependencies(
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry(
                new ValidatorPool(),
                BuiltinFormats::create(),
            ),
            pool: new ValidatorPool(),
            formatRegistry: BuiltinFormats::create(),
            strictFormats: $strictFormats,
            formatAssertionMode: $mode,
        ));
    }
}

final class RecordingFormatValidator implements FormatValidatorInterface
{
    /** @var list<mixed> */
    public array $received = [];

    #[Override]
    public function validate(mixed $data): void
    {
        $this->received[] = $data;
    }
}
