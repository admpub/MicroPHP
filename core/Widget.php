<?php
class CoreWidget {
	protected $view = null,$name = null,$view_file = 'view.tpl';
	protected $old_template_dir = null,$old_config_dir = null;
	protected $old_caching = null,$old_cache_lifetime = null;
	public static $debug = false;

	final public static function factory($name,&$smarty) {
		$className = function_exists('get_called_class') ? get_called_class() : $name.'Widget';
		return new $className($name,$smarty);
	}

	final private function __construct($name,&$smarty=null) {
		$this -> name = $name;
		$this -> view = $smarty;
	}

	final public function init($params,&$smarty=null) {
		if($smarty) $this -> view = $smarty;
		$this -> view -> assign($params);
		return $this;
	}

	final public function _setTpl($view_file) {
		$this -> view_file = $view_file;
		return $this;
	}

	final protected function setCache($enable, $lifetime = 3600) {
		if (is_null($this -> old_caching))
			$this -> old_caching = $this -> view -> caching;
		if (is_null($this -> old_cache_lifetime))
			$this -> old_cache_lifetime = $this -> view -> cache_lifetime;
		$this -> view -> caching = $enable;
		$this -> view -> cache_lifetime = $lifetime;
		return $this;
	}

	final protected function display($cacheId=null, $callback=null) {
		$className = strtolower(get_class($this));
		$this -> old_template_dir = $this -> view -> template_dir;
		$this -> view -> template_dir = CoreLoader::$system['widget_folder'] .'/'. $this -> name . '/views/';
		if(!self::$debug && $cacheId){
			$cacheId = 'Widget|'.$cacheId;
			if(!$this -> view -> caching){
				$this->setCache(TRUE);
			}
		}
		if($this -> view -> caching){
			if($cacheId && $callback && $this -> view -> isCached($this -> view_file, $cacheId) == false){
				if(is_array($callback)){
					$params = array();
					if(isset($callback[2])){
						$params = $callback[2];
						unset($callback[2]);
					}
					$data = call_user_func_array(array($callback[0],$callback[1]),$params);
				}else{
					$data = $callback();
				}
				$this -> view -> assign($data);
				CoreLoader::log('['.$className.'][caching:on] updating data from $callback.');
			}
			$this -> view -> display($this -> view_file,$cacheId);

		}else{
			if($callback){
				if(is_array($callback)){
					$params = array();
					if(isset($callback[2])){
						$params = $callback[2];
						unset($callback[2]);
					}
					$data = call_user_func_array(array($callback[0],$callback[1]),$params);
				}else{
					$data = $callback();
				}
				$this -> view -> assign($data);
				CoreLoader::log('['.$className.'][caching:off] reading data from $callback.');
			}
			$this -> view -> display($this -> view_file);
		}
		$this -> view -> template_dir = $this -> old_template_dir;
		if (!is_null($this -> old_caching))
			$this -> view -> caching = $this -> old_caching;
		if (!is_null($this -> old_cache_lifetime))
			$this -> view -> cache_lifetime = $this -> old_cache_lifetime;
	}

	/**
	 * 执行 widget.
	 * ------------------------------------------------------------
	 *       捕获某个widget的输出  种用法使得widget可以嵌套使用
	 *          ob_start();
	 *          ob_implicit_flush(false);
	 *          $widget = MyWidget::factory($params);
	 *          $widget->execute();
	 *          return ob_get_clean();
	 * ------------------------------------------------------------
	 */
	public function execute($params=array()) {
		return $this -> display();
	}
}

/**
 * class_exists('CoreWidget',false) || die('Access Denied.');
 * class CategoriesWidget extends CoreWidget {
 *
 * public function execute() {
 * // your widget runs, happily ever after...
 * // this is where you can load in models (if you do MVC).
 * // use getInstance() get controller instance.
 * return $this->display();
 * }
 *
 * }
[dir tree]
 widget+
		|
		+Categories[dir]+
		|				|
		|				+Categories.php
		|				+views[dir]	+
		|							|
		|							+view.tpl
		+Categories2[dir]+
		|				|
		|				+Categories2.php
		|				+views[dir]	+
		|							|
		|							+view.tpl
 */