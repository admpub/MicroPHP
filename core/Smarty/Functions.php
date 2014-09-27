<?php
// ====================================
// Template function
// ====================================
function strarg($str) {
	$tr=array();
	$p=0;
	for ($i=1, $len=func_num_args(); $i < $len; $i++) {
		$arg=func_get_arg($i);
		if (is_array($arg)) {
			foreach ($arg as $a) $tr['%'.++$p]=$a;
		} else {
			$tr['%'.++$p]=$arg;
		}
	}
	return strtr($str, $tr);
}

/**
 * Smarty block function, provides gettext support for smarty.
 *
 * The block content is the text that should be translated.
 *
 * Any parameter that is sent to the function will be represented as %n in the translation text,
 * where n is 1 for the first parameter. The following parameters are reserved:
 *       - escape - sets escape mode:
 *           - 'html' for HTML escaping, this is the default.
 *           - 'js' for javascript escaping.
 *           - 'no'/'off'/0 - turns off escaping
 *       - plural - The plural version of the text (2nd parameter of ngettext())
 *       - count - The item count for plural mode (3rd parameter of ngettext())
 */
function smarty_block_t($params, $text, &$smarty) {
	$text=stripslashes($text);
	// set escape mode
	if (isset($params['escape'])) {
		$escape=$params['escape'];
		unset($params['escape']);
	}
	// set plural version
	if (isset($params['plural'])) {
		$plural=$params['plural'];
		unset($params['plural']);
		// set count
		if (isset($params['count'])) {
			$count=$params['count'];
			unset($params['count']);
		}
	}
	// use plural if required parameters are set
	if (isset($count) && isset($plural)) {
		$text=CoreLoader::locale(CoreLoader::$system['language']) -> ngettext($text, $plural, $count);
	} else { // use normal
		$text=CoreLoader::locale(CoreLoader::$system['language']) -> gettext($text);
	}
	// run strarg if there are parameters
	if (count($params)) {
		$text=strarg($text, $params);
	}

	if (!isset($escape) || $escape == 'html') { // html escape, default
		$text=nl2br(htmlspecialchars($text));
	} elseif (isset($escape) && ($escape == 'javascript' || $escape == 'js')) { // javascript escape
		$text=str_replace('\'', '\\\'', stripslashes($text));
	}

	return $text;
}

/**
 * Smarty SEO URL Friendly modifier plugin
 *
 * Type:     modifier
 * Name:     seo
 * Purpose:  SEO URL Friendly
 *
 * @author Willy Mularto <me at sangprabv dot web dot id>
 * @param string $string input string
 * @param string $search text to search for
 * @param string $delimiter replacement text
 * @param string $case uppercase/lowercase/ucfirst
 * @return string
 */
function smarty_modifier_seo($string, $search, $delimiter, $case) {
	//Replace any non latin chars
	$string=iconv('UTF-8', 'ASCII//TRANSLIT', $string);
	//Remove any non alpha numeric
	$string=preg_replace('/[^a-zA-Z 0-9]+/', ' ', $string);
	//Remove any double delimiter
	$string=preg_replace('/[[:blank:]]+/', "$delimiter", $string);
	//Remove any delimiter at the beginning of string if any
	$string=ltrim($string, "$delimiter");
	//Remove any delimiter at the end of string if any
	$string=rtrim($string, "$delimiter");

	switch ($case) {
		case 'upper':
			return strtoupper($string);
		case 'lower':
			return strtolower($string);
		case 'first':
			return ucfirst($string);
		default:
			return($string);
	}
}

/**
 * Prints out A- Z with a link
 */

function smarty_function_a2z($params=array(), &$smarty) {
	$params['url']=!empty($params['url'])?$params['url']:'letter.php';
	$params['name']=!empty($params['name'])?$params['name']:'L'; # $_GET index
	$params['active']=!empty($params['active'])?$params['active']:'A';

	$links=array();
	for($letter=ord('A'); $letter <= ord('Z'); ++$letter) {
		$alphabet=chr($letter);
		$active=($alphabet == $params['active'])?' class="active"':'';
		$links[]="<li{$active}><span><a href='{$params['url']}?{$params['name']}={$alphabet}'>{$alphabet}</a></span></li>";
	}
	return(implode('', $links));
}

function smarty_modifier_scheme($string) {
	if (!preg_match('#^[a-zA-Z0-9-]://#', $string)) {
		return '//'.$string; // Note that "//google.com" is a valid URL.
	}
	return $string;
}


/**
 * Function: smarty_contains
 * Purpose:  Used to find a string in a string
 * Example: contains( 'Jason was here', 'here' ) returns true
 * Example2: contains( 'Jason was here', 'ason' ) returns false
 *
 * @author Jason Strese <Jason dot Strese at gmail dot com>
 * @param string $
 * @return string
 */
function smarty_modifier_contains($string, $find, $cases=false) {
	if (empty($string)) return 0;
	$find=preg_quote($find);
	$find=addcslashes($find, '/');
	if ($cases)
		$count=preg_match_all("/\b{$find}\b/", $string, $match);
	else
		$count=preg_match_all("/\b{$find}\b/i", $string, $match);
	return $count;
}

function smarty_function_widget($params, &$template) {
	if (empty($params['name'])) {
		user_error('Widget is missing name.', E_USER_ERROR);
	}
	$widget=CoreLoader::widget($params['name'],$template->smarty);
	$method='execute';
	$widget -> _setTpl('view.tpl');
	if(!empty($params['func'])){
		$method=$params['func'];
		$widget -> _setTpl($method.'.tpl');
	}
	if($method{0}!='_' && method_exists($widget,$method)){
		$widget -> init($params) -> $method($params);
	}
}
