<?php

return [
	'target_php_version'              => '7.3',
	'backward_compatibility_checks'   => false,
	'analyze_signature_compatibility' => true,
	'minimum_severity'                => 0,
	'directory_list'                  => [
		'src',
		'.phan/stubs',
	],
	'suppress_issue_types'            => [
		'PhanPluginDuplicateConditionalNullCoalescing',
	],
	'plugins'                         => [
		'AlwaysReturnPlugin',
		'DuplicateArrayKeyPlugin',
		'PregRegexCheckerPlugin',
		'UnreachableCodePlugin',
		'NonBoolBranchPlugin',
		'NonBoolInLogicalArithPlugin',
		'InvalidVariableIssetPlugin',
		'NoAssertPlugin',
		'DuplicateExpressionPlugin',
		'DollarDollarPlugin',
	],
];
