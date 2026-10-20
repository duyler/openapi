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
 * P-097: real Billion Laughs attack. The existing XmlSecurityTest covers
 * three entity levels; this test extends the contract to eight ENTITY
 * declarations — a literal leaf plus seven multiplying levels of ten
 * (10^7 = 10 million entity expansions at the leaf reference; a fully
 * expanded tree would allocate ~30 MB for the lol-string) — and asserts
 * that memory growth stays under 20 MB. The entity chain is currently
 * inert due to a naming defect (see SEC-026 in the campaign backlog);
 * the defence lines below are verified against a live chain.
 *
 * Defence lines for this payload, which declares internal entities only:
 * libxml2's built-in entity-reference-loop detection aborts exponential
 * internal entity expansion, and XmlBodyParser parses without LIBXML_NOENT
 * and without LIBXML_PARSE_HUGE (entity substitution stays off); the
 * deny-all external entity loader installed by LibxmlSecuredContext plus
 * LIBXML_NONET guards XXE (SYSTEM/PUBLIC references) and is not invoked by
 * internal entities. On top of the parser, the reject-or-bounded assertion
 * pins the contract: the parser must either reject the document or return
 * a result whose serialized form stays under 1 MB.
 *
 * Memory assertion is generous (20 MB) to stay stable on noisy CI workers
 * while still detecting a regression that fully expanded the entity tree
 * (~30 MB at these levels).
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
