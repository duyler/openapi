<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function hrtime;
use function implode;
use function sprintf;

final class YamlParserBombTest extends TestCase
{
    private YamlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlParser();
    }

    #[Test]
    public function seven_level_ten_arity_chain_bomb_is_rejected_under_100ms(): void
    {
        $payload = $this->buildChainBomb(levels: 7, arity: 10);

        $start = hrtime(true);
        try {
            $this->parser->parse($payload);
            self::fail('Expected SpecTooLargeException was not thrown');
        } catch (SpecTooLargeException $e) {
            $elapsedNs = hrtime(true) - $start;
            $elapsedMs = (int) ($elapsedNs / 1_000_000);
            self::assertLessThan(
                100,
                $elapsedMs,
                sprintf('Billion-laughs defence took %d ms; expected <100 ms', $elapsedMs),
            );
            self::assertStringNotContainsString($payload, $e->getMessage());
        }
    }

    #[Test]
    public function four_level_ten_arity_legitimate_dedup_still_passes(): void
    {
        $payload = $this->buildChainBomb(levels: 4, arity: 10);

        $document = $this->parser->parse($payload);

        self::assertSame('3.0.3', $document->openapi);
        self::assertNotNull($document->components);
        self::assertNotNull($document->components->schemas);
        self::assertArrayHasKey('lvl0', $document->components->schemas);
        self::assertArrayHasKey('lvl3', $document->components->schemas);
    }

    #[Test]
    public function horizontal_bomb_at_depth_cap_high_arity_caught_by_size_cap(): void
    {
        $payload = $this->buildChainBomb(levels: 4, arity: 40);

        try {
            $this->parser->parse($payload);
            self::fail('Expected SpecTooLargeException was not thrown');
        } catch (SpecTooLargeException $e) {
            self::assertStringContainsString('Expanded YAML payload of', $e->getMessage());
            self::assertStringNotContainsString($payload, $e->getMessage());
        }
    }

    #[Test]
    public function cr_lf_line_ending_chain_bomb_is_rejected(): void
    {
        $payload = $this->buildChainBomb(levels: 7, arity: 5, lineSeparator: "\r\n");

        try {
            $this->parser->parse($payload);
            self::fail('Expected SpecTooLargeException was not thrown');
        } catch (SpecTooLargeException $e) {
            self::assertStringContainsString('alias nesting too deep', $e->getMessage());
            self::assertStringNotContainsString($payload, $e->getMessage());
        }
    }

    #[Test]
    public function cr_only_line_ending_chain_bomb_is_rejected(): void
    {
        $payload = $this->buildChainBomb(levels: 7, arity: 5, lineSeparator: "\r");

        try {
            $this->parser->parse($payload);
            self::fail('Expected SpecTooLargeException was not thrown');
        } catch (SpecTooLargeException $e) {
            self::assertStringContainsString('alias nesting too deep', $e->getMessage());
            self::assertStringNotContainsString($payload, $e->getMessage());
        }
    }

    private function buildChainBomb(int $levels, int $arity, string $lineSeparator = "\n"): string
    {
        $lines = [];
        $inner = implode(',', array_fill(0, $arity, '"x"'));
        $lines[] = "lvl0: &lvl0 [{$inner}]";

        for ($i = 1; $i < $levels; ++$i) {
            $refs = implode(',', array_fill(0, $arity, "*lvl" . ($i - 1)));
            $lines[] = "lvl{$i}: &lvl{$i} [{$refs}]";
        }

        $header = "openapi: 3.0.3"
            . $lineSeparator . "info:"
            . $lineSeparator . "  title: Bomb"
            . $lineSeparator . "  version: 1.0.0"
            . $lineSeparator . "paths: {}"
            . $lineSeparator . "components:"
            . $lineSeparator . "  schemas:"
            . $lineSeparator . "    ";

        return $header . implode($lineSeparator . "    ", $lines) . $lineSeparator;
    }
}
