<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;

/**
 * @psalm-external-mutation-free
 */
trait SanitizableExceptionTrait
{
    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
