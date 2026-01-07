<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'type_declaration_spaces' => true,
        'array_syntax' => ['syntax' => 'short'],
        'global_namespace_import' => true,
    ])
    ->setFinder($finder);
