<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function count;

#[CoversClass(AllOfValidator::class)]
#[CoversClass(AnyOfValidator::class)]
#[CoversClass(OneOfValidator::class)]
final class CompositionBranchErrorsTest extends TestCase
{
    private ValidatorDependencies $dependencies;

    protected function setUp(): void
    {
        $this->dependencies = new ValidatorDependencies(
            pool: new ValidatorPool(),
            formatRegistry: BuiltinFormats::create(),
        );
    }

    /**
     * @return iterable<string, array{KeywordApplicable, Schema}>
     */
    public static function compositionKeywordProvider(): iterable
    {
        $dependencies = new ValidatorDependencies(
            pool: new ValidatorPool(),
            formatRegistry: BuiltinFormats::create(),
        );

        yield 'allOf' => [new AllOfValidator($dependencies), new Schema(allOf: [self::formIdentifier()])];
        yield 'anyOf' => [new AnyOfValidator($dependencies), new Schema(anyOf: [self::formIdentifier()])];
        yield 'oneOf' => [new OneOfValidator($dependencies), new Schema(oneOf: [self::formIdentifier()])];
    }

    #[Test]
    #[DataProvider('compositionKeywordProvider')]
    public function null_rejected_by_every_branch_reports_a_structured_error(KeywordApplicable $validator, Schema $schema): void
    {
        $exception = $this->assertRejects($validator, $schema, null);

        $this->assertNotEmpty($exception->getErrors());

        foreach ($exception->getErrors() as $error) {
            $this->assertSame('/', $error->dataPath());
        }
    }

    #[Test]
    #[DataProvider('compositionKeywordProvider')]
    public function failing_branch_is_reported_exactly_once(KeywordApplicable $validator, Schema $schema): void
    {
        $exception = $this->assertRejects($validator, $schema, ['type' => 'widgets']);

        $identities = array_map(
            static fn($error): string => $error->keyword() . '@' . $error->dataPath() . '@' . $error->schemaPath(),
            $exception->getErrors(),
        );

        $this->assertCount(1, $identities);
        $this->assertCount(count(array_unique($identities)), $identities);
    }

    #[Test]
    public function all_of_counts_the_failing_branch_for_a_non_object_value(): void
    {
        $validator = new AllOfValidator($this->dependencies);
        $schema = new Schema(allOf: [self::formIdentifier()]);

        $exception = $this->assertRejects($validator, $schema, 'not-an-object');

        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
        $this->assertCount(1, $exception->getErrors());
    }

    #[Test]
    public function all_of_counts_every_failing_branch(): void
    {
        $validator = new AllOfValidator($this->dependencies);
        $schema = new Schema(allOf: [
            self::formIdentifier(),
            new Schema(type: 'object', required: ['widget']),
        ]);

        $exception = $this->assertRejects($validator, $schema, ['type' => 'widgets']);

        $this->assertSame('All of the schemas must match, but 2 failed', $exception->getMessage());
        $this->assertCount(2, $exception->getErrors());
    }

    #[Test]
    public function all_of_counts_only_the_branches_that_did_not_match(): void
    {
        $validator = new AllOfValidator($this->dependencies);
        $schema = new Schema(allOf: [
            new Schema(type: 'string'),
            new Schema(type: 'string', minLength: 20),
        ]);

        $exception = $this->assertRejects($validator, $schema, 'short');

        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function every_keyword_reports_the_same_errors_for_the_same_failing_branch(): void
    {
        $identities = [];

        foreach (self::compositionKeywordProvider() as $keyword => [$validator, $schema]) {
            $identities[$keyword] = array_map(
                static fn($error): string => $error->keyword() . '@' . $error->dataPath() . '@' . $error->schemaPath(),
                $this->assertRejects($validator, $schema, ['type' => 'widgets'])->getErrors(),
            );
        }

        $this->assertSame($identities['allOf'], $identities['anyOf']);
        $this->assertSame($identities['allOf'], $identities['oneOf']);
    }

    #[Test]
    public function rejected_null_is_reported_against_the_branch_that_rejected_it(): void
    {
        $validator = new AllOfValidator($this->dependencies);
        $schema = new Schema(allOf: [
            new Schema(type: 'string'),
            self::formIdentifier(),
        ]);

        $exception = $this->assertRejects($validator, $schema, null);

        $this->assertCount(2, $exception->getErrors());
        $this->assertSame('Expected type "string", but got "null" at /', $exception->getErrors()[0]->message());
        $this->assertSame('/allOf/0', $exception->getErrors()[0]->schemaPath());
        $this->assertSame('Expected type "object", but got "null" at /', $exception->getErrors()[1]->message());
        $this->assertSame('/allOf/1', $exception->getErrors()[1]->schemaPath());
        $this->assertSame('All of the schemas must match, but 2 failed', $exception->getMessage());
    }

    #[Test]
    public function all_of_preserves_every_error_reported_by_a_single_branch(): void
    {
        $validator = new AllOfValidator($this->dependencies);
        $schema = new Schema(allOf: [
            new Schema(
                type: 'object',
                properties: ['a' => new Schema(type: 'integer')],
                additionalProperties: false,
            ),
        ]);

        $exception = $this->assertRejects($validator, $schema, ['x' => 1, 'y' => 2]);

        $this->assertCount(2, $exception->getErrors());
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function composition_errors_are_capped(): void
    {
        $branches = [];

        for ($i = 0; $i < 30; ++$i) {
            $branches[] = new Schema(type: 'object', required: ['missing' . $i]);
        }

        $validator = new AllOfValidator($this->dependencies);
        $exception = $this->assertRejects($validator, new Schema(allOf: $branches), []);

        $this->assertCount(21, $exception->getErrors());
        $this->assertSame('composition', $exception->getErrors()[20]->keyword());
    }

    private static function formIdentifier(): Schema
    {
        return new Schema(
            type: 'object',
            required: ['type', 'id'],
            properties: [
                'type' => new Schema(type: 'string', enum: ['forms']),
                'id' => new Schema(type: 'string'),
            ],
        );
    }

    private function assertRejects(KeywordApplicable $validator, Schema $schema, mixed $data): ValidationException
    {
        try {
            $validator->validate($data, $schema);
        } catch (ValidationException $e) {
            return $e;
        }

        $this->fail('Expected the value to be rejected');
    }
}
