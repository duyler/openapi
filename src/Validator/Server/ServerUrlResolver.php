<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Server\Dto\ServerVariableOverride;

use function array_key_exists;
use function is_array;
use function is_string;
use function sprintf;

final readonly class ServerUrlResolver
{
    private const string VARNAME_PATTERN = '[a-zA-Z0-9_.]+';

    /**
     * Resolves a server URL template by substituting variables with provided values.
     *
     * @param Server $server
     * @param ServerVariableOverride ...$overrides
     *
     * @throws ServerVariableException If a required variable is missing
     */
    public function resolve(Server $server, ServerVariableOverride ...$overrides): string
    {
        $url = $server->url;
        $variables = $this->mergeVariables($server, $overrides);

        $result = preg_replace_callback(
            '/\{(' . self::VARNAME_PATTERN . ')\}/',
            function (array $matches) use ($variables, $server): string {
                $variableName = $matches[1];

                if (false === array_key_exists($variableName, $variables)) {
                    throw new ServerVariableException(
                        sprintf(
                            'Server variable "%s" is not defined for server URL "%s"',
                            $variableName,
                            $server->url,
                        ),
                    );
                }

                return $variables[$variableName];
            },
            $url,
        );

        if (null === $result) {
            return $url;
        }

        return $result;
    }

    /**
     * Extracts variable names from a server URL template.
     *
     * @return list<string>
     */
    public function extractVariableNames(Server $server): array
    {
        preg_match_all('/\{(' . self::VARNAME_PATTERN . ')\}/', $server->url, $matches);

        /** @var list<string> */
        return $matches[1];
    }

    /**
     * Validates that all template variables in the server URL have corresponding values.
     *
     * @param Server $server
     * @param ServerVariableOverride ...$overrides
     *
     * @throws ServerVariableException If a variable is missing a value
     */
    public function validateVariables(Server $server, ServerVariableOverride ...$overrides): void
    {
        $variableNames = $this->extractVariableNames($server);
        $variables = $this->mergeVariables($server, $overrides);

        foreach ($variableNames as $name) {
            if (false === array_key_exists($name, $variables)) {
                throw new ServerVariableException(
                    sprintf(
                        'Missing value for server variable "%s" in URL "%s"',
                        $name,
                        $server->url,
                    ),
                );
            }
        }
    }

    /**
     * @param array<array-key, ServerVariableOverride> $overrides
     *
     * @return array<string, string>
     */
    private function mergeVariables(Server $server, array $overrides): array
    {
        /** @var array<string, string> $defaults */
        $defaults = [];

        if (null !== $server->variables) {
            /**
             * @var string $name
             * @var mixed $variable
             */
            foreach ($server->variables as $name => $variable) {
                if (is_array($variable) && isset($variable['default']) && is_string($variable['default'])) {
                    $defaults[$name] = $variable['default'];
                }
            }
        }

        $overrideMap = [];
        foreach ($overrides as $override) {
            $overrideMap[$override->name] = $override->value;
        }

        return array_merge($defaults, $overrideMap);
    }
}
