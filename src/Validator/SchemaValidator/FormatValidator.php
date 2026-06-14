<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function is_array;
use function sprintf;

final readonly class FormatValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly FormatRegistry $formatRegistry,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->format || null === $schema->type) {
            return;
        }

        $type = is_array($schema->type) ? $schema->type[0] : $schema->type;
        $formatValidator = $this->formatRegistry->getValidator($type, $schema->format);

        if (null === $formatValidator) {
            if ($this->strictFormats) {
                throw new InvalidFormatException(
                    $schema->format,
                    $data,
                    sprintf('Unknown format "%s" for type "%s" in strict mode', $schema->format, $type),
                );
            }

            return;
        }

        $formatValidator->validate($data);
    }
}
