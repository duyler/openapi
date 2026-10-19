<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PathItemParametersMergeTest extends TestCase
{
    private const string WEBHOOK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Webhook Path Level Parameters API
  version: 1.0.0
webhooks:
  payment.completed:
    parameters:
      - name: X-Signature
        in: header
        required: true
        schema:
          type: string
    post:
      responses:
        '200':
          description: OK
YAML;

    private const string CALLBACK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Callback Path Level Parameters API
  version: 1.0.0
components:
  callbacks:
    invoiceCallback:
      invoiceCallback:
        '/webhook/invoice':
          parameters:
            - name: mode
              in: query
              required: true
              schema:
                type: string
                enum: [live, test]
          post:
            responses:
              '200':
                description: OK
YAML;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function webhook_path_item_level_parameter_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', 'http://localhost/webhook');

        $this->expectException(MissingParameterException::class);

        $validator->validateWebhook($request, 'payment.completed');
    }

    #[Test]
    public function webhook_satisfying_path_item_level_parameter_is_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', 'http://localhost/webhook')
            ->withHeader('X-Signature', 'sha256=abc');

        $operation = $validator->validateWebhook($request, 'payment.completed');

        $this->assertSame('payment.completed', $operation->path);
    }

    #[Test]
    public function callback_path_item_level_parameter_schema_is_enforced(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest(
            'POST',
            'http://localhost/webhook/invoice?mode=staging',
        );

        $this->expectException(EnumError::class);

        $validator->validateCallback($request, 'invoiceCallback');
    }

    #[Test]
    public function callback_satisfying_path_item_level_parameter_is_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest(
            'POST',
            'http://localhost/webhook/invoice?mode=live',
        );

        $operation = $validator->validateCallback($request, 'invoiceCallback');

        $this->assertSame('POST', $operation->method);
    }
}
