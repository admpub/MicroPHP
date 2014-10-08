<?php
/**
 * MicroPHP
 *
 * An open source application development framework for PHP 5.2.0 or newer
 *
 * @package                MicroPHP
 * @author                狂奔的蜗牛
 * @email                672308444@163.com
 * @copyright          Copyright (c) 2013 - 2014, 狂奔的蜗牛, Inc.
 * @link                http://git.oschina.net/snail/microphp
 * @since                Version 2.2.12
 * @createdtime       2014-09-01 10:19:02
 * @property CI_DB_active_record $db
 * @property phpFastCache        $cache
 * @property CoreInput          $input
 */
class CoreController extends CoreLoaderPlus {

    private static $instance;

    public function __construct() {
        self::$instance = &$this;
        $this->autoload();
        parent::__construct();
    }

    private function autoload() {
        $system = CoreLoader::$system;
        $autoload_helper = $system['helper_file_autoload'];
        $autoload_library = $system['library_file_autoload'];
        $autoload_models = $system['models_file_autoload'];
        foreach ($autoload_helper as $val) {
            if (is_array($val)) {
                $key = key($val);
                $val = $val[$key];
                $this->helper($key,$val);
            } else {
                $this->helper($val);
            }
        }
        foreach ($autoload_library as $key => $val) {
            if (is_array($val)) {
                $new = isset($val['new']) ? $val['new'] : true;
                $key = key($val);
                $val = $val[$key];

                $this->lib($key, $val, $new);
            } else {
                $this->lib($val);
            }
        }
        foreach ($autoload_models as $key => $val) {
            if (is_array($val)) {
                $key = key($val);
                $val = $val[$key];
                $this->model($key, $val);
            } else {
                $this->model($val);
            }
        }
        /**
         * 如果使用了自定义缓存驱动，加载相应的文件
         */
        static $included = array();
        foreach ($system['cache_drivers'] as $filepath) {
            $file = pathinfo($filepath, PATHINFO_BASENAME);
            $namex = str_replace('.php', '', $file);
            //只include选择的缓存驱动文件
            if ($namex == $system['cache_config']['storage']) {
                if (!isset($included[truepath($filepath)])) {
                    CoreLoader::includeOnce($filepath);
                } else {
                    $included[truepath($filepath)] = 1;
                }
            }
        }
    }

    public static function &getInstance() {
        return self::$instance;
    }

    /**
     * 实例化一个控制器
     * @staticvar array $loadedClasses
     * @param type $classname_path
     * @param type $hmvc_module_floder
     * @return CoreController
     */
    public static function instance($classname_path = null, $hmvc_module_floder = NULL) {
        if (!empty($hmvc_module_floder)) {
            CoreRouter::switchHmvcConfig($hmvc_module_floder);
        }
        if (empty($classname_path)) {
            CoreLoader::classAutoloadRegister();
            return new self();
        }
        $system = CoreLoader::$system;
        $classname_path = str_replace('.', DIRECTORY_SEPARATOR, $classname_path);
        $classname = basename($classname_path);
        $filepath = $system['controller_folder'] . DIRECTORY_SEPARATOR . $classname_path . $system['controller_file_suffix'];
        $alias_name = strtolower($classname);
        static $loadedClasses = array();
        if (in_array($alias_name, array_keys($loadedClasses))) {
            return $loadedClasses[$alias_name];
        }
        if (file_exists($filepath)) {
            //在plugin模式下，路由器不再使用，那么自动注册不会被执行，自动加载功能会失效，所以在这里再尝试加载一次，
            //如此一来就能满足两种模式
            CoreLoader::classAutoloadRegister();
            CoreLoader::includeOnce($filepath);
            if (class_exists($classname, FALSE)) {
                return $loadedClasses[$alias_name] = new $classname();
            } else {
                trigger404('Ccontroller Class:' . $classname . ' not found.');
            }
        } else {
            trigger404($filepath . ' not found.');
        }
    }
}




class CoreControllerPlus extends CoreController {
	public $page_title = '',$page_keywords = '',$page_description = '';
	protected $view = null, $tpl_ext = '.tpl',$router_info = null;

	public function __construct() {
		parent::__construct();
		$this->router_info = CoreRouter::info();

		// ====================================
		// 确定语言
		// ====================================
		$cookie_lifetime=7*86400;
		if (!empty($_GET['lang']) && preg_match('/^[a-z\\-]+$/', $_GET['lang'])) {
			if ($this->validLanguage($_GET['lang'])) {
				CoreLoader::setCookie('lang', $_GET['lang'], $cookie_lifetime);
				CoreLoader::$system['language'] = $_GET['lang'];
			} else {
				CoreLoader::setCookie('lang', CoreLoader::$system['default_language'], $cookie_lifetime);
				CoreLoader::$system['language'] = CoreLoader::$system['default_language'];
			}
		} else {
			$cookieLang = CoreInput::cookie('lang');
			if (empty($cookieLang) || !preg_match('/^[a-z\\-]+$/', $cookieLang) || !$this->validLanguage($cookieLang)) {
				// if user doesn't specify any language, check his/her browser language
				// check if the visitor language is supported
				// $this->language(true) to return the country code such as en-US zh-CN zh-TW
				$countryCode = true;
				$langcode = (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
				$langcode = (!empty($langcode)) ? explode(';', $langcode) : $langcode;
				$langcode = (!empty($langcode[0])) ? explode(',', $langcode[0]) : $langcode;
				if(!$countryCode)
					$langcode = (!empty($langcode[0])) ? explode('-', $langcode[0]) : $langcode;

				$lang = $langcode[0];
				$lang = strtolower($lang);
				if (preg_match('/^[a-z\\-]+$/', $lang) && $this->validLanguage($lang)) {
					CoreLoader::setCookie('lang', $lang, $cookie_lifetime);
					CoreLoader::$system['language'] = $lang;
				} else {
					CoreLoader::setCookie('lang', CoreLoader::$system['default_language'], $cookie_lifetime);
					CoreLoader::$system['language'] = CoreLoader::$system['default_language'];
				}
			} else {
				CoreLoader::$system['language'] = $cookieLang;
			}
		}
		#echo CoreLoader::$system['language'];
		$locale=CoreLoader::locale(CoreLoader::$system['language']);
		$langEncoding=explode('.',$locale->getLocale());
		$localeDataFile=CoreLoader::$system['language_folder'] . '/' . $langEncoding[0] . '/locale.php';
		if(file_exists($localeDataFile)){
			CoreLocale::$LANG = include($localeDataFile);
		}



		// ====================================
		// 初始化Smarty模板引擎
		// ====================================
		include_once(FRAMEWORK_CORE_PATH.'/Smarty/Smarty.class.php');
		$smarty = new Smarty();
		$view_dir = self::$system['view_folder'];
		$cache_dir = self::$system['table_cache_folder'] . '/smarty';
		// $smarty->force_compile = true;
		$smarty->error_reporting = 9;//E_ALL;
		$smarty->debugging = false;
		$smarty->caching = false;
		//$smarty->use_sub_dirs = true;
		$smarty->cache_lifetime = 120;
		$smarty->left_delimiter = '<{';
		$smarty->right_delimiter = '}>';
		$smarty->allow_php_templates = true;
		$smarty->setTemplateDir($view_dir);
		$smarty->setCompileDir($cache_dir . '/templates_c/');
		$smarty->setConfigDir($cache_dir . '/configs/');
		$smarty->setCacheDir($cache_dir . '/cache/');
		$smarty->registered_cache_resources = array('phpFastCache','memcache');
		//$smarty->caching_type = 'phpFastCache'; //TODO: 缓存无效
		$smarty->caching_type = 'file';
		switch(phpFastCache::$storage){
			case 'memcache':
			case 'apc': $smarty->caching_type = phpFastCache::$storage;
				break;
			default:
				if(function_exists('apc_cache_info')){
					$smarty->caching_type = 'apc';
				}
				break;
		}
		$smarty->default_resource_type = 'file'; //模板保存方式

		// ====================================
		// 设置全局变量
		// ====================================
		$smarty->assignGlobal('TITLE', '');
		$smarty->assignGlobal('KEYWORDS', '');
		$smarty->assignGlobal('DESCRIPTION', '');

		// ====================================
		// 注册自定义函数
		// ====================================
		//$smarty->registerPlugin('function', 'burl', 'build_url');
		//<{burl param=value}> ===> function build_url(...){...}
		//$smarty->registerPlugin('modifier', 'astatus', 'audit_status');
		//<{$var|astatus}> ===> function audit_status(...){...}
		$smarty->registerPlugin('modifier', 'url', 'Fn::url');

		$this->view = &$smarty;
	}

	public function validLanguage($lang){
		$languages=array('en'=>'en','ru'=>'ru','zh_cn'=>'zh_cn');
		return isset($languages[$lang]);
	}

	//无缓存的视图渲染，只要控制器函数不返回false，会自动调用本函数
	public function view($view_name, $data = null, $return = false) {
		$this->view->assignGlobal('TITLE', $this->page_title);
		$this->view->assignGlobal('KEYWORDS', $this->page_keywords);
		$this->view->assignGlobal('DESCRIPTION', $this->page_description);
		$this->view->assign($data);
		if ($return) {
			return $this->view->fetch($view_name . $this->tpl_ext);
		} else {
			self::contentType('html');
			$this->view->display($view_name . $this->tpl_ext);
		}
	}

	//有缓存的视图渲染，可以在控制器函数末尾return此函数
	public function viewCached($cacheId, $callback, $view_name = null) {
		if(is_null($view_name)){
			$view_name = $this->router_info['class'].'/'.$this->router_info['method_raw'];
		}
		if($this->view->isCached($view_name . $this->tpl_ext, $cacheId) == false){
			$this->view->assignGlobal('TITLE', $this->page_title);
			$this->view->assignGlobal('KEYWORDS', $this->page_keywords);
			$this->view->assignGlobal('DESCRIPTION', $this->page_description);
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
			$this->view->assign($data);
		}
		self::contentType('html');
		$this->view->display($view_name . $this->tpl_ext, $cacheId);
		return false;
	}

	public function V(){
		return $this->view;
	}

	public function __before(){

	}

	public function __after($result=null){
		if($result!==false){
			//自动渲染模板
			$this->view($this->router_info['class'].'/'.$this->router_info['method_raw'],$result);
		}
	}
}

/* End of file Controller.php */