<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class IdnHostnameValidator extends AbstractStringFormatValidator
{
    private readonly HostnameValidator $inner;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->inner = new HostnameValidator($this->pregExecutor);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'idn-hostname';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        try {
            $this->inner->validate($data);
        } catch (InvalidFormatException $e) {
            throw new InvalidFormatException('idn-hostname', $data, $e->getMessage());
        }
    }
}
