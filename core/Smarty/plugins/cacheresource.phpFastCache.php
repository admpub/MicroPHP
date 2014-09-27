<?php

class Smarty_CacheResource_phpFastCache extends Smarty_CacheResource_KeyValueStore {
	/**
	 * memcache instance
	 *
	 * @var Memcache
	 */
	protected $phpFastCache = null;

	public function __construct() {
        $system = CoreLoader::$system;
		$this->phpFastCache = phpFastCache::getInstance($system['cache_config']['storage'], $system['cache_config']);
	}

	/**
	 * Read values for a set of keys from cache
	 *
	 * @param array $keys list of keys to fetch
	 * @return array list of values with the given keys used as indexes
	 * @return boolean true on success, false on failure
	 */
	protected function read(array $keys) {
		$_res = array();
		/*
		$_keys = $lookup = array();
		foreach ($keys as $k) {
			$_k = sha1($k);
			$_keys[] = $_k;
			$lookup[$_k] = $k;
		}
		$res = $this->phpFastCache->get($_keys);
		if(!$res){
			return $res;
		}
		foreach ($res as $k => $v) {
			$_res[$lookup[$k]] = $v;
		} //*/
		foreach ($keys as $k) {
			$k = sha1($k);
			$_res[$k]=$this->phpFastCache->get($k);
			//$_res[$k]=unserialize($_res[$k]);
		} //dump($_res);
		return $_res;
	}

	/**
	 * Save values for a set of keys to cache
	 *
	 * @param array $keys list of values to save
	 * @param int $expire expiration time
	 * @return boolean true on success, false on failure
	 */
	protected function write(array $keys, $expire = null) {
		foreach ($keys as $k => $v) {
			$k = sha1($k);
			//$v = serialize($v);
			$this->phpFastCache->set($k, $v, $expire);
		}
		return true;
	}

	/**
	 * Remove values from cache
	 *
	 * @param array $keys list of keys to delete
	 * @return boolean true on success, false on failure
	 */
	protected function delete(array $keys) {
		foreach ($keys as $k) {
			$k = sha1($k);
			$this->phpFastCache->delete($k);
		}
		return true;
	}

	/**
	 * Remove *all* values from cache
	 *
	 * @return boolean true on success, false on failure
	 */
	protected function purge() {
		return $this->phpFastCache->clean();
	}
}
