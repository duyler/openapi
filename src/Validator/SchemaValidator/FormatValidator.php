<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\FormatAssertionMode;
use Override;

use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function sprintf;

final readonly class FormatValidator implements KeywordApplicable
{
    private const string DOMAIN_STRING = 'string';

    private const string DOMAIN_NUMBER = 'number';

    private const string DOMAIN_INTEGER = 'integer';

    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
    ) {}

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->format;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->format || false === is_scalar($data)) {
            return;
        }

        $type = is_array($schema->type)
            ? $this->firstStringType($schema->type)
            : $schema->type;

        if (null === $type && null !== $schema->type) {
            return;
        }

        $context?->annotate('format', $schema->format, $context->path());

        if (FormatAssertionMode::Annotation === $this->dependencies->formatAssertionMode) {
            return;
        }

        $formatValidator = null === $type
            ? $this->instanceFormatValidator($schema->format, $data)
            : $this->dependencies->formatRegistry->getValidator($type, $schema->format);

        if (null === $formatValidator) {
            if ($this->dependencies->strictFormats && $this->isUnresolvableFormat($schema->format, $type)) {
                throw new InvalidFormatException(
                    $schema->format,
                    $data,
                    null === $type
                        ? sprintf('Unknown format "%s" in strict mode', $schema->format)
                        : sprintf('Unknown format "%s" for type "%s" in strict mode', $schema->format, $type),
                );
            }

            return;
        }

        $formatValidator->validate($data);
    }

    private function instanceFormatValidator(string $format, mixed $data): ?FormatValidatorInterface
    {
        $instanceDomain = $this->instanceDomain($data);

        foreach ($this->dependencies->formatRegistry->types($format) as $registryType) {
            if ($this->domainAccepts($registryType, $instanceDomain)) {
                return $this->dependencies->formatRegistry->getValidator($registryType, $format);
            }
        }

        return null;
    }

    private function instanceDomain(mixed $data): ?string
    {
        return match (true) {
            is_string($data) => self::DOMAIN_STRING,
            is_int($data) => self::DOMAIN_INTEGER,
            is_float($data) => self::DOMAIN_NUMBER,
            default => null,
        };
    }

    private function domainAccepts(string $registryType, ?string $instanceDomain): bool
    {
        if (null === $instanceDomain) {
            return self::DOMAIN_STRING !== $registryType
                && self::DOMAIN_NUMBER !== $registryType
                && self::DOMAIN_INTEGER !== $registryType;
        }

        return match ($registryType) {
            self::DOMAIN_STRING => self::DOMAIN_STRING === $instanceDomain,
            self::DOMAIN_NUMBER => self::DOMAIN_INTEGER === $instanceDomain || self::DOMAIN_NUMBER === $instanceDomain,
            self::DOMAIN_INTEGER => self::DOMAIN_INTEGER === $instanceDomain,
            default => true,
        };
    }

    private function isUnresolvableFormat(string $format, ?string $type): bool
    {
        if (null !== $type) {
            return true;
        }

        return [] === $this->dependencies->formatRegistry->types($format);
    }

    /**
     * @param list<string|null> $types
     */
    private function firstStringType(array $types): ?string
    {
        foreach ($types as $type) {
            if (is_string($type)) {
                return $type;
            }
        }

        return null;
    }
}
