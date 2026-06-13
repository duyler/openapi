<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Helper;

use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\OpenApiValidator;
use ReflectionProperty;

trait ValidatorDependenciesAccessTrait
{
    private static function readProperty(object $object, string $class, string $property): object
    {
        $prop = new ReflectionProperty($class, $property);

        return $prop->getValue($object);
    }

    private static function readDependenciesProperty(OpenApiValidator $validator, string $property): object
    {
        $dependencies = self::readProperty($validator, OpenApiValidator::class, 'dependencies');

        return self::readProperty($dependencies, ValidatorDependencies::class, $property);
    }
}
