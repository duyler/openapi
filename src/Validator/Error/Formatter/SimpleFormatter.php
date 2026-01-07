<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Override;

/**
 * Simple error formatter with concise messages
 *
 * Provides brief error messages including breadcrumb path.
 */
readonly class SimpleFormatter implements ErrorFormatterInterface
{
    #[Override]
    public function format(AbstractValidationError $error): string
    {
        $breadcrumb = $error->dataPath();
        $message = $error->getMessage();

        if ('/' === $breadcrumb) {
            return $message;
        }

        return sprintf('[%s] %s', $breadcrumb, $message);
    }

    #[Override]
    public function formatMultiple(array $errors): string
    {
        $lines = array_map(
            $this->format(...),
            $errors,
        );

        return implode("\n", $lines);
    }
}
