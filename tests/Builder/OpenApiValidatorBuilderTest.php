<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;

final class OpenApiValidatorBuilderTest extends TestCase
{
    #[Test]
    public function create_builder(): void
    {
        $builder = OpenApiValidatorBuilder::create();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function build_from_yaml_string(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: A list of users
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->assertSame('Sample API', $validator->document->info->title);
    }

    #[Test]
    public function build_from_json_string(): void
    {
        $json = <<<'JSON'
{
  "openapi": "3.0.3",
  "info": {
    "title": "Sample API",
    "version": "1.0.0"
  },
  "paths": {
    "/users": {
      "get": {
        "summary": "List users",
        "responses": {
          "200": {
            "description": "A list of users"
          }
        }
      }
    }
  }
}
JSON;

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->build();

        $this->assertSame('Sample API', $validator->document->info->title);
    }

    #[Test]
    public function throw_error_when_no_spec_loaded(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec not loaded');

        OpenApiValidatorBuilder::create()
            ->build();
    }

    #[Test]
    public function return_new_instance_on_each_call(): void
    {
        $builder = OpenApiValidatorBuilder::create();
        $builder2 = $builder->fromYamlString("openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []");

        $this->assertNotSame($builder, $builder2);
    }

    #[Test]
    public function chain_multiple_methods(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->enableNullableAsType();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_validator_pool(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_cache(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_logger(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_error_formatter(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $formatter = new DetailedFormatter();

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withErrorFormatter($formatter);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function register_custom_format(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void
            {
                // Custom validation logic
            }
        };

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withFormat('string', 'custom-format', $customValidator);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function enable_coercion(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function enable_nullable_as_type(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableNullableAsType();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function build_with_all_options(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void
            {
                // Custom validation logic
            }
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool)
            ->withErrorFormatter($formatter)
            ->withFormat('string', 'custom', $customValidator)
            ->enableCoercion()
            ->enableNullableAsType()
            ->build();

        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function maintain_immutability_with_multiple_with_calls(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder1 = OpenApiValidatorBuilder::create()->fromYamlString($yaml);
        $builder2 = $builder1->enableCoercion();
        $builder3 = $builder2->enableNullableAsType();

        $this->assertNotSame($builder1, $builder2);
        $this->assertNotSame($builder2, $builder3);
    }
}
