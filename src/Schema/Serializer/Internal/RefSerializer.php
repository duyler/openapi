<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

/** @internal */
final readonly class RefSerializer
{
    /** @return array{'$ref': string, 'summary'?: string, 'description'?: string}|null */
    public function toWireForm(?string $ref, ?string $summary, ?string $description): ?array
    {
        if (null === $ref) {
            return null;
        }

        $data = ['$ref' => $ref];

        if (null !== $summary) {
            $data['summary'] = $summary;
        }

        if (null !== $description) {
            $data['description'] = $description;
        }

        return $data;
    }
}
