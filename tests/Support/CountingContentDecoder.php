<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Validator\ContentDecoderInterface;
use Override;

use function base64_decode;

final class CountingContentDecoder implements ContentDecoderInterface
{
    public int $calls = 0;

    #[Override]
    public function decodeBase64(string $data): string|false
    {
        ++$this->calls;

        return base64_decode($data, strict: true);
    }
}
