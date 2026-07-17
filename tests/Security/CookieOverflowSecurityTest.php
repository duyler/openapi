<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;

use function implode;
use function range;
use function str_repeat;
use function substr_count;

/**
 * Security regression for P-028: CookieValidator previously returned an empty
 * array on overflow, silently dropping cookie state and bypassing security
 * checks for optional cookie-based schemes. The validator now fails closed
 * with {@see InvalidParameterException}, matching
 * {@see QueryParser} and
 * {@see MultipartBodyParser}.
 *
 * @internal
 */
#[CoversClass(CookieValidator::class)]
final class CookieOverflowSecurityTest extends TestCase
{
    private CookieValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $this->validator = new CookieValidator($schemaValidator, $deserializer, $coercer);
    }

    #[Test]
    public function cookie_pair_overflow_throws_exception(): void
    {
        $pairs = [];
        foreach (range(1, 150) as $i) {
            $pairs[] = 'c' . $i . '=v' . $i;
        }
        $cookieHeader = implode('; ', $pairs);

        $pairCount = substr_count($cookieHeader, ';') + 1;
        $this->assertGreaterThan(100, $pairCount);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Maximum cookie pairs of 100 exceeded');

        try {
            $this->validator->parseCookies($cookieHeader);
        } catch (InvalidParameterException $e) {
            $this->assertSame('cookie', $e->parameterName);

            throw $e;
        }
    }

    #[Test]
    public function boundary_at_100_pairs_accepted(): void
    {
        $pairs = [];
        foreach (range(1, 100) as $i) {
            $pairs[] = 'c' . $i . '=v' . $i;
        }
        $cookieHeader = implode('; ', $pairs);

        $result = $this->validator->parseCookies($cookieHeader);

        $this->assertCount(100, $result);
        $this->assertSame('v1', $result['c1']);
        $this->assertSame('v100', $result['c100']);
    }

    #[Test]
    public function parse_cookie_style_with_overflow_header_throws(): void
    {
        $pairs = [];
        foreach (range(1, 150) as $i) {
            $pairs[] = 'c' . $i . '=v' . $i;
        }
        $cookieHeader = implode('; ', $pairs);

        $parameter = new Parameter(
            name: 'c1',
            in: 'cookie',
            style: 'cookie',
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Maximum cookie pairs of 100 exceeded');

        $this->validator->parseCookieStyle($cookieHeader, $parameter);
    }

    #[Test]
    public function explode_style_overflow_throws_via_has_multiple_cookies(): void
    {
        $pairs = [];
        foreach (range(1, 150) as $i) {
            $pairs[] = 'tag=v' . $i;
        }
        $cookieHeader = implode('; ', $pairs);

        $parameter = new Parameter(
            name: 'tag',
            in: 'cookie',
            style: 'cookie',
            explode: true,
            schema: new Schema(type: 'array'),
        );

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Maximum cookie pairs of 100 exceeded');

        $this->validator->parseCookieStyle($cookieHeader, $parameter);
    }

    #[Test]
    public function large_cookie_header_does_not_return_partial_state(): void
    {
        $padding = str_repeat('a', 200);
        $pairs = [];
        foreach (range(1, 120) as $i) {
            $pairs[] = 'c' . $i . '=' . $padding . $i;
        }
        $cookieHeader = implode('; ', $pairs);

        $caught = false;
        try {
            $this->validator->parseCookies($cookieHeader);
        } catch (InvalidParameterException) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Overflow must throw rather than return a truncated cookie map');
    }
}
