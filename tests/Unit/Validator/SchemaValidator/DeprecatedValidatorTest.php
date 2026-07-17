<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeprecatedValidator::class)]
class DeprecatedValidatorTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function deprecated_property_generates_warning_when_report_enabled(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $oldFieldSchema = new Schema(type: 'string', deprecated: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => $oldFieldSchema,
                'newField' => new Schema(type: 'string'),
            ],
        );

        $validator->validate(['oldField' => 'value', 'newField' => 'value'], $schema);

        self::assertNotNull($warningReceived);
        self::assertSame('oldField', $warningReceived->propertyName);
        self::assertSame('Property "oldField" is deprecated', $warningReceived->message);
    }

    #[Test]
    public function deprecated_property_no_warning_when_report_disabled(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: false, eventDispatcher: $dispatcher));

        $oldFieldSchema = new Schema(type: 'string', deprecated: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => $oldFieldSchema,
            ],
        );

        $validator->validate(['oldField' => 'value'], $schema);

        self::assertNull($warningReceived);
    }

    #[Test]
    public function deprecated_property_not_used_no_warning(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $oldFieldSchema = new Schema(type: 'string', deprecated: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => $oldFieldSchema,
                'newField' => new Schema(type: 'string'),
            ],
        );

        $validator->validate(['newField' => 'value'], $schema);

        self::assertNull($warningReceived);
    }

    #[Test]
    public function warning_does_not_block_validation(): void
    {
        $warnings = [];

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $oldFieldSchema = new Schema(type: 'string', deprecated: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => $oldFieldSchema,
            ],
        );

        $validator->validate(['oldField' => 'value'], $schema);

        self::assertCount(1, $warnings);
    }

    #[Test]
    public function multiple_deprecated_properties_generate_multiple_warnings(): void
    {
        $warnings = [];

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField1' => new Schema(type: 'string', deprecated: true),
                'oldField2' => new Schema(type: 'integer', deprecated: true),
                'newField' => new Schema(type: 'string'),
            ],
        );

        $validator->validate(['oldField1' => 'a', 'oldField2' => 5, 'newField' => 'b'], $schema);

        self::assertCount(2, $warnings);
        self::assertSame('oldField1', $warnings[0]->propertyName);
        self::assertSame('oldField2', $warnings[1]->propertyName);
    }

    #[Test]
    public function non_deprecated_property_no_warning(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            type: 'object',
            properties: [
                'normalField' => new Schema(type: 'string'),
            ],
        );

        $validator->validate(['normalField' => 'value'], $schema);

        self::assertNull($warningReceived);
    }

    #[Test]
    public function non_object_data_skipped(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            type: 'string',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );

        $validator->validate('just a string', $schema);

        self::assertNull($warningReceived);
    }

    #[Test]
    public function deprecated_property_path_includes_breadcrumb(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);
        $context->enterBreadcrumb('root');

        $validator->validate(['oldField' => 'value'], $schema, $context);

        self::assertNotNull($warningReceived);
        self::assertSame('/root/oldField', $warningReceived->propertyPath);
    }

    #[Test]
    public function no_dispatcher_no_exception(): void
    {
        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true));

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );

        $validator->validate(['oldField' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function warning_includes_schema_ref(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            ref: '#/components/schemas/User',
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );

        $validator->validate(['oldField' => 'value'], $schema);

        self::assertNotNull($warningReceived);
        self::assertSame('#/components/schemas/User', $warningReceived->schemaRef);
    }

    #[Test]
    public function warning_schema_ref_is_null_when_schema_has_no_ref(): void
    {
        $warningReceived = null;

        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warningReceived): void {
                    $warningReceived = $event;
                },
            ],
        ]);

        $validator = new DeprecatedValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create(), reportDeprecated: true, eventDispatcher: $dispatcher));

        $schema = new Schema(
            type: 'object',
            properties: [
                'oldField' => new Schema(type: 'string', deprecated: true),
            ],
        );

        $validator->validate(['oldField' => 'value'], $schema);

        self::assertNotNull($warningReceived);
        self::assertNull($warningReceived->schemaRef);
    }
}
