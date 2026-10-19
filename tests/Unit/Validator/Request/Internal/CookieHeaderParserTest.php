<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Request\Internal\CookieHeaderParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;
use function sprintf;

/** @internal */
final class CookieHeaderParserTest extends TestCase
{
    private CookieHeaderParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CookieHeaderParser();
    }

    #[Test]
    public function parse_pairs_splits_name_value_pairs(): void
    {
        $result = $this->parser->parsePairs('name=value; name2=value2');

        $this->assertSame(['name' => 'value', 'name2' => 'value2'], $result);
    }

    #[Test]
    public function parse_pairs_returns_empty_array_for_blank_header(): void
    {
        $this->assertSame([], $this->parser->parsePairs(''));
        $this->assertSame([], $this->parser->parsePairs('   '));
        $this->assertSame([], $this->parser->parsePairs(';;;'));
    }

    #[Test]
    public function parse_pairs_preserves_flag_cookie_with_empty_value(): void
    {
        $result = $this->parser->parsePairs('sessionId=abc; flag');

        $this->assertSame(['sessionId' => 'abc', 'flag' => ''], $result);
    }

    #[Test]
    public function parse_pairs_splits_on_first_equals_only(): void
    {
        $result = $this->parser->parsePairs('key=val=ue');

        $this->assertSame(['key' => 'val=ue'], $result);
    }

    #[Test]
    public function parse_pairs_last_duplicate_name_wins(): void
    {
        $result = $this->parser->parsePairs('tag=a;tag=b');

        $this->assertSame(['tag' => 'b'], $result);
    }

    #[Test]
    public function parse_pairs_keeps_raw_encoded_values(): void
    {
        $result = $this->parser->parsePairs('session=abc%20123');

        $this->assertSame(['session' => 'abc%20123'], $result);
    }

    #[Test]
    public function parse_pairs_accepts_max_pairs(): void
    {
        $header = implode('; ', $this->pairNames(100));

        $result = $this->parser->parsePairs($header);

        $this->assertCount(100, $result);
    }

    #[Test]
    public function parse_pairs_throws_when_pair_count_exceeded(): void
    {
        $header = implode('; ', $this->pairNames(101));

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid parameter configuration');

        $this->parser->parsePairs($header);
    }

    #[Test]
    public function parse_exploded_values_collects_all_occurrences_decoded(): void
    {
        $result = $this->parser->parseExplodedValues('tags=hello%20world;tags=foo%2Bbar', 'tags');

        $this->assertSame(['hello world', 'foo+bar'], $result);
    }

    #[Test]
    public function parse_exploded_values_skips_other_names_and_flag_pairs(): void
    {
        $result = $this->parser->parseExplodedValues('tag=a;other=b;flag;tag=c', 'tag');

        $this->assertSame(['a', 'c'], $result);
    }

    #[Test]
    public function parse_exploded_values_returns_empty_list_without_match(): void
    {
        $result = $this->parser->parseExplodedValues('other=value', 'tag');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_exploded_values_throws_when_pair_count_exceeded(): void
    {
        $pairs = [];
        for ($i = 0; $i < 101; ++$i) {
            $pairs[] = sprintf('tag=value%d', $i);
        }
        $header = implode('; ', $pairs);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Invalid parameter configuration');

        $this->parser->parseExplodedValues($header, 'tag');
    }

    /**
     * @return list<string>
     */
    private function pairNames(int $count): array
    {
        $pairs = [];
        for ($i = 0; $i < $count; ++$i) {
            $pairs[] = sprintf('name%d=value%d', $i, $i);
        }

        return $pairs;
    }
}
