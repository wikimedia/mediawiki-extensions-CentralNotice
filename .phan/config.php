<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// T191668 and T191666
$cfg['suppress_issue_types'][] = 'PhanParamTooMany';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/CentralAuth',
		'../../extensions/cldr',
		'../../extensions/MobileFrontend',
		'../../extensions/Translate',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/CentralAuth',
		'../../extensions/cldr',
		'../../extensions/MobileFrontend',
		'../../extensions/Translate',
	]
);

return $cfg;
