<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class ConstError extends AbstractValidationError
{
    public function __construct(
        mixed $expected,
        mixed $actual,
        string $dataPath,
        string $schemaPath,
    ) {
        $expectedJson = json_encode($expected);
        $actualJson = json_encode($actual);

        if ($expectedJson === false) {
            $expectedJson = 'null';
        }

        if ($actualJson === false) {
            $actualJson = 'null';
        }

        $message = sprintf('Value "%s" does not match const value "%s" at %s', $actualJson, $expectedJson, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'const',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['expected' => $expected, 'actual' => $actual],
            suggestion: sprintf('Use const value: %s', $expectedJson),
        );
    }
}
