<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use ValueError;

use function is_array;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final readonly class XmlBodyParser
{
    private const int PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    /**
     * @return array<array-key, mixed>|string
     */
    public function parse(string $body): array|string
    {
        if ('' === trim($body)) {
            return '';
        }

        try {
            return LibxmlSecuredContext::run(static function () use ($body): array|string {
                $xml = simplexml_load_string($body, options: self::PARSE_OPTIONS);

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
            });
        } catch (ValueError) {
            return $body;
        }
    }
}
