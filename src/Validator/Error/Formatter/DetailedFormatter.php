<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Override;

use function is_scalar;
use function sprintf;

/**
 * Detailed error formatter with comprehensive information
 *
 * Provides detailed error messages with suggestions and context.
 */
readonly class DetailedFormatter implements ErrorFormatterInterface
{
    #[Override]
    public function format(AbstractValidationError $error): string
    {
        $breadcrumb = $error->dataPath();
        $message = $error->getMessage();
        $details = $this->getDetails($error);
        $suggestion = $error->suggestion();

        $output = sprintf("Error at %s:\n", $breadcrumb);
        $output .= sprintf("  Message: %s\n", $message);

        if ([] !== $details) {
            $output .= "  Details:\n";
            foreach ($details as $key => $value) {
                $output .= sprintf("    %s: %s\n", $key, $value);
            }
        }

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

    /**
     * @return array<string, string>
     */
    private function getDetails(AbstractValidationError $error): array
    {
        $params = $error->params();
        $details = [];

        foreach ($params as $key => $value) {
            if (is_scalar($value)) {
                $details[$key] = (string) $value;
            }
        }

        return $details;
    }
}
