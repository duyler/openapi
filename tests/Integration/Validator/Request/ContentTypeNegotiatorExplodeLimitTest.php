<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Request;

use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/** @internal */
final class ContentTypeNegotiatorExplodeLimitTest extends TestCase
{
    private ContentTypeNegotiator $negotiator;

    protected function setUp(): void
    {
        $this->negotiator = new ContentTypeNegotiator();
    }

    #[Test]
    public function large_param_count_rejected(): void
    {
        // 21 params (1 media type + 20 parameters separated by 20 semicolons)
        // MAX_CONTENT_TYPE_PARAMS = 20 → 21 items via 20 separators must be rejected.
        $contentType = 'application/json' . str_repeat('; p=v', 20);

        $this->expectException(InvalidArgumentException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function normal_param_count_accepted(): void
    {
        // 3 params (application/json, charset=utf-8, boundary=xyz) — under the 20 cap.
        $contentType = 'application/json; charset=utf-8; boundary=xyz';

        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }
}
