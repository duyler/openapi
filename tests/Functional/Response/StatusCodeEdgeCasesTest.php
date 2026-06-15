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

/**
 * Edge cases for response status code validation (SC-01, SC-02, SC-04, SC-05).
 *
 * Covers non-standard status codes (0, 999), range-based responses (1XX, 3XX)
 * and fully out-of-range codes (6XX-9XX) that the spec does not declare.
 */
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

    /**
     * SC-01: Status code 0 is non-standard and not declared in the spec.
     * The Nyholm Response constructor permits status 0 (only withStatus()
     * enforces the 100..599 range), so the validator receives 0 and must
     * reject it because no "0XX" range is defined.
     */
    #[Test]
    public function status_code_zero_throws_undefined_response_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::STANDARD_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/resource');
        $operation = $validator->validateRequest($request);

        // createResponse() bypasses the 100..599 range check enforced by withStatus().
        $response = $this->psrFactory->createResponse(0)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"status":"ok"}'));

        try {
            $validator->validateResponse($response, $operation);
            self::fail('Expected UndefinedResponseException for status code 0');
        } catch (UndefinedResponseException $e) {
            self::assertSame(0, $e->statusCode);
            // Symfony YAML normalises "200" to the integer 200 in array keys,
            // so we assert against the numeric value regardless of type.
            self::assertContains(200, $e->definedResponses);
            self::assertStringContainsString('"0"', $e->getMessage());
        }
    }

    /**
     * SC-02: Status code 999 exceeds the standard HTTP range and is not
     * declared in the spec, so the validator must reject it.
     */
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

    /**
     * SC-04 (positive): Status 102 falls under the declared 1XX range.
     */
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

        // Range match is the behaviour we are locking in; reaching this line
        // without an exception is the success criterion.
        self::assertSame(102, $response->getStatusCode());
    }

    /**
     * SC-04 (positive): Status 301 falls under the declared 3XX range.
     */
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

    /**
     * SC-04 (negative): Status 200 is not covered by the RANGES_SPEC
     * (only 1XX and 3XX are declared), so it must be rejected.
     */
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
     * SC-05: Fully out-of-range HTTP status codes (6XX-9XX) must be rejected
     * when the spec only declares standard codes.
     *
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
     * SC-05: 6XX-9XX codes are not part of any standard HTTP range.
     * The spec declares only "200", so all of these must throw.
     *
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
            // The spec declares only "200" (Symfony YAML casts to int 200).
            self::assertContains(200, $e->definedResponses);
        }
    }

    /**
     * SC-05 (positive): The 6XX range works as a declared range. When the
     * spec explicitly declares "6XX", status 600 must pass validation.
     * This documents that the validator itself does not blacklist 6XX-9XX;
     * rejection happens only because the spec did not declare them.
     */
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
