<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Override;

use function count;

final readonly class CookieValidator extends AbstractParameterValidator
{
    public function parseCookies(string $cookieHeader): array
    {
        if ('' === trim($cookieHeader)) {
            return [];
        }

        $cookies = [];
        $pairs = explode(';', $cookieHeader);

        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (2 === count($parts)) {
                $cookies[$parts[0]] = $parts[1];
            }
        }

        return $cookies;
    }

    #[Override]
    protected function getLocation(): string
    {
        return 'cookie';
    }

    #[Override]
    protected function findParameter(array $data, string $name): mixed
    {
        return $data[$name] ?? null;
    }
}
