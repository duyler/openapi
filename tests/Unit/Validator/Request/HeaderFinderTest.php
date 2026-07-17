<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\HeaderFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class HeaderFinderTest extends TestCase
{
    private HeaderFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new HeaderFinder();
    }

    #[Test]
    public function find_existing_header_as_string(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $result = $this->finder->find($headers, 'Content-Type');

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function find_existing_header_as_array(): void
    {
        $headers = ['Accept' => ['application/json', 'application/xml']];
        $result = $this->finder->find($headers, 'Accept');

        self::assertSame('application/json,application/xml', $result);
    }

    /**
     * P-015: RFC 9110 §5.2 — multiple field lines with the same name are
     * equivalent to a single line whose values are joined by a comma
     * WITHOUT a space, so the merged value round-trips correctly through
     * simple/pipeDelimited style explode which splits on the bare comma.
     */
    #[Test]
    public function merges_multi_value_headers_with_comma_no_space(): void
    {
        $headers = ['x-foo' => ['a', 'b']];

        $result = $this->finder->find($headers, 'x-foo');

        self::assertSame('a,b', $result);
    }

    #[Test]
    public function preserves_single_value_header(): void
    {
        $headers = ['x-foo' => 'a'];

        $result = $this->finder->find($headers, 'x-foo');

        self::assertSame('a', $result);
    }

    #[Test]
    public function return_null_when_header_not_found(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $result = $this->finder->find($headers, 'X-Custom-Header');

        $this->assertNull($result);
    }

    #[Test]
    public function use_case_insensitive_matching_lowercase(): void
    {
        $headers = ['content-type' => 'application/json'];
        $result = $this->finder->find($headers, 'Content-Type');

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function use_case_insensitive_matching_uppercase(): void
    {
        $headers = ['CONTENT-TYPE' => 'application/json'];
        $result = $this->finder->find($headers, 'Content-Type');

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function use_case_insensitive_matching_mixed_case(): void
    {
        $headers = ['X-Custom-Header' => 'value'];
        $result = $this->finder->find($headers, 'x-custom-header');

        $this->assertSame('value', $result);
    }

    #[Test]
    public function ignore_numeric_keys(): void
    {
        $headers = [0 => 'ignored', 'X-Custom' => 'value'];
        $result = $this->finder->find($headers, 'X-Custom');

        $this->assertSame('value', $result);
    }

    #[Test]
    public function return_null_for_non_string_value(): void
    {
        $headers = ['X-Custom' => 123];
        $result = $this->finder->find($headers, 'X-Custom');

        $this->assertNull($result);
    }

    #[Test]
    public function return_null_for_boolean_value(): void
    {
        $headers = ['X-Custom' => true];
        $result = $this->finder->find($headers, 'X-Custom');

        $this->assertNull($result);
    }

    #[Test]
    public function handle_empty_array_value(): void
    {
        $headers = ['X-Custom' => []];
        $result = $this->finder->find($headers, 'X-Custom');

        $this->assertSame('', $result);
    }

    #[Test]
    public function handle_array_with_single_element(): void
    {
        $headers = ['X-Custom' => ['value']];
        $result = $this->finder->find($headers, 'X-Custom');

        $this->assertSame('value', $result);
    }
}
