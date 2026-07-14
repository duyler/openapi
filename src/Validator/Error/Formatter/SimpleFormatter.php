<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Override;

use function sprintf;

final class SimpleFormatter implements ErrorFormatterInterface
{
    #[Override]
    public function format(ValidationErrorInterface $error): string
    {
        $breadcrumb = $error->dataPath();
        $message = $error->message();

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
