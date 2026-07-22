<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

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
     * Returns a new registry whose entries are the union of $base and
     * the current registry's entries, with the current registry's
     * entries overriding $base on (type, format) conflict.
     *
     * Used by {@see OpenApiValidatorBuilder::build()}
     * to ensure builtin format validators share the configured
     * PregExecutor even when the caller registered custom formats via
     * withFormat() before withMaxRegexBacktracks().
     */
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
