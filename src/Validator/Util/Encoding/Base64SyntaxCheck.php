<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\ContentDecoderInterface;
use Override;

use function base64_encode;

final readonly class Base64SyntaxCheck implements EncodingSyntaxCheck
{
    public function __construct(
        private readonly ContentDecoderInterface $contentDecoder,
    ) {}

    #[Override]
    public function isValid(string $data): bool
    {
        $decoded = $this->contentDecoder->decodeBase64($data);

        return false !== $decoded && base64_encode($decoded) === $data;
    }
}
