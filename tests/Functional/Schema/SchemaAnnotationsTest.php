<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class SchemaAnnotationsTest extends TestCase
{
    private ValidatorPool $pool;
    private SchemaValidatorInterface $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new SchemaValidatorDependencies(
            pool: $this->pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($this->pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        )->rootStatelessValidator();
    }

    #[Test]
    public function object_schema_with_extras_annotates_unknown_keywords_with_root_path(): void
    {
        $context = $this->createContext();
        $schema = new Schema(type: 'object', extras: ['x-meta' => ['a' => 1]]);

        $this->validator->validate([], $schema, $context);

        $annotations = $context->annotations();
        $this->assertArrayHasKey('x-meta', $annotations);
        $this->assertSame([['path' => '/', 'value' => ['a' => 1]]], $annotations['x-meta']);
    }

    #[Test]
    public function comment_keyword_is_never_collected_as_annotation(): void
    {
        $converter = new SchemaFromArrayConverter();
        $schema = $converter->fromArray([
            'type' => 'object',
            '$comment' => 'note',
            'x-meta' => ['a' => 1],
        ]);

        $context = $this->createContext();

        $this->validator->validate([], $schema, $context);

        $annotations = $context->annotations();
        $this->assertArrayNotHasKey('comment', $annotations);
        $this->assertArrayNotHasKey('$comment', $annotations);
        $this->assertArrayHasKey('x-meta', $annotations);
    }

    #[Test]
    public function one_of_branch_annotations_survive_only_for_matched_branch(): void
    {
        $context = $this->createContext();
        $schema = new Schema(oneOf: [
            new Schema(type: 'string', extras: ['x-ok' => 'ok']),
            new Schema(type: 'object', extras: ['x-bad' => 'bad']),
        ]);

        $this->validator->validate('hello', $schema, $context);

        $annotations = $context->annotations();
        $this->assertArrayHasKey('x-ok', $annotations);
        $this->assertSame([['path' => '/', 'value' => 'ok']], $annotations['x-ok']);
        $this->assertArrayNotHasKey('x-bad', $annotations);
    }

    #[Test]
    public function failed_validation_outcome_is_identical_with_and_without_extras(): void
    {
        $contextWithExtras = $this->createContext();
        $contextWithoutExtras = $this->createContext();
        $schemaWithExtras = new Schema(type: 'integer', extras: ['x-vendor' => true]);
        $schemaWithoutExtras = new Schema(type: 'integer');

        try {
            $this->validator->validate('abc', $schemaWithExtras, $contextWithExtras);
            self::fail('Expected TypeMismatchError for schema with extras');
        } catch (TypeMismatchError $errorWithExtras) {
        }

        try {
            $this->validator->validate('abc', $schemaWithoutExtras, $contextWithoutExtras);
            self::fail('Expected TypeMismatchError for schema without extras');
        } catch (TypeMismatchError $errorWithoutExtras) {
        }

        $this->assertSame($errorWithoutExtras->keyword(), $errorWithExtras->keyword());
        $this->assertSame($errorWithoutExtras->dataPath(), $errorWithExtras->dataPath());
        $this->assertSame($errorWithoutExtras->getMessage(), $errorWithExtras->getMessage());
        $this->assertArrayHasKey('x-vendor', $contextWithExtras->annotations());
        $this->assertSame([], $contextWithoutExtras->annotations());
    }

    #[Test]
    public function successful_validation_outcome_is_identical_with_and_without_extras(): void
    {
        $contextWithExtras = $this->createContext();
        $contextWithoutExtras = $this->createContext();

        $this->validator->validate('hello', new Schema(type: 'string', extras: ['x-vendor' => true]), $contextWithExtras);
        $this->validator->validate('hello', new Schema(type: 'string'), $contextWithoutExtras);

        $this->assertSame([], $contextWithoutExtras->annotations());
        $this->assertSame([['path' => '/', 'value' => true]], $contextWithExtras->annotations()['x-vendor']);
    }

    #[Test]
    public function item_schema_extras_annotated_per_instance_path(): void
    {
        $context = $this->createContext();
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string', extras: ['x-item' => 'v']),
        );

        $this->validator->validate(['a', 'b'], $schema, $context);

        $annotations = $context->annotations();
        $this->assertArrayHasKey('x-item', $annotations);
        $this->assertSame([
            ['path' => '/0', 'value' => 'v'],
            ['path' => '/1', 'value' => 'v'],
        ], $annotations['x-item']);
    }

    #[Test]
    public function all_of_failed_branch_annotations_are_discarded(): void
    {
        $context = $this->createContext();
        $schema = new Schema(allOf: [
            new Schema(type: 'string', extras: ['x-first' => 'v']),
            new Schema(type: 'integer', extras: ['x-second' => 'v']),
        ]);

        try {
            $this->validator->validate('hello', $schema, $context);
            self::fail(sprintf('Expected %s for non-integer data', ValidationException::class));
        } catch (ValidationException) {
        }

        $annotations = $context->annotations();
        $this->assertSame([['path' => '/', 'value' => 'v']], $annotations['x-first']);
        $this->assertArrayNotHasKey('x-second', $annotations);
    }

    private function createContext(): ValidationContext
    {
        return ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool));
    }
}
