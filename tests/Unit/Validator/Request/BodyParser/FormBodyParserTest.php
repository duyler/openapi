<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\QueryParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class FormBodyParserTest extends TestCase
{
    private FormBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FormBodyParser(new QueryParser());
    }

    #[Test]
    public function parse_simple_form_body(): void
    {
        $body = 'name=John&age=30';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => '30'], $result);
    }

    #[Test]
    public function parse_empty_form_body_returns_empty_array(): void
    {
        $body = '';

        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_whitespace_only_form_body_returns_empty_array(): void
    {
        $body = '   ';

        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_duplicate_scalar_keys_collect_to_indexed_array(): void
    {
        $body = 'tags=php&tags=go';

        $result = $this->parser->parse($body);

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function parse_duplicate_scalar_keys_three_values_collect_to_indexed_array(): void
    {
        $body = 'tags=php&tags=go&tags=java';

        $result = $this->parser->parse($body);

        $this->assertSame(['tags' => ['php', 'go', 'java']], $result);
    }

    #[Test]
    public function parse_url_encoded_value(): void
    {
        $body = 'name=John%20Doe';

        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John Doe'], $result);
    }

    #[Test]
    public function parse_deep_object_flat_keys_preserve_associative_array(): void
    {
        $body = 'filters[name]=John&filters[age]=30';

        $result = $this->parser->parse($body);

        $this->assertSame(['filters' => ['name' => 'John', 'age' => '30']], $result);
    }

    #[Test]
    public function parse_deep_object_nested_keys_preserve_structure(): void
    {
        $body = 'filters[a][b]=value';

        $result = $this->parser->parse($body);

        $this->assertSame(['filters' => ['a' => ['b' => 'value']]], $result);
    }

    #[Test]
    public function parse_bracket_append_syntax_collects_to_indexed_array(): void
    {
        $body = 'tags[]=php&tags[]=go';

        $result = $this->parser->parse($body);

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function parse_comma_separated_value_stays_scalar(): void
    {
        $body = 'tags=php,go';

        $result = $this->parser->parse($body);

        $this->assertSame(['tags' => 'php,go'], $result);
    }
}
