<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Example;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\MediaType;
use Psr\EventDispatcher\EventDispatcherInterface;

use function sprintf;

trait ValidatesExamplesTrait
{
    private function dispatchExampleWarnings(
        mixed $data,
        MediaType $mediaType,
        ExampleValidator $exampleValidator,
        ?EventDispatcherInterface $eventDispatcher,
    ): void {
        $warnings = $exampleValidator->validateMediaType($data, $mediaType);

        $schema = $mediaType->schema;

        if (null !== $schema) {
            $warnings = [...$warnings, ...$exampleValidator->validate($data, $schema)];
        }

        foreach ($warnings as $warning) {
            $eventDispatcher?->dispatch(
                new ValidationWarningEvent(
                    message: sprintf('%s (data: %s)', $warning->message, $warning->data),
                ),
            );
        }
    }
}
