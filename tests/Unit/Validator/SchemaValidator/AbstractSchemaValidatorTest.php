<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AbstractSchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractSchemaValidator::class)]
final class AbstractSchemaValidatorTest extends TestCase
{
    private ValidatorPool $pool;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function get_data_path_returns_root_slash_when_context_null(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame('/', $validator->exposeGetDataPath(null));
    }

    #[Test]
    public function get_data_path_returns_breadcrumb_path_when_context_supplied(): void
    {
        $validator = $this->buildConcreteValidator();
        $context = ValidationContext::create($this->pool);
        $context->enterBreadcrumb('root');
        $context->enterBreadcrumb('child');

        self::assertSame('/root/child', $validator->exposeGetDataPath($context));
    }

    #[Test]
    public function format_schema_type_returns_default_when_type_is_null(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame('scalar', $validator->exposeFormatSchemaType(null));
    }

    #[Test]
    public function format_schema_type_returns_default_argument_when_supplied(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame('object', $validator->exposeFormatSchemaType(null, 'object'));
    }

    #[Test]
    public function format_schema_type_joins_array_type_with_pipe(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame('integer|string', $validator->exposeFormatSchemaType(['integer', 'string']));
    }

    #[Test]
    public function format_schema_type_returns_string_type_as_is(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame('integer', $validator->exposeFormatSchemaType('integer'));
    }

    #[Test]
    public function create_schema_validator_returns_root_schema_validator_instance(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertNotNull($validator->exposeCreateSchemaValidator());
    }

    #[Test]
    public function pool_accessor_returns_injected_pool_instance(): void
    {
        $validator = $this->buildConcreteValidator();

        self::assertSame($this->pool, $validator->exposeDependencies()->pool);
    }

    private function buildConcreteValidator(): AbstractSchemaValidatorTestStub
    {
        $dependencies = new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create());

        return new AbstractSchemaValidatorTestStub($dependencies);
    }
}
