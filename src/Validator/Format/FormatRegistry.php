<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format;

final readonly class FormatRegistry
{
    /**
     * @param array<string, array<string, FormatValidatorInterface>> $validators
     */
    public function __construct(
        private readonly array $validators = [],
    ) {}

    public function registerFormat(
        string $type,
        string $format,
        FormatValidatorInterface $validator,
    ): self {
        $newValidators = $this->validators;
        $newValidators[$type][$format] = $validator;

        return new self($newValidators);
    }

    public function getValidator(string $type, string $format): ?FormatValidatorInterface
    {
        return $this->validators[$type][$format] ?? null;
    }

    public function hasFormat(string $type, string $format): bool
    {
        return isset($this->validators[$type][$format]);
    }

    /**
     * @return array<string, array<string, FormatValidatorInterface>>
     */
    public function all(): array
    {
        return $this->validators;
    }
}
