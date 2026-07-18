<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function is_scalar;
use function sprintf;

final readonly class DetailedFormatter implements ErrorFormatterInterface
{
    public function __construct(
        private bool $includeSensitiveValues = false,
    ) {}

    #[Override]
    public function format(ValidationErrorInterface $error): string
    {
        $output = sprintf("Error at %s:\n", $error->dataPath());
        $output .= sprintf("  Message: %s\n", $error->message());

        $details = [];
        foreach ($error->params() as $key => $value) {
            if (is_scalar($value)) {
                $details[$key] = (string) $value;
            }
        }

        if ($this->includeSensitiveValues
            && $error instanceof InvalidFormatException
            && is_scalar($error->value)
        ) {
            $details['value'] = (string) $error->value;
        }

        if ([] !== $details) {
            $output .= "  Details:\n";
            foreach ($details as $key => $value) {
                $output .= sprintf("    %s: %s\n", $key, $value);
            }
        }

        $suggestion = $error->suggestion();
        if (null !== $suggestion) {
            $output .= sprintf("  Suggestion: %s\n", $suggestion);
        }

        return rtrim($output);
    }

    #[Override]
    public function formatMultiple(array $errors): string
    {
        $formattedErrors = array_map(
            $this->format(...),
            $errors,
        );

        return implode("\n\n", $formattedErrors);
    }

    #[Override]
    public function formatException(ValidationException $exception): string
    {
        return $this->formatMultiple($exception->getErrors());
    }
}
