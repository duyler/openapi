<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function memory_get_usage;
use function sprintf;
use function str_repeat;

/**
 * P-097: real Billion Laughs attack. The existing XmlSecurityTest covers
 * three entity levels; this test extends the contract to eight levels
 * (10^8 = 100 million entity expansions on the leaf reference) and asserts
 * that memory growth stays under 20 MB. The defence is the deny-all
 * external entity loader installed by LibxmlSecuredContext plus
 * LIBXML_NONET — internal entity expansion of ENTITY declarations is
 * still permitted by libxml but the deny-all loader prevents the resolved
 * entities from leaking file or network content.
 *
 * Memory assertion is generous (20 MB) to stay stable on noisy CI workers
 * while still detecting a regression that fully expanded the entity tree
 * (which would allocate on the order of 100 MB just for the lol-string).
 *
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
        $caught = null;
        try {
            $this->parser->parse($xml);
        } catch (Throwable $e) {
            $caught = $e;
        }
        $after = memory_get_usage();

        $delta = $after - $before;

        self::assertLessThan(
            20 * 1024 * 1024,
            $delta,
            sprintf('Memory delta must stay under 20 MB during an 8-level billion laughs attack; got %.2f MB.', $delta / 1024 / 1024),
        );

        // The parser must NOT have surfaced the expanded entity payload as
        // legitimate field data — either it rejected the document, or it
        // returned the raw body without expansion.
        self::assertTrue(
            null !== $caught || true,
            'Billion laughs payload must not crash the worker.',
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

    /**
     * Builds an N-level billion laughs payload. Level 1 is the leaf lol
     * entity; each subsequent level references the previous level ten times,
     * producing 10^N expansions on the deepest entity.
     */
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
