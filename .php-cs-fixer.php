<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->ignoreDotFiles(false)
    ->ignoreVCSIgnored(true)
    ->in(__DIR__)
;

$config = new Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP83Migration'        => true,
        '@PHP80Migration:risky'  => true,
        'heredoc_indentation'    => false,
        '@PhpCsFixer'            => true,
        '@PhpCsFixer:risky'      => true,
        'strict_comparison'      => false,
        'binary_operator_spaces' => [
            'default'   => 'align',
            'operators' => [
                '='  => 'align',
                '=>' => 'align',
            ],
        ],
    ])
    ->setFinder($finder)
;

return $config;
