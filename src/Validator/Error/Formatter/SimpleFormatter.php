<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Override;

use function sprintf;

/**
 * Simple error formatter with concise messages
 *
 * Provides brief error messages including breadcrumb path.
 */
final class SimpleFormatter implements ErrorFormatterInterface
{
    private static ?self $shared = null;

    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

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
