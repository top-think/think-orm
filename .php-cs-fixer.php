<?php

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('vendor')
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/stubs',
        __DIR__ . '/tests',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$fixers = [
    '@PSR12'                                     => true,
    'assign_null_coalescing_to_coalesce_equal'   => true, // 尽可能的使用空合并运算法
    'clean_namespace'                            => true, // 命名空间中不得包含空格，注释
    'no_unset_cast'                              => true, // 如需取消变量，必须使用 (null) 而非 (unset)强制类型转换
    'single_quote'                               => true, // 简单字符串应该使用单引号代替双引号；
    'method_argument_space'                      => ['after_heredoc' => true], // 方法调用的参数每个逗号后必须有一个空格
    'no_whitespace_before_comma_in_array'        => ['after_heredoc' => true], // 在数组中，每个逗号之前不得有空格。
    'list_syntax'                                => true, // 数组直接解构，不需要使用list语法
    'ternary_to_null_coalescing'                 => true, // 尽可能使用 null 合并运算法而非三元运算符
    'no_unused_imports'                          => true, // 删除没用到的use
    'no_singleline_whitespace_before_semicolons' => true, // 删除在结束分号之前的单行空格；
    'no_empty_statement'                         => true, // 多余的分号
    'no_extra_blank_lines'                       => true, // 多余空白行
    'no_blank_lines_after_phpdoc'                => true, // 注释和代码中间不能有空行
    'no_empty_phpdoc'                            => true, // 禁止空注释
    'phpdoc_align'                               => true, // 注释中标签垂直对齐
    'phpdoc_indent'                              => true, // 注释和代码的缩进相同
    'no_leading_namespace_whitespace'            => true, // 命名空间前面不应该有空格；
    'indentation_type'                           => true, // 代码必须使用配置的缩进类型
    'cast_spaces'                                => true, // 强制转换和变量之间应该有一个空格
    'align_multiline_comment'                    => true, // PSR-5,多行注释对齐
    'concat_space'                               => [
        'spacing' => 'one',
    ], // 连接符号两边必须有一个空格
    'binary_operator_spaces'                     => [
        'operators' => [
            '=>' => 'align_single_space_minimal_by_scope',
            '='  => 'align_single_space_minimal',
        ]
    ], // 等号对齐、数字箭头符号对齐
    'whitespace_after_comma_in_array'            => true, // 在数组声明中，每个逗号后面必须有一个空格。
    'array_syntax'                               => true, // 数组使用简洁语法
    'normalize_index_brace'                      => true, // 数组索引必须是方括号
    'function_declaration'                       => true, // 规定函数中括号的格式
    'class_attributes_separation'                => [ // class, trait 以及属性之间必须有一个或没有空行分割
        'elements' => [
            'const'        => 'none',
            'method'       => 'one',
            'property'     => 'one',
            'trait_import' => 'none',
            'case'         => 'none',
        ],
    ],
];
$config = new \PhpCsFixer\Config();

return $config
    ->setRules($fixers)
    ->setFinder($finder)
    ->setUsingCache(false);
