<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidEncodingConfigurationException;

use function in_array;
use function is_array;

/** @internal */
final readonly class OrderedMultipartPlanner
{
    public function __construct(
        private readonly MultipartInstanceResolver $instanceResolver,
    ) {}

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    public function plan(array $parts, MediaType $mediaType, Schema $schema): OrderedMultipartPlan
    {
        $this->assertConfiguration($mediaType, $schema);

        return new OrderedMultipartPlan(
            parts: $parts,
            prefixEncoding: $mediaType->prefixEncoding,
            itemEncoding: $mediaType->itemEncoding,
            instance: $this->instanceResolver->positionalValues(
                parts: $parts,
                prefixEncoding: $mediaType->prefixEncoding,
                itemEncoding: $mediaType->itemEncoding,
                schema: $schema,
            ),
        );
    }

    public function apply(OrderedMultipartPlan $plan, MultipartEncodingApplier $applier): void
    {
        $plan->applyTo($applier);
    }

    public function assertConfiguration(MediaType $mediaType, ?Schema $schema): void
    {
        if (null !== $mediaType->encoding) {
            throw InvalidEncodingConfigurationException::mixedModes();
        }

        if (false === $this->hasArrayModel($mediaType, $schema)) {
            throw InvalidEncodingConfigurationException::missingArrayModel();
        }
    }

    private function hasArrayModel(MediaType $mediaType, ?Schema $schema): bool
    {
        if (null !== $mediaType->itemSchema) {
            return true;
        }

        if (null === $schema) {
            return false;
        }

        $type = $schema->type;

        if (is_array($type)) {
            return in_array('array', $type, true);
        }

        if ('array' === $type) {
            return true;
        }

        return null !== $schema->prefixItems || null !== $schema->items;
    }
}
