<?php

/**
 * MicroPHP
 *
 * An open source application development framework for PHP 5.2.0 or newer
 *
 * @package MicroPHP
 * @author 狂奔的蜗牛
 * @email 672308444@163.com
 * @copyright Copyright (c) 2013 - 2014, 狂奔的蜗牛, Inc.
 * @link http://git.oschina.net/snail/microphp
 * @since Version 2.2.12
 * @createdtime 2014-09-01 10:19:02
 */
class CoreRouter {
	private static $info = null;

	public static function loadClass() {
		$system = CoreLoader :: $system;
		$methodInfo = self :: parseURI();
		self :: $info = &$methodInfo;
		// 在解析路由之后，就注册自动加载，这样控制器可以继承类库文件夹里面的自定义父控制器,实现hook功能，达到拓展控制器的功能
		// 但是plugin模式下，路由器不再使用，那么这里就不会被执行，自动加载功能会失效，所以在每个instance方法里面再尝试加载一次即可，
		// 如此一来就能满足两种模式
		CoreLoader :: classAutoloadRegister();
		// var_dump($methodInfo);
		if (file_exists($methodInfo['file'])) {
			include $methodInfo['file'];
			CoreInput :: $router = $methodInfo;
			if (!CoreInput :: isCli()) {
				// session自定义配置检查,只在非命令行模式下启用
				self :: checkSession();
			}
			$class = new $methodInfo['class']();
			if (method_exists($class, $methodInfo['method'])) {
				$methodInfo['parameters'] = is_array($methodInfo['parameters']) ? $methodInfo['parameters'] : array();
				$result = true;
				if (method_exists($class, '__before')) {
					$class->__before();
				}
				if (method_exists($class, '__output')) {
					ob_start();
					$result = call_user_func_array(array($class, $methodInfo['method']), $methodInfo['parameters']);
					if (method_exists($class, '__after')) {
						$class->__after($result);
					}
					$buffer = ob_get_contents();
					@ob_end_clean();
					$class->__output($buffer);
				} else {
					$result = call_user_func_array(array($class, $methodInfo['method']), $methodInfo['parameters']);
					if (method_exists($class, '__after')) {
						$class->__after($result);
					}
				}
				$result = null;
			} else {
				Fn::trigger404($methodInfo['class'] . ':' . $methodInfo['method'] . ' not found.');
			}
		} else {
			if ($system['debug']) {
				Fn::trigger404('file:' . $methodInfo['file'] . ' not found.');
			} else {
				Fn::trigger404();
			}
		}
	}

	private static function parseURI() {
		$pathinfo_query = self :: getQueryStr();
		// 路由hmvc模块名称信息检查
		$router['module'] = self :: getHmvcModuleName($pathinfo_query);

		$pathinfo_query = self :: checkHmvc($pathinfo_query);
		$pathinfo_query = self :: checkRouter($pathinfo_query);
		// 标记系统最终使用的路由字符串
		$router['query'] = $pathinfo_query;

		$system = CoreLoader :: $system;
		$class_method = $system['default_controller'] . '.' . $system['default_controller_method'];
		// 看看是否要处理查询字符串
		if (!empty($pathinfo_query)) {
			// 查询字符串去除头部的/
			$pathinfo_query = ltrim($pathinfo_query, '/');
			$requests = explode('/', $pathinfo_query);
			// 看看是否指定了类和方法名,最后可以有等号，兼容get表单模式
			preg_match('/[^&]+(?:\.[^&]+)+=?/', $requests[0]) ? $class_method = $requests[0] : null;
			if (strstr($class_method, '&') !== false) {
				$cm = explode('&', $class_method);
				$class_method = trim($cm[0], "=");
			}
		}
		// 去掉最后面的等号（如果有）
		$class_method = trim($class_method, '=');
		// 去掉查询字符串中的类方法部分，只留下参数
		$pathinfo_query = str_replace($class_method, '', $pathinfo_query);
		$pathinfo_query_parameters = explode('&', $pathinfo_query);
		$pathinfo_query_parameters_str = !empty($pathinfo_query_parameters[0]) ? $pathinfo_query_parameters[0] : '';
		// 去掉参数开头的/，只留下参数
		$pathinfo_query_parameters_str && $pathinfo_query_parameters_str{0} === '/' ? $pathinfo_query_parameters_str = substr($pathinfo_query_parameters_str, 1) : '';
		// 现在已经解析出了，$class_method类方法名称字符串(main.index），$pathinfo_query_parameters_str参数字符串(1/2)，进一步解析为真实路径
		$origin_class_method = $class_method;
		$class_method = explode('.', $class_method);
		$method = end($class_method);
		if ($system['controller_method_ucfirst'])$method = ucfirst($method);
		$router['method_raw'] = $method;
		$method = $system['controller_method_prefix'] . $method;

		unset($class_method[count($class_method) - 1]);

		$file = $system['controller_folder'] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $class_method) . $system['controller_file_suffix'];
		$class = $class_method[count($class_method) - 1];
		$parameters = explode('/', $pathinfo_query_parameters_str);

		if (count($parameters) === 1 && (empty($parameters[0]) || strpos($parameters[0], '=') !== false)) {
			$parameters = array();
		}
		// 对参数进行urldecode解码一下
		foreach ($parameters as $key => $value) {
			$parameters[$key] = urldecode($value);
		}
		$info = array('file' => $file, 'class' => ucfirst($class), 'method' => str_replace('.', '/', $method), 'parameters' => $parameters);
		// 开始准备router信息
		$path = explode('.', $origin_class_method);
		$router['mpath'] = $origin_class_method;
		$router['m'] = $path[count($path) - 1];
		$router['c'] = '';
		if (count($path) > 1) {
			$router['c'] = $path[count($path) - 2];
		}
		$router['prefix'] = $system['controller_method_prefix'];
		unset($path[count($path) - 1]);
		$router['cpath'] = empty($path) ? '' : implode('.', $path);
		$router['folder'] = '';
		if (count($path) > 1) {
			unset($path[count($path) - 1]);
			$router['folder'] = implode('.', $path);
		}

		return $router + $info;
	}

	private static function getQueryStr() {
		$system = CoreLoader :: $system;
		// 命令行运行检查
		if (CoreInput :: isCli()) {
			global $argv;
			$pathinfo_query = isset($argv[1]) ? $argv[1] : '';
		} else {
			$pathinfo = @parse_url($_SERVER['REQUEST_URI']);
			if (empty($pathinfo)) {
				if ($system['debug']) {
					Fn::trigger404('request parse error:' . $_SERVER['REQUEST_URI']);
				} else {
					Fn::trigger404();
				}
			}
			// pathinfo模式下有?,那么$pathinfo['query']也是非空的，这个时候查询字符串是PATH_INFO和query
			$query_str = empty($pathinfo['query']) ? '' : $pathinfo['query'];
			$pathinfo_query = empty($_SERVER['PATH_INFO']) ? $query_str : $_SERVER['PATH_INFO'] . '&' . $query_str;
		}
		if ($pathinfo_query) {
			$pathinfo_query = trim($pathinfo_query, '/&');
		}
		// urldecode 解码所有的参数名，解决get表单会编码参数名称的问题
		$pq = $_pq = array();
		$_pq = explode('&', $pathinfo_query);
		foreach ($_pq as $value) {
			$p = explode('=', $value);
			if (isset($p[0])) {
				$p[0] = urldecode($p[0]);
			}
			$pq[] = implode('=', $p);
		}
		$pathinfo_query = implode('&', $pq);
		return $pathinfo_query;
	}

	private static function checkSession() {
		$system = CoreLoader :: $system;
		$common_config = $system['session_handle']['common'];
		ini_set('session.auto_start', 0);
		ini_set('session.gc_probability', 1);
		ini_set('session.gc_divisor', 100);
		ini_set('session.gc_maxlifetime', $common_config['lifetime']);
		ini_set('session.referer_check', '');
		ini_set('session.entropy_file', '/dev/urandom');
		ini_set('session.entropy_length', 16);
		ini_set('session.use_cookies', 1);
		ini_set('session.use_only_cookies', 1);
		ini_set('session.use_trans_sid', 0);
		ini_set('session.hash_function', 1);
		ini_set('session.hash_bits_per_character', 5);
		session_cache_limiter('nocache');
		session_set_cookie_params(
				$common_config['lifetime'], $common_config['cookie_path'], preg_match('/^[^\\.]+$/', CoreInput::server('HTTP_HOST')) ? null : $common_config['cookie_domain']
		);
		session_name($common_config['session_name']);
		register_shutdown_function('session_write_close');
		if (!empty($system['session_handle']['handle']) && isset($system['session_handle'][$system['session_handle']['handle']])) {
			$driver = $system['session_handle']['handle'];
			$config = $system['session_handle'];
			$handle = ucfirst($driver) . 'SessionHandle';
			if (class_exists($handle, FALSE)) {
				$session = new $handle();
				$session->start($config);
			}
		}
		if ($common_config['autostart']) {
			sessionStart();
		}
	}

	private static function checkRouter($pathinfo_query) {
		$system = CoreLoader :: $system;
		if (is_array($system['route'])) {
			foreach ($system['route'] as $reg => $replace) {
				if (preg_match($reg, $pathinfo_query)) {
					$pathinfo_query = preg_replace($reg, $replace, $pathinfo_query);
					break;
				}
			}
		}
		return $pathinfo_query;
	}

	private static function checkHmvc($pathinfo_query) {
		if ($_module = self :: getHmvcModuleName($pathinfo_query)) {
			$_system = CoreLoader :: $system;
			self :: switchHmvcConfig($_system['hmvc_modules'][$_module]);
			return preg_replace('|^' . $_module . '[\./&]?|', '', $pathinfo_query);
		}
		return $pathinfo_query;
	}

	private static function getHmvcModuleName($pathinfo_query) {
		$_module = current(explode('&', $pathinfo_query));
		$_module = current(explode('/', $_module));
		$_system = CoreLoader :: $system;
		if (isset($_system['hmvc_modules'][$_module])) {
			return $_module;
		} else {
			return '';
		}
	}

	public static function switchHmvcConfig($hmvc_folder) {
		$_system = $system = CoreLoader :: $system;
		$module = $_system['hmvc_folder'] . '/' . $hmvc_folder . '/hmvc.php';
		// $system被hmvc模块配置重写
		include($module);
		// 共享主配置：模型，视图，类库，helper,同时保留自动加载的东西
		foreach (array('model_folder', 'view_folder', 'library_folder', 'helper_folder', 'helper_file_autoload', 'library_file_autoload', 'models_file_autoload') as $folder) {
			if (!is_array($_system[$folder])) {
				$_system[$folder] = array($_system[$folder]);
			}
			if (!is_array($system[$folder])) {
				$system[$folder] = array($system[$folder]);
			}
			$system[$folder] = array_merge($system[$folder], $_system[$folder]);
		}
		// 切换核心配置
		CoreLoader :: $system = $system;
	}

	public static function setConfig($system) {
		CoreLoader :: $system = $system;
	}

	public static function info() {
		return self :: $info;
	}

	public static function controller() {
		return self :: $controller;
	}
}

/**
 * End of file Router.php
 */