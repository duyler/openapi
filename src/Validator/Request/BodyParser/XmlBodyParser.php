<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use ValueError;

final readonly class XmlBodyParser
{
    /**
     * @return array<array-key, mixed>|string
     */
    public function parse(string $body): array|string
    {
        if ('' === trim($body)) {
            return '';
        }

        try {
            $xml = simplexml_load_string($body);

            if (false === $xml) {
                return $body;
            }

            $encoded = json_encode($xml);
            if (false === $encoded) {
                return $body;
            }

            $decoded = json_decode($encoded, true);
            if (false === is_array($decoded)) {
                return $body;
            }

            return $decoded;
        } catch (ValueError) {
            return $body;
        }
    }
}
