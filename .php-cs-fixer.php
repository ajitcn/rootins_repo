<?php

use PhpCsFixer\Config;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules(
        [
            '@PSR12' => true,
            'array_indentation' => true,
            'binary_operator_spaces' => true,
            'blank_line_after_namespace' => true,
            'blank_line_after_opening_tag' => false,
            'blank_line_before_statement' => [
                'statements' => [
                    'break',
                    'case',
                    'continue',
                    'declare',
                    'do',
                    'exit',
                    'for',
                    'foreach',
                    'if',
                    'include',
                    'require',
                    'return',
                    'switch',
                    'throw',
                    'try',
                    'while',
                ],
            ],
            'braces' => [
                'allow_single_line_closure' => true,
                'position_after_anonymous_constructs' => 'same',
                'position_after_control_structures' => 'next',
                'position_after_functions_and_oop_constructs' => 'next',
            ],
            'cast_spaces' => true,
            'class_attributes_separation' => [
                'elements' => [
                    'const' => 'one',
                    'method' => 'one',
                    'property' => 'one',
                    'trait_import' => 'none',
                ],
            ],
            'concat_space' => [
                'spacing' => 'one',
            ],
            'control_structure_braces' => true,
            'control_structure_continuation_position' => [
                'position' => 'same_line',
            ],
            'function_declaration' => [
                'closure_fn_spacing' => 'one',
                'closure_function_spacing' => 'one',
                'trailing_comma_single_line' => false,
            ],
            'include' => true,
            'indentation_type' => true,
            'method_argument_space' => [
                'after_heredoc' => true,
                'attribute_placement' => 'standalone',
                'keep_multiple_spaces_after_comma' => false,
                'on_multiline' => 'ensure_fully_multiline',
            ],
            'method_chaining_indentation' => true,
            'multiline_comment_opening_closing' => true,
            'multiline_whitespace_before_semicolons' => true,
            'no_extra_blank_lines' => [
                'tokens' => [
                    'break',
                    'case',
                    'continue',
                    'curly_brace_block',
                    'default',
                    'extra',
                    'return',
                    'square_brace_block',
                    'throw',
                    'use',
                ],
            ],
            'no_multiple_statements_per_line' => true,
            'no_spaces_around_offset' => true,
            'no_whitespace_in_blank_line' => true,
            'operator_linebreak' => [
                'only_booleans' => true,
                'position' => 'beginning',
            ],
            'phpdoc_align' => [
                'align' => 'vertical',
            ],
            'phpdoc_indent' => true,
            'phpdoc_line_span' => [
                'const' => 'multi',
                'method' => 'multi',
                'property' => 'multi',
            ],
            'phpdoc_no_access' => true,
            'phpdoc_order' => true,
            'phpdoc_scalar' => true,
            'phpdoc_separation' => true,
            'phpdoc_single_line_var_spacing' => true,
            'phpdoc_summary' => true,
            'return_type_declaration' => [
                'space_before' => 'none',
            ],
            'single_line_comment_style' => [
                'comment_types' => [
                    'hash',
                ],
            ],
            'single_line_throw' => false,
            // Mixed PHP/HTML templates are indented by their surrounding markup;
            // PHP-CS-Fixer's statement indentation rule would otherwise oscillate.
            'statement_indentation' => false,
            'trailing_comma_in_multiline' => [
                'after_heredoc' => true,
                'elements' => [
                    'arguments',
                    'arrays',
                    'parameters',
                ],
            ],
            'whitespace_after_comma_in_array' => true,
        ]
    )
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(
                [
                    __DIR__ . '/app',
                    __DIR__ . '/tests',
                    __DIR__ . '/modules',
                ]
            )
            ->exclude(
                [
                    'vendor',
                    'cache',
                    'build',
                    'generated',
                    'uploads',
                    'third-party',
                ]
            )
            ->notName('*.min.php')
            // These mixed templates are kept manually formatted because the fixer
            // cannot reconcile PHP indentation with their surrounding HTML.
            ->notPath('#(consultation_fee|documents)\.php$#')
            ->files()
    );
