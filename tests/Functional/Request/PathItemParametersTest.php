<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathItemParametersTest extends TestCase
{
    private const string WIDGETS_YAML = <<<YAML
openapi: 3.0.0
info:
  title: Path Level Parameters API
  version: 1.0.0
paths:
  /widgets/{widgetId}:
    parameters:
      - name: widgetId
        in: path
        required: true
        schema:
          type: string
          format: uuid
      - name: mustHave
        in: query
        required: true
        schema:
          type: string
          enum: [alpha, beta]
    get:
      responses:
        '200':
          description: ok
    delete:
      responses:
        '204':
          description: ok
YAML;

    private const string OVERRIDE_YAML = <<<YAML
openapi: 3.0.0
info:
  title: Override API
  version: 1.0.0
paths:
  /widgets:
    parameters:
      - name: status
        in: query
        required: true
        schema:
          type: string
          enum: [alpha, beta]
    get:
      parameters:
        - name: status
          in: query
          required: true
          schema:
            type: string
            enum: [gamma]
      responses:
        '200':
          description: ok
YAML;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function path_item_level_required_query_parameter_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WIDGETS_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets/2b3e0c4a-59f4-4c4f-9a0a-1e0d9c7f0b11',
        );

        $this->expectException(MissingParameterException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function path_item_level_query_parameter_schema_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WIDGETS_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets/2b3e0c4a-59f4-4c4f-9a0a-1e0d9c7f0b11?mustHave=gamma',
        );

        $this->expectException(EnumError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function path_item_level_path_parameter_schema_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WIDGETS_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets/not-a-uuid?mustHave=alpha',
        );

        $this->expectException(InvalidFormatException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function request_satisfying_path_item_level_parameters_is_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WIDGETS_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets/2b3e0c4a-59f4-4c4f-9a0a-1e0d9c7f0b11?mustHave=alpha',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/widgets/{widgetId}', $operation->path);
    }

    #[Test]
    public function path_item_level_parameters_apply_to_every_operation_under_the_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WIDGETS_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'DELETE',
            'http://localhost/widgets/2b3e0c4a-59f4-4c4f-9a0a-1e0d9c7f0b11',
        );

        $this->expectException(MissingParameterException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function operation_level_parameter_overrides_path_item_level_parameter_with_same_name_and_in(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OVERRIDE_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets?status=gamma',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/widgets', $operation->path);
    }

    #[Test]
    public function overridden_path_item_level_parameter_no_longer_applies(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OVERRIDE_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            'http://localhost/widgets?status=alpha',
        );

        $this->expectException(EnumError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function operation_level_parameters_are_still_enforced_alongside_path_item_level_ones(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OVERRIDE_YAML)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', 'http://localhost/widgets');

        $this->expectException(MissingParameterException::class);

        $validator->validateRequest($request);
    }
}
