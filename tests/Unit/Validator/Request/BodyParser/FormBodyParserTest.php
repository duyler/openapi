<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class FormBodyParserTest extends TestCase
{
    private FormBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FormBodyParser();
    }

    #[Test]
    public function parse_form_body(): void
    {
        $body = 'name=John&age=30';
        $result = $this->parser->parse($body);

        $this->assertSame(['name' => 'John', 'age' => '30'], $result);
    }

    #[Test]
    public function parse_empty_form_body(): void
    {
        $body = '';
        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }
}
