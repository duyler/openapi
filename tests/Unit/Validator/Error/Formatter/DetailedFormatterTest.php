<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Exception\AnyOfError;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\MinPropertiesError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DetailedFormatterTest extends TestCase
{
    private DetailedFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DetailedFormatter();
    }

    #[Test]
    public function format_with_details(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringContainsString('Error at /users/0/name', $formatted);
        $this->assertStringContainsString('Message:', $formatted);
        $this->assertStringContainsString('Details:', $formatted);
        $this->assertStringContainsString('minLength:', $formatted);
        $this->assertStringContainsString('actual:', $formatted);
        $this->assertStringContainsString('Suggestion:', $formatted);
    }

    #[Test]
    public function include_suggestions(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/name',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringContainsString('Suggestion: Ensure value has at least 3 characters', $formatted);
    }

    #[Test]
    public function format_multiple_errors(): void
    {
        $error1 = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $error2 = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/users/1/age',
            schemaPath: '/schema/properties/age/type',
        );

        $formatted = $this->formatter->formatMultiple([$error1, $error2]);

        $this->assertStringContainsString('Error at /users/0/name', $formatted);
        $this->assertStringContainsString('Error at /users/1/age', $formatted);
        $this->assertStringContainsString("\n\n", $formatted);
    }

    #[Test]
    public function format_root_error(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringStartsWith('Error at /', $formatted);
    }

    /**
     * @return array<string, array{0: ValidationErrorInterface, 1: string, 2: string, 3: string, 4: string, 5: bool}>
     */
    public static function provideValidationErrorTypes(): array
    {
        return [
            'type_mismatch' => [
                new TypeMismatchError(
                    expected: 'string',
                    actual: 'int',
                    dataPath: '/user/name',
                    schemaPath: '/properties/name/type',
                ),
                'type',
                '/user/name',
                'Expected type "string"',
                'Convert the value to string',
                true,
            ],
            'enum' => [
                new EnumError(
                    allowedValues: ['active', 'inactive', 'pending'],
                    actual: 'deleted',
                    dataPath: '/user/status',
                    schemaPath: '/properties/status/enum',
                ),
                'enum',
                '/user/status',
                'is not in allowed values',
                'Use one of the allowed values',
                true,
            ],
            'const' => [
                new ConstError(
                    expected: 'fixed-value',
                    actual: 'other-value',
                    dataPath: '/user/role',
                    schemaPath: '/properties/role/const',
                ),
                'const',
                '/user/role',
                'does not match const value',
                'Use const value: "fixed-value"',
                true,
            ],
            'min_length' => [
                new MinLengthError(
                    minLength: 3,
                    actualLength: 2,
                    dataPath: '/user/name',
                    schemaPath: '/properties/name/minLength',
                ),
                'minLength',
                '/user/name',
                'Value length 2 is less than minimum 3',
                'Ensure value has at least 3 characters',
                true,
            ],
            'max_length' => [
                new MaxLengthError(
                    maxLength: 10,
                    actualLength: 15,
                    dataPath: '/user/name',
                    schemaPath: '/properties/name/maxLength',
                ),
                'maxLength',
                '/user/name',
                'Value length 15 exceeds maximum 10',
                'Ensure value has at most 10 characters',
                true,
            ],
            'pattern_mismatch' => [
                new PatternMismatchError(
                    pattern: '^[a-z]+$',
                    dataPath: '/user/code',
                    schemaPath: '/properties/code/pattern',
                ),
                'pattern',
                '/user/code',
                'does not match pattern "^[a-z]+$"',
                'Ensure value matches the pattern: ^[a-z]+$',
                true,
            ],
            'minimum' => [
                new MinimumError(
                    minimum: 10.0,
                    actual: 5.0,
                    dataPath: '/user/age',
                    schemaPath: '/properties/age/minimum',
                ),
                'minimum',
                '/user/age',
                'less than minimum',
                'Ensure value is at least',
                true,
            ],
            'maximum' => [
                new MaximumError(
                    maximum: 100.0,
                    actual: 150.0,
                    dataPath: '/user/score',
                    schemaPath: '/properties/score/maximum',
                ),
                'maximum',
                '/user/score',
                'exceeds maximum',
                'Ensure value is at most',
                true,
            ],
            'multiple_of' => [
                new MultipleOfKeywordError(
                    multipleOf: 3.0,
                    value: 7,
                    dataPath: '/user/count',
                    schemaPath: '/properties/count/multipleOf',
                ),
                'multipleOf',
                '/user/count',
                'not a multiple of',
                'Value must be a multiple of',
                true,
            ],
            'min_items' => [
                new MinItemsError(
                    minItems: 2,
                    actualCount: 1,
                    dataPath: '/user/tags',
                    schemaPath: '/properties/tags/minItems',
                ),
                'minItems',
                '/user/tags',
                'Array has 1 items, but minimum is 2',
                'Ensure array has at least 2 items',
                true,
            ],
            'max_items' => [
                new MaxItemsError(
                    maxItems: 3,
                    actualCount: 5,
                    dataPath: '/user/tags',
                    schemaPath: '/properties/tags/maxItems',
                ),
                'maxItems',
                '/user/tags',
                'Array has 5 items, but maximum is 3',
                'Ensure array has at most 3 items',
                true,
            ],
            'duplicate_items' => [
                new DuplicateItemsError(
                    expectedCount: 3,
                    actualCount: 2,
                    dataPath: '/user/tags',
                    schemaPath: '/properties/tags/uniqueItems',
                ),
                'uniqueItems',
                '/user/tags',
                'Array contains duplicate items',
                'Ensure all items in the array are unique',
                true,
            ],
            'required' => [
                new RequiredError(
                    property: 'email',
                    dataPath: '/user',
                    schemaPath: '/required',
                ),
                'required',
                '/user',
                'Required property "email" is missing',
                'Add the missing property "email" to the data',
                true,
            ],
            'min_properties' => [
                new MinPropertiesError(
                    minProperties: 2,
                    actualCount: 1,
                    dataPath: '/user',
                    schemaPath: '/minProperties',
                ),
                'minProperties',
                '/user',
                'Object has 1 properties, but minimum is 2',
                'Ensure object has at least 2 properties',
                true,
            ],
            'max_properties' => [
                new MaxPropertiesError(
                    maxProperties: 3,
                    actualCount: 5,
                    dataPath: '/user',
                    schemaPath: '/maxProperties',
                ),
                'maxProperties',
                '/user',
                'Object has 5 properties, but maximum is 3',
                'Ensure object has at most 3 properties',
                true,
            ],
            'unevaluated_property' => [
                new UnevaluatedPropertyError(
                    dataPath: '/user/extra',
                    schemaPath: '/unevaluatedProperties',
                    propertyName: 'extra',
                ),
                'unevaluatedProperties',
                '/user/extra',
                'Property "extra" is not allowed',
                'Remove the unevaluated property',
                true,
            ],
            'one_of' => [
                new OneOfError(
                    dataPath: '/pet',
                    schemaPath: '/oneOf',
                ),
                'oneOf',
                '/pet',
                'matches multiple schemas',
                'Ensure data matches exactly one of the schemas',
                true,
            ],
            'any_of' => [
                new AnyOfError(
                    dataPath: '/pet',
                    schemaPath: '/anyOf',
                ),
                'anyOf',
                '/pet',
                'does not match any of the schemas',
                'Ensure data matches at least one of the schemas',
                true,
            ],
            'contains_match' => [
                new ContainsMatchError(
                    dataPath: '/items',
                    schemaPath: '/contains',
                ),
                'contains',
                '/items',
                'does not contain any item matching',
                'Ensure at least one item in the array matches',
                true,
            ],
            'min_contains' => [
                new MinContainsError(
                    minContains: 2,
                    actualCount: 1,
                    dataPath: '/items',
                    schemaPath: '/minContains',
                ),
                'minContains',
                '/items',
                'Array has 1 matching items, but minimum contains is 2',
                'Ensure array has at least 2 matching items',
                true,
            ],
            'max_contains' => [
                new MaxContainsError(
                    maxContains: 3,
                    actualCount: 5,
                    dataPath: '/items',
                    schemaPath: '/maxContains',
                ),
                'maxContains',
                '/items',
                'Array has 5 matching items, but maximum contains is 3',
                'Ensure array has at most 3 matching items',
                true,
            ],
            'missing_security_credentials' => [
                new MissingSecurityCredentialsError(
                    schemeName: 'bearerAuth',
                    schemeType: 'http',
                    location: 'header',
                ),
                'security',
                '/security/bearerAuth',
                'Security credentials missing for scheme "bearerAuth"',
                'Expected in: header',
                false,
            ],
            'invalid_format' => [
                new InvalidFormatException(
                    format: 'email',
                    value: 'not-an-email',
                    message: 'Value "not-an-email" is not a valid email',
                ),
                'format',
                '',
                'Value "not-an-email" is not a valid email',
                '',
                false,
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideValidationErrorTypes')]
    public function format_each_error_type_includes_data_path_message_and_keyword(
        ValidationErrorInterface $error,
        string $expectedKeyword,
        string $expectedDataPath,
        string $expectedMessageFragment,
        string $expectedSuggestionFragment,
        bool $hasSuggestion,
    ): void {
        self::assertSame($expectedKeyword, $error->keyword());

        $formatted = $this->formatter->format($error);

        self::assertStringContainsString('Error at ' . $expectedDataPath, $formatted);
        self::assertStringContainsString('Message:', $formatted);
        self::assertStringContainsString($expectedMessageFragment, $formatted);

        if ($hasSuggestion) {
            self::assertStringContainsString('Suggestion:', $formatted);
            self::assertStringContainsString($expectedSuggestionFragment, $formatted);
        } else {
            self::assertStringNotContainsString('Suggestion:', $formatted);
        }
    }

    #[Test]
    public function format_invalid_format_exception_omits_suggestion_section(): void
    {
        $error = new InvalidFormatException(
            format: 'uuid',
            value: 'not-a-uuid',
            message: 'Value is not a valid UUID',
        );

        $formatted = $this->formatter->format($error);

        self::assertStringContainsString('Error at :', $formatted);
        self::assertStringContainsString('Message: Value is not a valid UUID', $formatted);
        self::assertStringContainsString('Details:', $formatted);
        self::assertStringContainsString('format: uuid', $formatted);
        self::assertStringNotContainsString('Suggestion:', $formatted);
    }

    #[Test]
    public function format_one_of_error_omits_details_section_due_to_empty_params(): void
    {
        $error = new OneOfError(
            dataPath: '/pet',
            schemaPath: '/oneOf',
        );

        $formatted = $this->formatter->format($error);

        self::assertStringContainsString('Error at /pet', $formatted);
        self::assertStringContainsString('Message:', $formatted);
        self::assertStringNotContainsString('Details:', $formatted);
        self::assertStringContainsString('Suggestion:', $formatted);
    }

    #[Test]
    public function format_any_of_error_omits_details_section_due_to_empty_params(): void
    {
        $error = new AnyOfError(
            dataPath: '/pet',
            schemaPath: '/anyOf',
        );

        $formatted = $this->formatter->format($error);

        self::assertStringNotContainsString('Details:', $formatted);
        self::assertStringContainsString('Suggestion: Ensure data matches at least one of the schemas', $formatted);
    }

    #[Test]
    public function format_enum_error_omits_non_scalar_allowed_values_from_details(): void
    {
        $error = new EnumError(
            allowedValues: ['active', 'inactive'],
            actual: 'deleted',
            dataPath: '/status',
            schemaPath: '/enum',
        );

        $formatted = $this->formatter->format($error);

        self::assertStringContainsString('actual: deleted', $formatted);
        self::assertStringNotContainsString('allowed:', $formatted);
    }

    #[Test]
    public function format_multiple_combines_different_error_types_with_double_newline(): void
    {
        $typeError = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/name',
            schemaPath: '/type',
        );
        $enumError = new EnumError(
            allowedValues: ['a', 'b'],
            actual: 'c',
            dataPath: '/status',
            schemaPath: '/enum',
        );
        $requiredError = new RequiredError(
            property: 'email',
            dataPath: '/',
            schemaPath: '/required',
        );

        $formatted = $this->formatter->formatMultiple([$typeError, $enumError, $requiredError]);

        self::assertStringContainsString('Error at /name', $formatted);
        self::assertStringContainsString('Expected type "string"', $formatted);
        self::assertStringContainsString('Error at /status', $formatted);
        self::assertStringContainsString('not in allowed values', $formatted);
        self::assertStringContainsString('Error at /', $formatted);
        self::assertStringContainsString('Required property "email" is missing', $formatted);
    }
}
