<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\FieldMetadata;

/** @internal */
final readonly class ModelWireSerializer
{
    /**
     * @param list<FieldMetadata> $fields
     * @return array<string, mixed>
     */
    public static function toArray(object $model, array $fields): array
    {
        /** @var array<string, mixed> $data */
        $data = [];

        foreach ($fields as $field) {
            /** @var mixed $value */
            $value = null !== $field->accessor ? ($field->accessor)($model) : $model->{$field->name};

            if (null !== $value) {
                /** @var mixed */
                $data[$field->openApiName] = $value;
            }
        }

        return $data;
    }
}
