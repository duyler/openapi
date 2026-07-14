<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;
use function sprintf;

/** @internal */
#[CoversClass(CookieValidator::class)]
final class CookieValidatorPairCountLimitTest extends TestCase
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
    public function accepts_cookies_at_max_pairs(): void
    {
        $pairs = [];
        for ($i = 0; $i < 100; ++$i) {
            $pairs[] = sprintf('name%d=value%d', $i, $i);
        }
        $cookieHeader = implode('; ', $pairs);

        $result = $this->validator->parseCookies($cookieHeader);

        self::assertCount(100, $result);
    }

    #[Test]
    public function returns_empty_for_excessive_cookies(): void
    {
        $pairs = [];
        for ($i = 0; $i < 101; ++$i) {
            $pairs[] = sprintf('name%d=value%d', $i, $i);
        }
        $cookieHeader = implode('; ', $pairs);

        $result = $this->validator->parseCookies($cookieHeader);

        self::assertSame([], $result);
    }

    #[Test]
    public function empty_cookie_header_still_returns_empty_array(): void
    {
        $result = $this->validator->parseCookies('');

        self::assertSame([], $result);
    }

    #[Test]
    public function whitespace_only_cookie_header_still_returns_empty_array(): void
    {
        $result = $this->validator->parseCookies('   ');

        self::assertSame([], $result);
    }

    #[Test]
    public function parse_cookie_style_exploded_returns_null_when_pair_count_exceeded(): void
    {
        $parameter = new Parameter(
            name: 'tag',
            in: 'cookie',
            explode: true,
        );

        $pairs = [];
        for ($i = 0; $i < 101; ++$i) {
            $pairs[] = sprintf('tag=value%d', $i);
        }
        $cookieHeader = implode('; ', $pairs);

        $result = $this->validator->parseCookieStyle($cookieHeader, $parameter);

        self::assertNull($result);
    }

    #[Test]
    public function parse_cookie_style_exploded_accepts_max_pairs(): void
    {
        $parameter = new Parameter(
            name: 'tag',
            in: 'cookie',
            explode: true,
        );

        $pairs = [];
        for ($i = 0; $i < 100; ++$i) {
            $pairs[] = sprintf('tag=value%d', $i);
        }
        $cookieHeader = implode('; ', $pairs);

        $result = $this->validator->parseCookieStyle($cookieHeader, $parameter);

        self::assertCount(100, $result);
    }
}
