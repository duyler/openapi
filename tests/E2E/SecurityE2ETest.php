<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function file_put_contents;
use function implode;
use function json_encode;
use function microtime;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

final class SecurityE2ETest extends TestCase
{
    private const REDOS_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: ReDoS Test API
  version: 1.0.0
paths:
  /validate:
    post:
      summary: Validate with potentially dangerous pattern
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - value
              properties:
                value:
                  type: string
                  pattern: '(a+)+$'
      responses:
        '200':
          description: Valid
YAML;

    private const string COOKIE_AUTH_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Cookie Auth Test API
  version: 1.0.0
security:
  - CookieAuth: []
paths:
  /protected:
    get:
      summary: Cookie-protected endpoint
      responses:
        '200':
          description: Protected data
components:
  securitySchemes:
    CookieAuth:
      type: apiKey
      in: cookie
      name: session_id
YAML;

    private const XML_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: XXE Test API
  version: 1.0.0
paths:
  /xml-accept:
    post:
      summary: Accept XML body
      requestBody:
        required: true
        content:
          application/xml:
            schema:
              type: object
              properties:
                name:
                  type: string
                value:
                  type: string
                  maxLength: 0
              required:
                - name
      responses:
        '200':
          description: Accepted
YAML;

    private const DEEP_NESTING_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Deep Nesting Test API
  version: 1.0.0
paths:
  /nested:
    post:
      summary: Accept deeply nested data
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                data:
                  type: object
      responses:
        '200':
          description: Accepted
YAML;

    private const CIRCULAR_REF_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Circular Ref Test API
  version: 1.0.0
paths:
  /tree:
    post:
      summary: Accept tree node
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/A'
      responses:
        '200':
          description: Accepted
components:
  schemas:
    A:
      type: object
      properties:
        name:
          type: string
        b:
          $ref: '#/components/schemas/B'
    B:
      type: object
      properties:
        name:
          type: string
        a:
          $ref: '#/components/schemas/A'
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * Negative: pattern with catastrophic backtracking must not hang.
     * PCRE backtrack limit triggers InvalidPatternException.
     */
    #[Test]
    public function redos_pattern_does_not_hang_on_long_input(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REDOS_SPEC)
            ->build();

        $attackString = str_repeat('a', 30) . 'b';

        $request = $this->psrFactory->createServerRequest('POST', '/validate')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => $attackString,
            ])));

        $elapsed = $this->measureTime($validator, $request);

        $this->assertLessThan(1.0, $elapsed, 'ReDoS-vulnerable pattern must complete within 1 second');
    }

    /**
     * Positive: matching input validates without exception.
     */
    #[Test]
    public function redos_pattern_with_matching_input_validates_successfully(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REDOS_SPEC)
            ->build();

        $attackString = str_repeat('a', 50);

        $request = $this->psrFactory->createServerRequest('POST', '/validate')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => $attackString,
            ])));

        $startTime = microtime(true);
        $operation = $validator->validateRequest($request);
        $elapsed = microtime(true) - $startTime;

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/validate', $operation->path);
        $this->assertLessThan(1.0, $elapsed, 'Matching pattern must complete within 1 second');
    }

    /**
     * Negative: XXE entity must NOT be expanded — file contents must not leak.
     *
     * XmlBodyParser uses libxml without LIBXML_NOENT flag, so external
     * entities are never substituted. The empty entity reference produces
     * null (recursive parser converts empty elements to null), causing
     * ValidationException from SchemaValueNormalizer. If XXE protection
     * failed and the entity expanded to file content, the keyword would be
     * 'maxLength' instead (string passes type check but exceeds maxLength: 0).
     */
    #[Test]
    public function xxe_attack_does_not_read_system_files(): void
    {
        $secretContent = 'XXE_SECRET_' . uniqid('', true);
        $tempFile = tempnam(sys_get_temp_dir(), 'xxe_test_');

        if (false === $tempFile) {
            self::fail('Failed to create temporary file for XXE test');
        }

        file_put_contents($tempFile, $secretContent);

        try {
            $validator = OpenApiValidatorBuilder::create()
                ->fromYamlString(self::XML_SPEC)
                ->build();

            $xxePayload = sprintf(
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<!DOCTYPE foo [' . "\n"
                . '  <!ENTITY xxe SYSTEM "file://%s">' . "\n"
                . ']>' . "\n"
                . '<root><name>test</name><value>&xxe;</value></root>',
                $tempFile,
            );

            $request = $this->psrFactory->createServerRequest('POST', '/xml-accept')
                ->withHeader('Content-Type', 'application/xml')
                ->withBody($this->psrFactory->createStream($xxePayload));

            $startTime = microtime(true);

            $validationSucceeded = false;
            $errorKeyword = '';
            $errorMessage = '';

            try {
                $validator->validateRequest($request);
                $validationSucceeded = true;
            } catch (AbstractValidationError $e) {
                $errorKeyword = $e->keyword();
                $errorMessage = $e->getMessage();
            } catch (ValidationException $e) {
                $errorMessage = $e->getMessage();
            }

            $elapsed = microtime(true) - $startTime;

            $this->assertLessThan(1.0, $elapsed, 'XXE processing must complete within 1 second');

            $this->assertStringNotContainsString(
                $secretContent,
                $errorMessage,
                'XXE entity content must not be expanded into any output',
            );

            $this->assertNotSame(
                'maxLength',
                $errorKeyword,
                'XXE entity was expanded into a string value (keyword=maxLength means '
                . 'type check passed and value exceeded maxLength: 0 — entity content leaked)',
            );
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Positive: valid XML without XXE entities validates successfully.
     */
    #[Test]
    public function valid_xml_without_xxe_validates_successfully(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_SPEC)
            ->build();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<root>
  <name>John</name>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-accept')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xml));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/xml-accept', $operation->path);
    }

    /**
     * Negative: a chain of 200+ $ref pointers must throw SchemaDepthExceededException.
     *
     * RefResolver tracks depth during ref resolution. When depth exceeds
     * ValidationContext::MAX_DEPTH (64), SchemaDepthExceededException is thrown.
     */
    #[Test]
    public function deep_ref_chain_exceeds_max_depth(): void
    {
        $spec = $this->buildDeepRefChainSpec(200);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/test')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name":"test"}'));

        $this->expectException(SchemaDepthExceededException::class);
        $validator->validateRequest($request);
    }

    /**
     * Positive: deeply nested JSON data (200+ levels) against a flat schema
     * must complete without crash or stack overflow.
     */
    #[Test]
    public function deep_data_nesting_completes_without_crash(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DEEP_NESTING_SPEC)
            ->build();

        $nestedData = ['leaf' => 'end'];
        for ($i = 0; $i < 200; ++$i) {
            $nestedData = ['data' => $nestedData];
        }
        $payload = ['data' => $nestedData];

        $request = $this->psrFactory->createServerRequest('POST', '/nested')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($payload)));

        $startTime = microtime(true);
        $validator->validateRequest($request);
        $elapsed = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $elapsed, 'Deeply nested JSON validation must complete within 1 second');
    }

    /**
     * Circular $ref (A→B→A) must not cause infinite recursion.
     *
     * No exception is expected here because the regular schema validator
     * resolves only the top-level $ref once during request body validation
     * setup and does not recurse into property-level $ref pointers.
     * For discriminator-based schemas the context validator does recurse,
     * but ValidationContext::MAX_DEPTH (64) and RefResolver's visited-ref
     * tracking (UnresolvableRefException on direct circular chains) prevent
     * infinite loops. This test confirms shallow data validates successfully
     * without hanging.
     */
    #[Test]
    public function circular_ref_schema_does_not_cause_infinite_recursion(): void
    {
        $startTime = microtime(true);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CIRCULAR_REF_SPEC)
            ->build();

        $data = [
            'name' => 'root',
            'b' => [
                'name' => 'child',
                'a' => ['name' => 'grandchild'],
            ],
        ];

        $request = $this->psrFactory->createServerRequest('POST', '/tree')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($data)));

        $operation = $validator->validateRequest($request);

        $elapsed = microtime(true) - $startTime;

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/tree', $operation->path);
        $this->assertLessThan(1.0, $elapsed, 'Circular $ref schema validation must complete within 1 second');
    }

    /**
     * A schema with 1000 enum values must not cause timeout.
     */
    #[Test]
    public function huge_enum_in_schema_does_not_cause_timeout(): void
    {
        $enumValues = [];
        for ($i = 0; $i < 1000; ++$i) {
            $enumValues[] = 'value_' . $i;
        }

        $enumList = implode(', ', $enumValues);

        $spec = <<<YAML
openapi: 3.1.0
info:
  title: Huge Enum Test API
  version: 1.0.0
paths:
  /select:
    post:
      summary: Select value from huge enum
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - choice
              properties:
                choice:
                  type: string
                  enum: [{$enumList}]
      responses:
        '200':
          description: Selected
YAML;

        $startTime = microtime(true);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/select')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'choice' => 'not_in_enum',
            ])));

        try {
            $validator->validateRequest($request);
        } catch (AbstractValidationError) {
            // Expected: choice value is not in the enum
        }

        $elapsed = microtime(true) - $startTime;

        $this->assertLessThan(1.0, $elapsed, 'Huge enum schema must validate within 1 second');
    }

    /**
     * SE-06 Positive: apiKey in cookie with session_id present must pass
     * security validation and return the matched operation. The cookie
     * value extracted via PSR-7 must match the value supplied.
     */
    #[Test]
    public function cookie_api_key_with_session_id_passes_security_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_AUTH_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/protected')
            ->withCookieParams(['session_id' => 'abc123']);

        $this->assertSame('abc123', $request->getCookieParams()['session_id']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/protected', $operation->path);
    }

    /**
     * SE-06 Negative: request without the session_id cookie must fail
     * security validation with a MissingSecurityCredentialsError wrapped
     * in ValidationException. The error must reference the cookie location
     * and the expected cookie name.
     */
    #[Test]
    public function cookie_api_key_without_session_id_throws_missing_credentials(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_AUTH_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/protected');

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException for missing session_id cookie');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertCount(1, $errors);

            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);

            $params = $error->params();
            $this->assertSame('CookieAuth', $params['schemeName']);
            $this->assertSame('apiKey', $params['schemeType']);
            $this->assertSame('missing cookie parameter "session_id"', $params['location']);
        }
    }

    /**
     * SE-06 Negative: empty session_id cookie must fail security
     * validation. The PSR-7 layer returns an empty string, which the
     * security validator treats as missing.
     */
    #[Test]
    public function cookie_api_key_with_empty_session_id_throws_missing_credentials(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_AUTH_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/protected')
            ->withCookieParams(['session_id' => '']);

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException for empty session_id cookie');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame(
                'empty cookie parameter "session_id"',
                $errors[0]->params()['location'],
            );
        }
    }

    /**
     * SE-06 Negative: cookie with a different name must not satisfy the
     * security scheme. Only the cookie declared in the scheme (session_id)
     * is accepted.
     */
    #[Test]
    public function cookie_api_key_with_wrong_cookie_name_throws_missing_credentials(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_AUTH_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/protected')
            ->withCookieParams(['other_cookie' => 'abc123']);

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException when cookie name does not match scheme');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame(
                'missing cookie parameter "session_id"',
                $errors[0]->params()['location'],
            );
        }
    }

    /**
     * SE-06 Default: when enableSecurityValidation() is not called, a
     * missing cookie must not trigger any security error. This proves the
     * opt-in nature of security validation.
     */
    #[Test]
    public function cookie_api_key_skipped_when_security_validation_disabled(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_AUTH_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/protected');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/protected', $operation->path);
    }

    /**
     * Measures validation time, catching InvalidPatternException which is
     * the expected exception when PCRE backtrack limit is triggered.
     */
    private function measureTime(
        OpenApiValidator $validator,
        ServerRequestInterface $request,
    ): float {
        $startTime = microtime(true);

        try {
            $validator->validateRequest($request);
        } catch (InvalidPatternException) {
            // Expected: PCRE backtrack limit prevents catastrophic backtracking
        }

        return microtime(true) - $startTime;
    }

    /**
     * Generates an OpenAPI spec with a chain of $depth $ref pointers.
     * N0 → N1 → N2 → … → N(depth-1) where the last schema is concrete.
     */
    private function buildDeepRefChainSpec(int $depth): string
    {
        $yaml = "openapi: 3.1.0\n"
            . "info:\n"
            . "  title: Deep Ref Chain Test\n"
            . "  version: 1.0.0\n"
            . "paths:\n"
            . "  /test:\n"
            . "    post:\n"
            . "      requestBody:\n"
            . "        required: true\n"
            . "        content:\n"
            . "          application/json:\n"
            . "            schema:\n"
            . '              $ref: "#/components/schemas/N0"' . "\n"
            . "      responses:\n"
            . "        '200':\n"
            . "          description: OK\n"
            . "components:\n"
            . "  schemas:\n";

        for ($i = 0; $i < $depth; ++$i) {
            $next = $i + 1;

            if ($i < $depth - 1) {
                $yaml .= "    N{$i}:\n"
                    . '      $ref: "#/components/schemas/N' . $next . '"' . "\n";
            } else {
                $yaml .= "    N{$i}:\n"
                    . "      type: object\n"
                    . "      properties:\n"
                    . "        name:\n"
                    . "          type: string\n";
            }
        }

        return $yaml;
    }
}
