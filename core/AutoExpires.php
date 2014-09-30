<?php
/**
 * This class can negotiate the use of cache by user-agent.
 * It uses "Expires" in response header and
 * reads "If-Modified-Since" in request header
 * to determine wheather the application could send
 * the 304 HTTP code (Not Modified).
 *
 * Benefits:
 * - decrease traffic of data.
 * - take the page load faster.
 * - does not affect the application behavior.
 * - easy of use.
 *
 * @version 1.0 2012-07-19
 * @author Rubens Takiguti Ribeiro <rubs33@gmail.com>
 * @license LGPL 3 <http://www.gnu.org/licenses/lgpl.html>
 * @copyright Copyright 2012 Rubens Takiguti Ribeiro
 *
 * @modified by Wenhui Shen <swh@admpub.com> <http://www.admpub.com>
 * @usage: factory::loadClass('AutoExpires','third-party');
 * AutoExpires::setCacheTime(86400);
 * AutoExpires::start();
 */
final class AutoExpires {
	// / Attributes
	/**
	 * Cache time in seconds (default: no cache)
	 *
	 * @var int
	 */
	private static $cacheTime = 0;
	private static $hash = null;
	public static $useEtag = true; //是否使用Etag。否则使用过期时间作为缓存是否过期的标准。
	public static $debug = false;
	/**
	 * Type of cache: "public" or "private"
	 *
	 * @var string
	 */
	private static $cacheType = 'private';

	/**
	 * List of strategies to determine if the content is still valid.
	 * Posible strategies are:
	 * - 'if-modified': check if Hash of content was modified.
	 * - 'if-expired': check if the time of cache was expired.
	 *
	 * @var array [string]
	 */
	private static $strategies = array('if-modified', 'if-expired');

	/**
	 * List of user-defined strategies to determine if the content is still valid.
	 * It is used to avoid coupling of classes.
	 * Each callback must have the signature:
	 * bool callback(void)
	 * It should return true if content is still valid, or false if it is not.
	 *
	 * @var array [callback]
	 */
	private static $callbacksStrategies = array();

	/**
	 * Internal flag to control wheather the class was already started.
	 *
	 * @var bool
	 */
	private static $started = false;
	// / Magic methods
	/**
	 * Private constructor (use public static methods only)
	 */
	private function __construct() {
		// void
	}
	// / Public methods
	/**
	 * Define time limit for cache (in seconds).
	 *
	 * @param int $time Time in seconds
	 * @return void
	 */
	public static function setCacheTime($time) {
		if (self::$started) {
			trigger_error('AutoExpires was already started', E_USER_NOTICE);
			return;
		}
		self::$cacheTime = (int)$time;
	}

	/**
	 * Returns the time limit for cache.
	 *
	 * @return int
	 */
	public static function getCacheTime() {
		return self::$cacheTime;
	}

	/**
	 * Define cache type ('public' or 'private').
	 *
	 * @param string $type One of 'public' or 'private'
	 * @return void
	 */
	public static function setCacheType($type) {
		if (self::$started) {
			trigger_error('AutoExpires was already started', E_USER_NOTICE);
			return;
		}
		switch ($type) {
			case 'public':
			case 'private':
				self::$cacheType = $type;
				break;
		}
	}

	/**
	 * Returns the type of cache.
	 *
	 * @return string
	 */
	public static function getCacheType() {
		return self::$cacheType;
	}

	/**
	 * Specify a list of strategies to determine if the content is still valid.
	 *
	 * @param array $ [string] $strategis List of strategies
	 * @return void
	 */
	public static function setStrategies($strategies) {
		self::clearStrategies();
		$strategies=(array)$strategies;
		foreach ($strategies as $strategy) {
			self::addStrategy($strategy);
		}
	}

	/**
	 * Get the list of strategies to determine if the content is still valid.
	 *
	 * @return array [string]
	 */
	public static function getStrategies() {
		return self::$strategies;
	}

	/**
	 * Clear the list of strategies
	 *
	 * @return void
	 */
	public static function clearStrategies() {
		self::$strategies = array();
	}

	/**
	 * Add a strategy to the list of strategies to determine if the content is still valid.
	 *
	 * @param string $strategy The strategy to add.
	 * @return void
	 */
	public static function addStrategy($strategy) {
		if (self::$started) {
			trigger_error('AutoExpires was already started', E_USER_NOTICE);
			return;
		}
		switch ($strategy) {
			case 'if-modified':
			case 'if-expired':
				self::$strategies[] = $strategy;
				break;
		}
	}

	/**
	 * Check if the strategy is used.
	 *
	 * @param string $strategy Strategy to check.
	 * @return bool
	 */
	public static function usesStrategy($strategy) {
		return in_array($strategy, self::getStrategies());
	}

	/**
	 * Define a list of callback to use as strategy to determine if the content is still valid.
	 *
	 * @see #callbacksStrategies
	 * @param callback $callback Callback with signature "bool callback(void)"
	 * @return void
	 */
	public static function setCallbacksStrategies($callbacks) {
		self::clearCallbacksStrategies();
		$callbacks=(array)$callbacks;
		foreach ($callbacks as $callback) {
			self::addCallbackStrategy($callback);
		}
	}

	/**
	 * Return the list of callback to use as strategy to determine if the content is still valid.
	 *
	 * @see #callbacksStrategies
	 * @return array [callback]
	 */
	public static function getCallbacksStrategies() {
		return self::$callbacksStrategies;
	}

	/**
	 * Clear the list of callback.
	 *
	 * @see #callbacksStrategies
	 * @return void
	 */
	public static function clearCallbacksStrategies() {
		self::$callbacksStrategies = array();
	}

	/**
	 * Add a callback that implements a strategy to determine if the content is still valid.
	 *
	 * @see #callbacksStrategies
	 * @param callback $callback Callback with signature "bool callback(void)"
	 * @return void
	 */
	public static function addCallbackStrategy($callback) {
		if (self::$started) {
			trigger_error('AutoExpires was already started', E_USER_NOTICE);
			return;
		}
		self::$callbacksStrategies[] = $callback;
	}

	/**
	 * Starts the class. It should be called before printing anything.
	 *
	 * @return void
	 */
	public static function start($useEtag=true) {
		if (self::$started) {
			trigger_error('AutoExpires was already started', E_USER_NOTICE);
			return;
		}
		if (headers_sent($file, $line)) {
			$msg = sprintf('Headers already sent in %s on line %d', $file, $line);
			trigger_error($msg, E_USER_NOTICE);
		}

		self::$started = true;
		if(is_int($useEtag)){
			self::$useEtag = false;
			self::setCacheTime($useEtag);
		}else{
			self::$useEtag = $useEtag;
		}

		ob_start(array(__CLASS__, 'finish'));
	}

	public static function getHeaders() {
		$headers = array();
		if (function_exists('getallheaders')) {
			$headers = getallheaders();
		} elseif (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
		} elseif (function_exists('http_get_request_headers')) {
			$headers = http_get_request_headers();
		}
		foreach ($headers as $key => $value) {
			$key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
			if (!isset($_SERVER[$key])) $_SERVER[$key] = $value;
		}
		return $headers;
	}
	/**
	 * Try to get If-Modified-Since directive from user-agent.
	 *
	 * @return int | bool
	 */
	public static function getUserAgentCacheTime() {
		$date = false;
		self::getHeaders();
		// Check in $_SERVER
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			$date = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
		}
		// Not found
		if (!$date) {
			return false;
		}


		//*
		$d = explode(';', $date, 2);
		$gmtimestamp = strtotime(trim(reset($d)));
		if ($gmtimestamp) return $gmtimestamp;
		//*/


		// Format as timestamp
		$d = strptime($date, '%a, %d %b %Y %T');
		if (!$d) return false;
		$gmtimestamp = gmmktime($d['tm_hour'], $d['tm_min'], $d['tm_sec'], $d['tm_mon'] + 1, $d['tm_mday'], 1900 + $d['tm_year']);

		return $gmtimestamp;
	}
	// / Reserved method
	/**
	 * YOU DO NOT NEED TO CALL THIS METHOD.
	 * Method called by ob_start callback.
	 * It checks wheather the HTTP 304 code should be sent.
	 *
	 * @param string $content : content from ob.
	 * @return string
	 */
	public static function finish($content) {
		$hash = self::calculateHash($content);

		if (self::userAgentHasValidCache($hash)) {
			self::sendNotModifiedHeader($hash);
			return '';
		} else {
			self::setSessionHash($hash);
			self::sendExpireHeader($hash);
			return $content;
		}
	}
	// / Private methods
	/**
	 * Send 304 HTTP code to user-agent.
	 *
	 * @return void
	 */
	private static function sendNotModifiedHeader($hash) {
		// Prevent user-defined locale
		$defaultLocale = setlocale(LC_TIME, '0');
		setlocale(LC_TIME, 'C');
		if (self::$useEtag) {
			header('Etag:' . $hash, true, 304);
		} else {
			$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
			header($protocol . ' 304 Not Modified');
		}
		header('Date: ' . gmstrftime('%a, %d %b %Y %T %Z', $_SERVER['REQUEST_TIME']));
		// Remove Cache-Control, Pragma and Expires defined by session.cache_limiter
		header('Cache-Control: ');
		header('Pragma: ');
		header('Expires: ');
		// Reset user-defined locale
		setlocale(LC_TIME, $defaultLocale);
	}

	/**
	 * Send HTTP header with Expire header.
	 *
	 * @return void
	 */
	private static function sendExpireHeader($hash) {
		// Prevent user-defined locale
		$defaultLocale = setlocale(LC_TIME, '0');
		setlocale(LC_TIME, 'C');
		header('Cache-Control: ' . self::getCacheType());
		header('Pragma: ');
		header('Date: ' . gmstrftime('%a, %d %b %Y %T %Z', $_SERVER['REQUEST_TIME']));
		header('Expires: ' . date('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + self::getCacheTime()) . ' GMT');
		header('Last-Modified: ' . date('D, d M Y H:i:s \G\M\T', $_SERVER['REQUEST_TIME']));
		// Reset user-defined locale
		setlocale(LC_TIME, $defaultLocale);
	}

	/**
	 * Calculates the hash of a content.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	private static function calculateHash($content) {
		$content=preg_replace('|<ignore>(?:.*?)</ignore>|is','',$content);
		self::$hash = sprintf('%u', crc32($content));
		// self::$hash=md5($content);
		$_SERVER['HTTP_ETAG'] = self::$hash;
		return self::$hash;
	}

	public static function getHash() {
		return self::$hash;
	}
	/**
	 * Checks wheather the user-agent has informed If-Modified-Sice directive,
	 * and uses the list of strategies to determine if user-agent cache is
	 * still valid.
	 *
	 * @param string $hash
	 * @return bool
	 */
	private static function userAgentHasValidCache($hash) {
		// Try to get If-Modified-Since value
		$userAgentCacheTime = self::getUserAgentCacheTime();

		// Hash changed
		if (self::usesStrategy('if-modified')) {
			$sessionHash = self::getSessionHash();
			if (!$sessionHash || $sessionHash != $hash) {
				return false;
			}
		}

		if (!self::$useEtag) {
			// Time expired
			if (self::usesStrategy('if-expired')) {
				if (!$userAgentCacheTime) return false;
				if ($userAgentCacheTime + self::getCacheTime() < $_SERVER['REQUEST_TIME']) {
					return false;
				}
			}
		}

		// User-defined strategies
		$callbacks = self::getCallbacksStrategies();
		if ($callbacks) {
			foreach ($callbacks as $callback) {
				if (!is_callable($callback)) continue;
				if (!call_user_func($callback)) return false;
			}
		}
		return true; // Valid cache

	}
	// / Session control methods
	/**
	 * Save hash in session
	 *
	 * @param string $hash Hash to save
	 * @return void
	 */
	private static function setSessionHash($hash) {
		if (self::$useEtag) {
			header('Etag:' . $hash);
			return true;
		}
		isset($_SESSION) || session_start();
		$_SESSION[__FILE__][$_SERVER['REQUEST_URI']] = $hash;
	}

	/**
	 * Get hash from session or false if it was not defined.
	 *
	 * @return string || false
	 */
	private static function getSessionHash() {
		if (self::$useEtag) {
			return empty($_SERVER['HTTP_IF_NONE_MATCH']) ? false : $_SERVER['HTTP_IF_NONE_MATCH'];
		}
		isset($_SESSION) || session_start();
		if (!isset($_SESSION[__FILE__][$_SERVER['REQUEST_URI']])) {
			return false;
		}
		return $_SESSION[__FILE__][$_SERVER['REQUEST_URI']];
	}

	public static function debug($msg){
		if(self::$debug){
			file_put_contents(dirname(__FILE__).'/__Debug_'.__CLASS__.'.log',$msg,FILE_APPEND);
		}
	}

	public static function disableCache() {
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
	}
}
