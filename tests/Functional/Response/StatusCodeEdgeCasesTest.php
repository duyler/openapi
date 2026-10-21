<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class StatusCodeEdgeCasesTest extends TestCase
{
    private const string STANDARD_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Standard responses API
  version: 1.0.0
paths:
  /resource:
    get:
      operationId: getResource
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
YAML;

    private const string RANGES_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Range responses API
  version: 1.0.0
paths:
  /resource:
    get:
      operationId: getResource
      responses:
        '1XX':
          description: Informational
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
        '3XX':
          description: Redirection
          content:
            application/json:
              schema:
                type: object
                properties:
                  location:
                    type: string
YAML;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function status_code_zero_throws_undefined_response_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STANDARD_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(0)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        try {
            $validator->validateResponse($response, $operation);
            self::fail('Expected UndefinedResponseException for status code 0');
        } catch (UndefinedResponseException $e) {
            self::assertSame(0, $e->statusCode);
            self::assertContains(200, $e->definedResponses);
            self::assertStringContainsString('"0"', $e->getMessage());
        }
    }

    #[Test]
    public function status_code_999_throws_undefined_response_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STANDARD_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(999)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        try {
            $validator->validateResponse($response, $operation);
            self::fail('Expected UndefinedResponseException for status code 999');
        } catch (UndefinedResponseException $e) {
            self::assertSame(999, $e->statusCode);
            self::assertContains(200, $e->definedResponses);
            self::assertStringContainsString('"999"', $e->getMessage());
        }
    }

    #[Test]
    public function status_code_102_matches_declared_1xx_range(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RANGES_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(102)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"processing"}'));

        $validator->validateResponse($response, $operation);

        self::assertSame(102, $response->getStatusCode());
    }

    #[Test]
    public function status_code_301_matches_declared_3xx_range(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RANGES_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(301)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"location":"https://example.com"}'));

        $validator->validateResponse($response, $operation);

        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function status_code_200_not_covered_by_ranges_throws_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RANGES_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        $this->expectException(UndefinedResponseException::class);

        $validator->validateResponse($response, $operation);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function nonStandardStatusCodesProvider(): iterable
    {
        yield '600' => [600];
        yield '601' => [601];
        yield '650' => [650];
        yield '699' => [699];
        yield '700' => [700];
        yield '750' => [750];
        yield '799' => [799];
        yield '800' => [800];
        yield '850' => [850];
        yield '899' => [899];
        yield '900' => [900];
        yield '950' => [950];
        yield '999' => [999];
    }

    /**
     * @dataProvider nonStandardStatusCodesProvider
     */
    #[Test]
    #[DataProvider('nonStandardStatusCodesProvider')]
    public function non_standard_status_code_in_6xx_to_9xx_range_throws_exception(int $statusCode): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STANDARD_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        try {
            $validator->validateResponse($response, $operation);
            self::fail(sprintf(
                'Expected UndefinedResponseException for status code %d',
                $statusCode,
            ));
        } catch (UndefinedResponseException $e) {
            self::assertSame($statusCode, $e->statusCode);
            self::assertContains(200, $e->definedResponses);
        }
    }

    #[Test]
    public function status_code_600_passes_when_6xx_range_is_declared(): void
    {
        $spec = <<<'YAML'
openapi: 3.2.0
info:
  title: Custom range API
  version: 1.0.0
paths:
  /resource:
    get:
      operationId: getResource
      responses:
        '6XX':
          description: Custom non-standard range
          content:
            application/json:
              schema:
                type: object
                properties:
                  status:
                    type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(600)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        $validator->validateResponse($response, $operation);

        self::assertSame(600, $response->getStatusCode());
    }
}
