<?php

########################################################################
# Extension Manager/Repository config file for ext "minify".
#
# Auto generated 08-03-2012 11:47
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Minify',
	'description' => 'Minify and merge JS and CSS files',
	'category' => 'fe',
	'shy' => 0,
	'version' => '1.6.1',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => 'typo3temp/minify',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Christopher Hlubek',
	'author_email' => 'hlubek@networkteam.com',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:22:{s:9:"ChangeLog";s:4:"3882";s:10:"README.txt";s:4:"c148";s:19:"class.tx_minify.php";s:4:"a190";s:29:"class.tx_minify_cachemenu.php";s:4:"a41a";s:21:"ext_conf_template.txt";s:4:"93ab";s:12:"ext_icon.gif";s:4:"5f5a";s:17:"ext_localconf.php";s:4:"93ea";s:14:"ext_tables.php";s:4:"8d36";s:13:"locallang.xml";s:4:"b01d";s:13:"lib/JSMin.php";s:4:"e9fe";s:18:"lib/Minify/CSS.php";s:4:"a25d";s:31:"lib/Minify/CommentPreserver.php";s:4:"86ba";s:25:"lib/Minify/Javascript.php";s:4:"b751";s:29:"lib/Minify/CSS/Compressor.php";s:4:"d691";s:30:"lib/Minify/CSS/UriRewriter.php";s:4:"0920";s:28:"nbproject/project.properties";s:4:"4917";s:21:"nbproject/project.xml";s:4:"fb33";s:35:"nbproject/private/config.properties";s:4:"d41d";s:36:"nbproject/private/private.properties";s:4:"eb3e";s:29:"nbproject/private/private.xml";s:4:"a4f4";s:15:"res/be_icon.png";s:4:"16c9";s:16:"static/setup.txt";s:4:"ca4c";}',
	'suggests' => array(
	),
);

?>