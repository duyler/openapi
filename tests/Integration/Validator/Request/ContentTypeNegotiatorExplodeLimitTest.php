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
        $contentType = 'application/json' . str_repeat('; p=v', 20);

        $this->expectException(InvalidArgumentException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function normal_param_count_accepted(): void
    {
        $contentType = 'application/json; charset=utf-8; boundary=xyz';

        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }
}
