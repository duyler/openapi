<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\CodingStyle\Rector\Closure\ClosureDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php84\Rector\Class_\DeprecatedAnnotationToDeprecatedAttributeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php84: true)
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withSkip([
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        ClosureDelegatingCallToFirstClassCallableRector::class,
        ClosureToArrowFunctionRector::class,
        // PHPDoc-only @deprecated skips — the named-constructors below delegate to the
        // deprecated constructors, so converting the PHPDoc to #[Deprecated] would
        // trigger E_DEPRECATED on every internal call. ADRs:
        //  - adr-schema-constructor.md (Schema 11b)
        //  - adr-validator-signatures-and-dtos.md (ValidatorDependencies, ValidationContext 18)
        DeprecatedAnnotationToDeprecatedAttributeRector::class => [
            __DIR__ . '/src/Schema/Model/Schema.php',
            __DIR__ . '/src/Validator/Validation/ValidatorDependencies.php',
            __DIR__ . '/src/Validator/Error/ValidationContext.php',
        ],
    ]);
