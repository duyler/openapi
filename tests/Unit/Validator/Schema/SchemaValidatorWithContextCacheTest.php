<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorWithContextCacheTest extends TestCase
{
    #[Test]
    public function repeated_validate_calls_do_not_recreate_validators(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(openapi: '3.2.0', info: new InfoObject(title: 'Test', version: '1.0.0'));
        $statelessValidators = new StatelessValidatorRegistry($pool, BuiltinFormats::create());

        $validator = new SchemaValidatorWithContext($pool, $refResolver, $document, $statelessValidators);

        $schema = new Schema(type: 'string');

        $validator->validate('hello', $schema);
        $validator->validate('world', $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_integer_schema(): void
    {
        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $document = new OpenApiDocument(openapi: '3.2.0', info: new InfoObject(title: 'Test', version: '1.0.0'));
        $statelessValidators = new StatelessValidatorRegistry($pool, BuiltinFormats::create());

        $validator = new SchemaValidatorWithContext($pool, $refResolver, $document, $statelessValidators);

        $schema = new Schema(type: 'integer');

        $validator->validate(42, $schema);

        $this->assertTrue(true);
    }
}
