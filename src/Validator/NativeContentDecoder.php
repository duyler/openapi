<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Override;

use function base64_decode;

final readonly class NativeContentDecoder implements ContentDecoderInterface
{
    #[Override]
    public function decodeBase64(string $data): string|false
    {
        return base64_decode($data, strict: true);
    }
}
