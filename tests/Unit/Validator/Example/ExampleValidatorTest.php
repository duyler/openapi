<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Example;

use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExampleValidatorTest extends TestCase
{
    private ExampleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ExampleValidator();
    }

    #[Test]
    public function return_empty_warnings_when_no_examples(): void
    {
        $schema = new Schema(type: 'string');

        $warnings = $this->validator->validate('hello', $schema);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function return_empty_warnings_when_data_matches_example(): void
    {
        $schema = new Schema(
            type: 'string',
            example: 'hello',
        );

        $warnings = $this->validator->validate('hello', $schema);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function return_warning_when_data_does_not_match_example(): void
    {
        $schema = new Schema(
            type: 'string',
            example: 'hello',
        );

        $warnings = $this->validator->validate('world', $schema);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('does not match', $warnings[0]->message);
    }

    #[Test]
    public function return_empty_warnings_when_data_matches_one_of_examples(): void
    {
        $schema = new Schema(
            type: 'string',
            examples: [
                new Example(value: 'hello'),
                new Example(value: 'world'),
            ],
        );

        $warnings = $this->validator->validate('world', $schema);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function return_warning_when_data_does_not_match_any_examples(): void
    {
        $schema = new Schema(
            type: 'string',
            examples: [
                new Example(value: 'hello'),
                new Example(value: 'world'),
            ],
        );

        $warnings = $this->validator->validate('foo', $schema);

        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function return_empty_warnings_when_array_data_matches_example(): void
    {
        $schema = new Schema(
            type: 'object',
            example: ['name' => 'John', 'age' => 30],
        );

        $warnings = $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function return_warning_when_array_data_does_not_match_example(): void
    {
        $schema = new Schema(
            type: 'object',
            example: ['name' => 'John', 'age' => 30],
        );

        $warnings = $this->validator->validate(['name' => 'Jane', 'age' => 25], $schema);

        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function validate_media_type_with_matching_example(): void
    {
        $mediaType = new MediaType(
            example: new Example(value: ['status' => 'ok']),
        );

        $warnings = $this->validator->validateMediaType(['status' => 'ok'], $mediaType);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function validate_media_type_with_non_matching_example(): void
    {
        $mediaType = new MediaType(
            example: new Example(value: ['status' => 'ok']),
        );

        $warnings = $this->validator->validateMediaType(['status' => 'error'], $mediaType);

        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function validate_media_type_with_no_examples(): void
    {
        $mediaType = new MediaType();

        $warnings = $this->validator->validateMediaType('anything', $mediaType);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function return_empty_when_both_example_and_examples_present_and_match(): void
    {
        $schema = new Schema(
            type: 'string',
            example: 'hello',
            examples: [
                new Example(value: 'world'),
            ],
        );

        $warnings = $this->validator->validate('hello', $schema);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function handle_raw_array_examples(): void
    {
        $schema = new Schema(
            type: 'string',
            examples: [
                ['value' => 'hello'],
            ],
        );

        $warnings = $this->validator->validate('hello', $schema);

        $this->assertSame([], $warnings);
    }
}
