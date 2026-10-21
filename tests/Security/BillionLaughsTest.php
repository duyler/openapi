<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function json_encode;
use function memory_get_usage;
use function sprintf;
use function str_repeat;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class BillionLaughsTest extends TestCase
{
    private XmlBodyParser $parser;

    #[Override]
    protected function setUp(): void
    {
        $this->parser = new XmlBodyParser();
    }

    #[Test]
    public function memory_stays_under_20mb_on_eight_level_entity_expansion(): void
    {
        $xml = $this->buildBillionLaughs(8);

        $before = memory_get_usage();
        $result = null;
        $caught = null;
        try {
            $result = $this->parser->parse($xml);
        } catch (Throwable $e) {
            $caught = $e;
        }

        $delta = memory_get_usage() - $before;

        self::assertLessThan(
            20 * 1024 * 1024,
            $delta,
            sprintf('Memory delta must stay under 20 MB during an 8-level billion laughs attack; got %.2f MB.', $delta / 1024 / 1024),
        );

        $isResultBounded = null === $result || strlen(json_encode($result, JSON_THROW_ON_ERROR)) < 1_048_576;

        self::assertTrue(
            null !== $caught || $isResultBounded,
            'Parser must reject the billion laughs document or return a bounded result without entity expansion.',
        );
    }

    #[Test]
    public function memory_stays_under_20mb_on_six_level_entity_expansion(): void
    {
        $xml = $this->buildBillionLaughs(6);

        $before = memory_get_usage();
        try {
            $this->parser->parse($xml);
        } catch (Throwable) {
        }
        $after = memory_get_usage();

        $delta = $after - $before;

        self::assertLessThan(
            20 * 1024 * 1024,
            $delta,
            sprintf('Memory delta must stay under 20 MB during a 6-level billion laughs attack; got %.2f MB.', $delta / 1024 / 1024),
        );
    }

    private function buildBillionLaughs(int $levels): string
    {
        $header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE lolz [\n";
        $header .= "  <!ENTITY lol \"lol\">\n";

        for ($i = 2; $i <= $levels; ++$i) {
            $previous = 'lol' . ($i - 1);
            $current = 'lol' . $i;
            $header .= '  <!ENTITY ' . $current . ' "'
                . str_repeat('&' . $previous . ';', 10)
                . "\">\n";
        }

        $leaf = 'lol' . $levels;

        return $header . "]>\n<root><value>&" . $leaf . ';</value></root>';
    }
}
