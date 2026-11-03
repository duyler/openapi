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
     * @return list<string>
     */
    public function types(string $format): array
    {
        $types = [];

        foreach ($this->validators as $type => $formats) {
            if (isset($formats[$format])) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function withBase(self $base): self
    {
        $merged = $base->validators;

        foreach ($this->validators as $type => $formats) {
            foreach ($formats as $format => $validator) {
                $merged[$type][$format] = $validator;
            }
        }

        return new self($merged);
    }
}
