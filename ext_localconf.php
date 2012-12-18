<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE === 'FE') {
		// Content post processing hook for minification
	$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all'][] =
		'EXT:minify/class.tx_minify.php:&tx_minify->minify';
}

if (TYPO3_MODE === 'BE') {
	$extConf = unserialize($TYPO3_CONF_VARS['EXT']['extConf']['minify']);
	if (!isset($extConf['enableClearAllCacheHook']) || (boolean)$extConf['enableClearAllCacheHook']) {
			// Remove minified files if all caches are cleared
		$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][] =
			'EXT:minify/class.tx_minify.php:&tx_minify->clearCachePostProc';
	} else {
		$TYPO3_CONF_VARS['SC_OPTIONS']['additionalBackendItems']['cacheActions'][] = 'EXT:minify/class.tx_minify_cachemenu.php:&tx_minify_cachemenu';
		$TYPO3_CONF_VARS['BE']['AJAX']['tx_minify::clearCache'] = 'EXT:minify/class.tx_minify.php:tx_minify->ajaxClearCache';
	}
}

?>