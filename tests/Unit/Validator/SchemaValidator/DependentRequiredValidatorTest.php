<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\DependentRequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\DependentRequiredValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DependentRequiredValidator::class)]
class DependentRequiredValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private DependentRequiredValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new DependentRequiredValidator(new SchemaValidatorDependencies(refResolver: new RefResolver(), statelessValidators: new StatelessValidatorRegistry($this->pool, BuiltinFormats::create()), pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function throw_error_when_dependent_property_is_missing(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['creditCard' => 1], $schema);
    }

    #[Test]
    public function pass_when_dependent_property_is_present(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
            ],
        );

        $this->validator->validate(['creditCard' => 1, 'billingAddress' => 'x'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function error_carries_keyword_and_paths(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
            ],
        );

        try {
            $this->validator->validate(['creditCard' => 1], $schema, null);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertCount(1, $e->getErrors());
            self::assertInstanceOf(DependentRequiredError::class, $e->getErrors()[0]);
            self::assertSame('dependentRequired', $e->getErrors()[0]->keyword());
            self::assertSame('billingAddress', $e->getErrors()[0]->params()['property']);
            self::assertSame('creditCard', $e->getErrors()[0]->params()['triggerProperty']);
            self::assertSame('/dependentRequired/creditCard', $e->getErrors()[0]->schemaPath());
        }
    }

    #[Test]
    public function skip_when_trigger_property_absent(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
            ],
        );

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'bar' => ['foo'],
            ],
        );

        $this->validator->validate(['bar'], $schema);
        $this->validator->validate('foobar', $schema);
        $this->validator->validate(12, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_required_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_required_is_empty(): void
    {
        $schema = new Schema(type: 'object', dependentRequired: []);

        $this->validator->validate(['bar' => 2], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_list_is_empty(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'bar' => [],
            ],
        );

        $this->validator->validate(['bar' => 2], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_multiple_dependent_entries(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
                'coupon' => ['discount'],
            ],
        );

        $this->validator->validate([
            'creditCard' => '1234567890',
            'billingAddress' => '123 Main St',
            'coupon' => 'SAVE10',
            'discount' => 10,
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_only_matching_dependent_entries(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'creditCard' => ['billingAddress'],
                'coupon' => ['discount'],
            ],
        );

        $this->validator->validate([
            'creditCard' => '1234567890',
            'billingAddress' => '123 Main St',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function collect_all_missing_dependent_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentRequired: [
                'quux' => ['foo', 'bar'],
            ],
        );

        try {
            $this->validator->validate(['quux' => 1], $schema, null);
            self::fail('ValidationException expected');
        } catch (ValidationException $e) {
            self::assertCount(2, $e->getErrors());
            self::assertSame('foo', $e->getErrors()[0]->params()['property']);
            self::assertSame('bar', $e->getErrors()[1]->params()['property']);
        }
    }

    #[Test]
    public function applicable_when_dependent_required_present(): void
    {
        self::assertTrue($this->validator->isApplicable(new Schema(dependentRequired: ['a' => ['b']])));
        self::assertFalse($this->validator->isApplicable(new Schema()));
        self::assertFalse($this->validator->isApplicable(new Schema(dependentRequired: [])));
    }
}
