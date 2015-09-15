<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
        ->exclude('data')
        ->exclude('vendor')
        ->exclude('node_modules')
        ->exclude('nbproject')
        ->exclude('htdocs/lib')
        ->in(__DIR__);

return Symfony\CS\Config\Config::create()
                ->level(Symfony\CS\FixerInterface::NONE_LEVEL)
                ->fixers([
                    // 'align_double_arrow', // Waste of time
                    // 'align_equals', // Waste of time
                    'braces',
                    // 'concat_without_spaces', // This make it less readable
                    'concat_with_spaces',
                    'double_arrow_multiline_whitespaces',
                    'duplicate_semicolon',
                    'elseif',
                    // 'empty_return', // even if technically useless, we prefer to be explicit with our intent to return null
                    'encoding',
                    'eof_ending',
                    'extra_empty_lines',
                    'function_call_space',
                    'function_declaration',
                    'include',
                    'indentation',
                    'join_function',
                    'line_after_namespace',
                    'linefeed',
                    'lowercase_constants',
                    'lowercase_keywords',
                    'method_argument_space',
                    'multiline_array_trailing_comma',
                    'multiline_spaces_before_semicolon',
                    'multiple_use',
                    'namespace_no_leading_whitespace',
                    'new_with_braces',
                    'object_operator',
                    'operators_spaces',
                    'ordered_use',
                    'parenthesis',
                    'php_closing_tag',
                    'phpdoc_indent',
                    // 'phpdoc_params', // Waste of time
                    'psr0',
                    'remove_leading_slash_use',
                    'remove_lines_between_uses',
                    'return',
                    //'short_array_syntax',
                    'short_tag',
                    'single_array_no_trailing_comma',
                    'spaces_before_semicolon',
                    'spaces_cast',
                    'standardize_not_equal',
                    // 'strict', // No, too dangerous to change that
                    // 'strict_param', // No, too dangerous to change that
                    // 'ternary_spaces', // That would be nice, but NetBeans does not cooperate :-(
                    'trailing_spaces',
                    'unused_use',
                    'visibility',
                    'whitespacy_lines',
                ])
                ->finder($finder);
