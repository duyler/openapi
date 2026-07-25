<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

/**
 * @internal
 */
final readonly class ValidatorDependenciesGroup
{
    public function __construct(
        public readonly RootServices $root,
        public readonly ValidatorOptions $options,
        public readonly BodyLimits $bodyLimits = new BodyLimits(),
    ) {}
}
