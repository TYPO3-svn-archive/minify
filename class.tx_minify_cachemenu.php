<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Christopher Hlubek <hlubek@networkteam.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Clear cache menu for minify
 *
 * @package	tx_minify
 * @author Christopher Hlubek <hlubek@networkteam.com>
 */
class tx_minify_cachemenu implements backend_cacheActionsHook {

	/**
	 * Adds the option to clear the minify cache to the backend cache menu
	 *
	 * @param array $a_cacheActions
	 * @param array $a_optionValues
	 * @return void
	 */
	public function manipulateCacheActions(&$a_cacheActions, &$a_optionValues) {
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$s_title = $GLOBALS['LANG']->sL('LLL:EXT:minify/locallang.xml:clearCacheMenu_minifyClearCache', TRUE);
			$s_imagePath = t3lib_extMgm::extRelPath('minify') . 'res/';
			if (strpos($s_imagePath, 'typo3conf') !== FALSE) $s_imagePath = '../' . $s_imagePath;
			$a_cacheActions[] = array(
				'id'    => 'minify',
				'title' => $s_title,
				'href' => 'ajax.php?ajaxID=tx_minify::clearCache',
				'icon'  => '<img src="' . $s_imagePath . 'be_icon.png" title="' . $s_title . '" alt="' . $s_title . '" />',
			);
			$a_optionValues[] = 'clearCacheMinify';
		}
	}

}
?>