<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaValidatorWithContext::class)]
#[CoversClass(ValidationContext::class)]
final class SchemaAnnotationsWithContextTest extends TestCase
{
    private SchemaValidatorWithContext $validator;
    private ValidationContext $context;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: new RefResolver(),
            statelessValidators: new StatelessValidatorRegistry($pool, BuiltinFormats::create()),
            formatRegistry: BuiltinFormats::create(),
        );
        $this->validator = $dependencies->rootSchemaValidator($document, new ValidatorConfiguration());
        $this->context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));
    }

    #[Test]
    public function root_schema_extras_collected_at_descent_entry(): void
    {
        $schema = new Schema(type: 'object', extras: ['x-meta' => ['a' => 1]]);

        $this->validator->validateWithContext([], $schema, $this->context);

        $annotations = $this->context->annotations();
        $this->assertArrayHasKey('x-meta', $annotations);
        $this->assertSame([['path' => '/', 'value' => ['a' => 1]]], $annotations['x-meta']);
    }

    #[Test]
    public function nested_property_extras_collected_with_instance_path(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(type: 'object', extras: ['x-nested' => 'v']),
            ],
        );

        $this->validator->validateWithContext(['user' => []], $schema, $this->context);

        $annotations = $this->context->annotations();
        $this->assertArrayHasKey('x-nested', $annotations);
        $this->assertSame([['path' => '/user', 'value' => 'v']], $annotations['x-nested']);
    }

    #[Test]
    public function comment_keyword_is_never_collected_as_annotation(): void
    {
        $schema = new Schema(type: 'object', comment: 'note', extras: ['x-meta' => ['a' => 1]]);

        $this->validator->validateWithContext([], $schema, $this->context);

        $annotations = $this->context->annotations();
        $this->assertArrayNotHasKey('comment', $annotations);
        $this->assertArrayNotHasKey('$comment', $annotations);
        $this->assertArrayHasKey('x-meta', $annotations);
    }

    #[Test]
    public function one_of_fork_collects_only_matched_branch_annotations(): void
    {
        $schema = new Schema(oneOf: [
            new Schema(type: 'string', extras: ['x-ok' => 'ok']),
            new Schema(type: 'object', extras: ['x-bad' => 'bad']),
        ]);

        $this->validator->validateWithContext('hello', $schema, $this->context);

        $annotations = $this->context->annotations();
        $this->assertArrayHasKey('x-ok', $annotations);
        $this->assertSame([['path' => '/', 'value' => 'ok']], $annotations['x-ok']);
        $this->assertArrayNotHasKey('x-bad', $annotations);
    }

    #[Test]
    public function parent_extras_survive_branch_fork(): void
    {
        $schema = new Schema(
            extras: ['x-parent' => 'p'],
            oneOf: [
                new Schema(type: 'string', extras: ['x-ok' => 'ok']),
                new Schema(type: 'object', extras: ['x-bad' => 'bad']),
            ],
        );

        $this->validator->validateWithContext('hello', $schema, $this->context);

        $annotations = $this->context->annotations();
        $this->assertArrayHasKey('x-parent', $annotations);
        $this->assertArrayHasKey('x-ok', $annotations);
        $this->assertArrayNotHasKey('x-bad', $annotations);
    }

    #[Test]
    public function validation_outcome_is_not_changed_by_annotation_collection(): void
    {
        $schema = new Schema(type: 'integer', extras: ['x-vendor' => true]);

        try {
            $this->validator->validateWithContext('abc', $schema, $this->context);
            self::fail('Expected ValidationException for non-integer data');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertArrayHasKey('x-vendor', $this->context->annotations());
    }
}
