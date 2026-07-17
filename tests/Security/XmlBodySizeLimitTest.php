<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function memory_get_usage;
use function sprintf;
use function str_repeat;

/**
 * P-036 regression: XmlBodyParser must reject bodies larger than 1MB by
 * default to prevent libxml from being asked to ingest unbounded
 * payloads. The billion-laughs entity expansion cap is per-entity, not
 * per-document, so a document-size cap is a necessary first line of
 * defense.
 *
 * @internal
 */
final class XmlBodySizeLimitTest extends TestCase
{
    #[Test]
    public function xml_body_parser_rejects_body_over_one_megabyte(): void
    {
        $parser = new XmlBodyParser();

        $giant = '<?xml version="1.0"?><root>' . str_repeat('<item>x</item>', 200_000) . '</root>';

        $before = memory_get_usage();
        $caught = null;

        try {
            $parser->parse($giant);
        } catch (BodyTooLargeException $e) {
            $caught = $e;
        }

        $after = memory_get_usage();
        $delta = $after - $before;

        self::assertNotNull(
            $caught,
            'XmlBodyParser must throw BodyTooLargeException on bodies > 1MB (P-036)',
        );
        self::assertSame(1_048_576, $caught->maxBytes);
        self::assertLessThan(
            5 * 1024 * 1024,
            $delta,
            sprintf('XML size-cap check must not allocate large buffers; got %.2f MB', $delta / 1024 / 1024),
        );
    }

    #[Test]
    public function xml_body_parser_accepts_body_at_one_megabyte_boundary(): void
    {
        $parser = new XmlBodyParser(maxXmlBytes: 1024);

        $caught = null;

        try {
            $parser->parse(str_repeat('x', 1024));
        } catch (BodyTooLargeException $e) {
            $caught = $e;
        }

        self::assertNull(
            $caught,
            'XmlBodyParser must accept a body whose byte length equals maxXmlBytes exactly',
        );
    }

    #[Test]
    public function xml_body_parser_respects_custom_max_bytes(): void
    {
        $parser = new XmlBodyParser(maxXmlBytes: 64);

        $caught = null;

        try {
            $parser->parse(str_repeat('x', 65));
        } catch (BodyTooLargeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(64, $caught->maxBytes);
        self::assertSame(65, $caught->actualBytes);
    }
}
