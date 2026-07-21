<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;

final readonly class IdnEmailValidator extends AbstractStringFormatValidator
{
    private readonly EmailValidator $inner;

    public function __construct(
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->inner = new EmailValidator($this->pregExecutor);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'idn-email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        try {
            $this->inner->validate($data);
        } catch (InvalidFormatException $e) {
            throw new InvalidFormatException('idn-email', $data, $e->getMessage());
        }
    }
}
