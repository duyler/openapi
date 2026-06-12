<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Callback;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class OpenApiValidatorCallbackTest extends TestCase
{
    private const string CALLBACKS_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Callback Test API
  version: 1.0.0
components:
  callbacks:
    paymentCallback:
      paymentCallback:
        'https://payment.example.com/callback':
          post:
            requestBody:
              required: true
              content:
                application/json:
                  schema:
                    type: object
                    required:
                      - transactionId
                      - amount
                    properties:
                      transactionId:
                        type: string
                      amount:
                        type: number
            responses:
              '200':
                description: Payment callback processed
YAML;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function validate_callback_with_valid_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/payment/callback')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['transactionId' => 'tx-123', 'amount' => 99.99]),
                ),
            );

        $operation = $validator->validateCallback($request, 'paymentCallback');

        $this->assertInstanceOf(Operation::class, $operation);
        $this->assertSame('paymentCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_callback_throws_for_unknown_callback_name(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/callback');

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'nonExistentCallback');
    }

    #[Test]
    public function validate_callback_throws_for_mismatched_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('DELETE', '/payment/callback');

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'paymentCallback');
    }

    #[Test]
    public function validate_callback_throws_for_invalid_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/payment/callback')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['transactionId' => 'tx-123']),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'paymentCallback');
    }
}
