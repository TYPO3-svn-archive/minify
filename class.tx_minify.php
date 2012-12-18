<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008-2011 Christopher Hlubek <hlubek@networkteam.com>
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
 * Main minify class doing the minification.
 *
 * Uses some code from the mc_css_js_compressor extension.
 *
 * @package	tx_minify
 * @author Christopher Hlubek <hlubek@networkteam.com>
 */
class tx_minify {

	/**
	 * TypoScript configuration set by USER cObject
	 * @var array
	 */
	public $conf;

	/**
	 * Save cached files in this directory
	 * @var string
	 */
	protected $cachePath = 'typo3temp/minify/';

	/**
	 * Wether to merge the CSS and JS files
	 * @var boolean
	 */
	protected $merge = TRUE;

	/**
	 * Array of patterns of skipped files
	 * @var array
	 */
	protected $skipFilesPatterns;

	/**
	 * Debug / profiling mode enabled
	 * @var boolean
	 */
	protected $debug = FALSE;

	/**
	 * Lock object to guard against race conditions
	 * @var t3lib_lock
	 */
	protected $lockObj;

	/**
	 * This method is invoked by the content post processing hook and does the actual minification.
	 *
	 * @return void
	 */
	public function minify() {
		if (!class_exists('JSMin')) {
			require_once(t3lib_extMgm::extPath('minify', 'lib/JSMin.php'));
		}
		require_once(t3lib_extMgm::extPath('minify', 'lib/Minify/Javascript.php'));
		require_once(t3lib_extMgm::extPath('minify', 'lib/Minify/CSS.php'));

		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_minify.'];

		if ((int)$this->conf['debug'] == 1) {
			$this->debug = TRUE;
		}

		$skipFiles = t3lib_div::trimExplode(',', $this->conf['skipFiles'], TRUE);
		$this->skipFilesPatterns = array();
		foreach ($skipFiles as $skipFile) {
			$pattern = '/^' . str_replace('\*', '.*', preg_quote($skipFile, '/')) . '$/';
			$this->skipFilesPatterns[] = $pattern;
		}

		if ($this->conf['enable']) {
			if ($this->debug) $GLOBALS['TT']->push('minify', 'Minify CSS and JS');

			$tempContent = $GLOBALS['TSFE']->content;
			if (!$this->conf['noParseHeader']) {
				$headStart = stripos($tempContent, '<head');
				if ($headStart !== FALSE) {
					$headStart = strpos($tempContent, '>', $headStart) + 1;
				}
				$headEnd = stripos($tempContent, '</head>');
				if ($headStart !== FALSE && $headEnd !== FALSE) {
					$headContent = substr($tempContent, $headStart, $headEnd - $headStart);
					$tempContent = substr($tempContent, 0, $headStart) . $this->processHtmlPart($headContent) . substr($tempContent, $headEnd);
				}
			}
			if (!$this->conf['noParseBody']) {
				$bodyStart = stripos($tempContent, '<body');
				if ($bodyStart !== FALSE) {
					$bodyStart = strpos($tempContent, '>', $bodyStart) + 1;
				}
				$bodyEnd = stripos($tempContent, '</body>');
				if ($bodyStart !== FALSE && $bodyEnd !== FALSE) {
					// TODO Implement processing of body slice (e.g. only last 1000 bytes) to prevent pcre limits on large responses
					$bodyContent = substr($tempContent, $bodyStart, $bodyEnd - $bodyStart);
					$tempContent = substr($tempContent, 0, $bodyStart) . $this->processHtmlPart($bodyContent) . substr($tempContent, $bodyEnd);
				}
			}
			$GLOBALS['TSFE']->content = $tempContent;

			if ($this->debug) $GLOBALS['TT']->pull();
		}
	}

	/**
	 * @return boolean TRUE if the given filename should be skipped
	 */
	protected function shouldSkipFile($filename) {
		foreach ($this->skipFilesPatterns as $pattern) {
			if (preg_match($pattern, $filename)) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Process an HTML part (body or head)
	 *
	 * Replaces references to CSS and JS with minified and merged files.
	 *
	 * @param string $html The HTML content
	 * @return string
	 */
	public function processHtmlPart($html) {
		$html = $this->replaceLinkTagsForCSS($html);
		$html = $this->replaceScriptTagsForJavascript($html);
		return $html;
	}

	/**
	 * Finds and replaces link tags in the given HTML content
	 *
	 * Conditional comments will be merged and minified per conditional comment block and appended after the last
	 * CSS link.
	 *
	 * @param string $content HTML content
	 * @return string The HTML content with replaced link tags
	 */
	protected function replaceLinkTagsForCSS($content) {
		if (!$this->conf['processCssLinks']) {
			return $content;
		}

		$matches = array();
		$css = array();
		$conditionalTags = '';
		$tags = array();

			// Match link tags and link tags inside a conditional comment
		if (preg_match_all('/(<!--\[if[^>]+\]>)?(\s*<link [^>]+\s*\/?>\s*)+(<!\[endif\]-->)?\s*/i', $content, $matches)) {
			for ($i = 0; $i < count($matches[0]); $i++) {
					// Merge conditional comment content separately
				if ($matches[1][$i]) {
					$content = str_replace($matches[0][$i], '', $content);
					$conditionalTags .= $matches[1][$i] . $this->replaceLinkTagsForCSS($matches[2][$i]) . $matches[3][$i] . chr(10);
					continue;
				}

					// Process individual link tags
				if (preg_match_all('/\s*<link [^>]+\s*\/?>\s*/i', $matches[0][$i], $tagMatches)) {
					for ($j = 0; $j < count($tagMatches[0]); $j++) {
						$tag = $tagMatches[0][$j];
						if (preg_match('/href="([^"]+?)(\?[0-9]+)?"/i', $tag, $tagHrefMatches)) {
							$href = $tagHrefMatches[1];
							if ($this->shouldSkipFile($href)) {
								continue;
							}
						}
						if (strpos(strtolower($tag), 'rel="stylesheet"') !== FALSE) {
							$tags[] = $tag;
						}
					}
				}
			}
			for ($i = 0; $i < count($tags); $i++) {
				$tag = $tags[$i];
				$replacement = $this->createCSS($tag);
				$tagSubstitution = '';
				if ($this->merge && isset($replacement['new'])) {
					$css[$replacement['media']][] = $replacement['new'];
				} else {
					$tagSubstitution = $replacement['html'];
				}
					// Last tag occurence will be replaced by the merged CSS plus saved conditional tags
				if ($i == count($tags) - 1) {
					$tagSubstitution .= chr(10) . $this->mergeCSS($css) . $conditionalTags;
				}
				$content = str_replace($tag, $tagSubstitution, $content);
			}
 		} elseif (preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
			throw new Exception('Regular expression backtrack limit exceeded', 1326207971);
		}

		return $content;
 	}

	/**
	 * Finds and replaces script tags in the given HTML content
	 *
	 * @param string $content HTML content
	 * @return string The HTML content with replaced script tags
	 */
	protected function replaceScriptTagsForJavascript($content) {
		if (!$this->conf['processScriptTags']) {
			return $content;
		}

		$matches = array();
		$javascript = array();

		if (preg_match_all('/<script ([^>]+["\s])>\s*<\/script>\s*(\r\n|\n)?/i', $content, $matches)) {
			$tags = array();
			for ($i = 0; $i < count($matches[0]); $i++) {
				$tag = $matches[0][$i];
				 if(preg_match('/src="([^"]+?)(\?[0-9]+)?"/i', $tag, $tagSrcMatches)) {
					$src = $tagSrcMatches[1];
					if ($this->shouldSkipFile($src)) {
						continue;
					}
				}
				$tags[] = $tag;
			}
			for ($i = 0; $i < count($tags); $i++) {
				$tag = $tags[$i];

				$replacement  = $this->createJavascript($tag);

				$tagSubstitution = '';
				if ($this->merge && isset($replacement['new'])) {
					$javascript[] = $replacement['new'];
				} else {
					$tagSubstitution = $replacement['html'];
				}
				if ($i == count($tags) - 1) {
					$tagSubstitution .= $this->mergeJavascript($javascript);
				}
				$content = str_replace($tag, $tagSubstitution, $content);
			}
		} elseif (preg_last_error() === PREG_BACKTRACK_LIMIT_ERROR) {
			throw new Exception('Regular expression backtrack limit exceeded', 1326207978);
		}

		return $content;
	}

	/**
	 * Merge all CSS files from css array. If a cached file exists for the content, it will be used.
	 *
	 * @param array $css
	 * @return string Tag for merged files or empty string
	 */
	protected function mergeCSS($css) {
		$content = '';
		if (count($css)) {
			foreach ($css as $mediaKey => $mediaFiles) {
				// Take media key (e.g. print) and files into account
				$md5 = md5($mediaKey . ':' . implode(',', $mediaFiles));
				$dest = $this->cachePath . substr(md5($md5), 0, 10) . '_minify__merged.css';

				if (!is_file(PATH_site . $dest)) {
					$this->lockTemp();
					if (!is_file(PATH_site . $dest)) {
						$buffer = '';
						foreach ($mediaFiles as $css) {
							$buffer .= file_get_contents($this->cachePath . $css) . chr(10);
						}
						t3lib_div::writeFile(PATH_site . $dest, $buffer);
					}
					$this->unlockTemp();
				}

				// HOOK mergeCSS-postwrite: After writing CSS file
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/minify/class.tx_minify.php']['mergeCSS-postwrite']))	{
					$_params = array(
						'pObj' => &$this,
						'dest' => &$dest
					);
					foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/minify/class.tx_minify.php']['mergeCSS-postwrite'] as $_funcRef)	{
						t3lib_div::callUserFunction($_funcRef, $_params, $this);
					}
				}

				if ($this->debug) {
					$content .= '<!--' . chr(10);
					$content .= 'MINIFY merged CSS for media ' . $mediaKey . ':' . chr(10);
					foreach ($mediaFiles as $css) {
						$content .= $css . chr(10);
					}
					$content .= '-->' . chr(10);
				}
				$content .= '<link rel="stylesheet" type="text/css" href="' . $dest . '" media="' . $mediaKey . '" />' . chr(10);
			}
		}
		return $content;
	}

	/**
	 * Merge Javascript files from javascript array. If a cached file exists for the content, it will be used.
	 *
	 * @param array $javascript
	 * @return string Tag for merged files or empty string
	 */
	protected function mergeJavascript($javascript) {
		if(count($javascript)) {
			// Cache tagging is already done in the separate files
			$md5 = md5(implode(',', $javascript));
			$dest = $this->cachePath . substr(md5($md5), 0, 10) . '_minify__merged.js';

			if (!is_file(PATH_site . $dest)) {
				$this->lockTemp();
				if (!is_file(PATH_site . $dest)) {
					$buffer = '';
					foreach ($javascript as $js) {
						$buffer .= '// ---- ' . $js . chr(10);
						$buffer .= file_get_contents($this->cachePath . $js) . ';' . chr(10);
					}

					t3lib_div::writeFile(PATH_site . $dest, $buffer);
				}
				$this->unlockTemp();
			}

			// HOOK mergeJavascript-postwrite: After writing JavaScript file
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/minify/class.tx_minify.php']['mergeJavascript-postwrite']))	{
				$_params = array(
					'pObj' => &$this,
					'dest' => &$dest
				);
				foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/minify/class.tx_minify.php']['mergeJavascript-postwrite'] as $_funcRef)	{
					t3lib_div::callUserFunction($_funcRef, $_params, $this);
				}
			}

			$content = '<script type="text/javascript" src="' . $dest . '"></script>' . chr(10);
			if ($this->debug) {
				$content .= '<!--' . chr(10);
				$content .= 'MINIFY merged JS:' . chr(10);
				foreach ($javascript as $js) {
					$content .= $js . chr(10);
				}
				$content .= '-->' . chr(10);
			}
			return $content;
		} else {
			return $out;
		}
	}

	/**
	 * Compute a file tag by filename
	 *
	 * The value should be a light-weight signature of a file to
	 * detect changes without loading the whole file.
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function getFileTag($filename) {
		$stat = stat($filename);
		return $filename . '|' . $stat['mtime'] . '|' . $stat['size'];
	}

	/**
	 * Create a CSS file
	 *
	 * @param string $linkTag
	 * @return array
	 */
	protected function createCSS($linkTag) {
		if (preg_match('/href="([^"]+)"/', $linkTag, $matches)) {
			$out['file'] = $matches[1];
			$out['file'] = preg_replace('/\?[0-9]+/', '', $out['file']);
		}
		if (preg_match('/media="([^"]+)"/', $linkTag, $matches)) {
			$out['media'] = $matches[1];
		} else {
			$out['media'] = 'all';
		}

			// Strip base URL from file for absolute URLs
 		if (strpos($out['file'], strlen($GLOBALS['TSFE']->baseUrl)) === 0) {
			$out['file'] = substr($out['file'], strlen($GLOBALS['TSFE']->baseUrl));
 		}

		$out['html'] = $linkTag;

			// If this file is on skipFile array, return the original string
		if ($this->shouldSkipFile($out['file'])) {
			return $out;
		}

			// Remove leading slash
		if (substr($out['file'],0,1) == '/') {
			$out['file'] = substr($out['file'], 1, strlen($out['file']));
		}

			// Check if the file exists by file tags (name, mtime, size)
		if (is_file($out['file'])) {
			$out['md5'] = md5($this->getFileTag($out['file']));
			$out['new'] = substr($out['md5'], 0, 10) . '_minify_' . basename($out['file']);

				//Check if this file was already compressed
			if (!is_file($this->cachePath . $out['new'])) {
					// Compress the CSS, and save to the new file
				if (!$this->minifyCSSFile($out['file'], $this->cachePath . $out['new'])) {
						//If fails for any reason, return the original file
					$out['html'] = $linkTag;
				}
			}
			$newFile = $this->cachePath . $out['new'];

			$out['html'] = str_replace($out['file'], ($this->conf['dontAddLeadingSlash'] ? '' : '/') . $newFile, $linkTag);

			return $out;
		} else {
			return array('html' => $linkTag);
		}
	}

	/**
	 * Create a JavaScript file
	 *
	 * @param string $scriptTag
	 * @return array
	 */
	protected function createJavascript($scriptTag) {
		if (preg_match('/src="([^"]+)"/', $scriptTag, $matches)) {
			$out['file'] = $matches[1];
			$out['file'] = preg_replace('/\?[0-9]+/', '', $out['file']);
		}

			// Strip base URL from file for absolute URLs
 		if (strpos($out['file'], strlen($GLOBALS['TSFE']->baseUrl)) === 0) {
			$out['file'] = substr($out['file'], strlen($GLOBALS['TSFE']->baseUrl));
 		}

		$out['html'] = $scriptTag;

			//If this file is on skipFile array, return the original string
		if ($this->shouldSkipFile($out['file'])) {
			return $out;
		}

			//Remove leading slash
		if (substr($out['file'], 0, 1) == '/') {
			$out['file'] = substr($out['file'], 1, strlen($out['file']));
		}

			//Check if the file exists
		if (is_file($out['file'])) {
			$out['md5'] = md5($this->getFileTag($out['file']));
			$out['new'] = substr($out['md5'], 0, 10) . '_minify_' . basename($out['file']);

				//Check if this file was already compressed
			if (!is_file($this->cachePath . $out['new'])) {
					//Compress the JS, and save to the new file
				if (!$this->minifyJavascriptFile($out['file'], $this->cachePath . $out['new'])) {
						//If fails for any reason, return the original file
					$out['html'] = $scriptTag;
				}
			}

			$out['html'] = str_replace($out['file'], ($this->conf['dontAddLeadingSlash'] ? '' : '/') . $this->cachePath . $out['new'], $scriptTag);

			return $out;
		} else {
			return array('html' => $scriptTag);
		}
	}

	/**
	 * Find all files with _minify_ and given filename in temp filename
	 * in the minify temp path and delete them.
	 *
	 * @param string $filename The filename of the original file or "*" for cleaning all minified files
	 * @return void
	 */
	protected function cleanOldTemps($filename) {
		$this->lockTemp();
		$files = glob(PATH_site . $this->cachePath . '*_minify_' . $filename);
		if (is_array($files)) {
			foreach ($files as $filename) {
				if (is_file($filename)) {
					unlink($filename);
				}
			}
		}
		$this->unlockTemp();
	}

	/**
	 * Minifies a CSS file and writes it to some new file
	 *
	 * @param string $source: The source CSS file
	 * @param string $destination: The destination file
	 * @return boolean TRUE if successful
	 */
	protected function minifyCSSFile($source, $destination) {
		if (is_file(PATH_site . $destination)) {
			return TRUE;
		}
		$this->lockTemp();
		$buffer = file_get_contents($source);
		$sourceDirectory = dirname($source);
		$buffer = Minify_CSS::minify($buffer, array('currentDir' => PATH_site . $sourceDirectory));
		$result = t3lib_div::writeFile(PATH_site . $destination, $buffer);
		$this->unlockTemp();
		return $result;
	}

	/**
	 * Minifies a Javascript file and writes it to some new file
	 *
	 * @param string $source: The source JS file
	 * @param string $destination: The destination file
	 * @return boolean TRUE if successful
	 */
	protected function minifyJavascriptFile($source, $destination) {
		if (is_file(PATH_site . $destination)) {
			return TRUE;
		}
		$this->lockTemp();
		$buffer = file_get_contents($source);
		$sourceDirectory = dirname($source);
		$buffer = Minify_Javascript::minify($buffer, array('currentDir' => PATH_site . $sourceDirectory));
		$result = t3lib_div::writeFile(PATH_site . $destination, $buffer);
		$this->unlockTemp();
		return $result;
	}

	/**
	 * Clear cache post processor for removing minified
	 * files on "clear all caches". This is useful to remove
	 * unnecessary files and force a rebuild e.g. for new timestamps
	 * on assets.
	 *
	 * @param object $params parameter array
	 * @param object $pObj parent object
	 * @return void
	 */
	public function clearCachePostProc(&$params, &$pObj) {
		if ($params['cacheCmd'] === 'all') {
			$this->cleanOldTemps('*');
		}
	}

	/**
	 * AJAX backend entry point to clear the minify cache
	 *
	 * It also deletes the page cache to force a rebuild of minifed pages.
	 *
	 * @return void
	 */
	public function ajaxClearCache() {
		$tceMain = t3lib_div::makeInstance('t3lib_TCEmain');
		$tceMain->start(array(), array());
		$tceMain->clear_cacheCmd('pages');

		$this->cleanOldTemps('*');
	}

	/**
	 * Lock the temp directory for writing
	 *
	 * @return void
	 */
	protected function lockTemp() {
		if (!isset($this->conf['useLocking']) || $this->conf['useLocking'] !== '1') return TRUE;

		if ($this->lockObj === NULL) {
			$this->lockObj = t3lib_div::makeInstance('t3lib_lock', 'tx_minify', $GLOBALS['TYPO3_CONF_VARS']['SYS']['lockingMode']);
		}
		try {
			$success = $this->lockObj->acquire();
		} catch(Exception $e) {
			$success = FALSE;
		}
		return $success;
	}

	/**
	 * Unlock the temp directory after writing
	 *
	 * @return void
	 */
	protected function unlockTemp() {
		if (!isset($this->conf['useLocking']) || $this->conf['useLocking'] !== '1') return TRUE;

		if ($this->lockObj === NULL || $this->lockObj->getLockStatus() === FALSE) return TRUE;
		try {
			$success = $this->lockObj->release();
		} catch(Exception $e) {
			$success = FALSE;
		}
		return $success;
	}
}
?>