<?php
/**
 * Locale 语言包类
 * 系统语言包采用的是php-getext模块
 * # 如果模板使用的是smarty.使用了smarty-gettext插件.插件地址http://sourceforge.net/projects/smarty-gettext/
 *    php-gettext的安装和使用(ubuntu平台下)
 *    1 Installation of gettext package: sudo apt-get install php-gettext
 *    2 Install locales: see all locales in the file vim /usr/share/i18n/SUPPORTED
 *    2.1 如果没有相应语言则相应安装之。方法：sudo apt-get locale-gen zh_CN.UTF-8
 *    3 设置文件目录结构;如: Locale/zh_CN/LC_MESSAGES 或者 Locale/en_US/LC_MESSAGES
 *    4 如果是smarty模板(使用{t}你好{/t}标记)。生成.c格式的文件;如:php -q tsmarty2c.php  $file > text.c
 *    5 生成.po格式的文件;xgettext -o Dh.po --join-existing --omit-header --no-location text.c
 *    6 生成.mo格式的文件;msgfmt Dh.po -o Dh.mo
 *    7 移动mo文件到相应的Locale/en_US/LC_MESSAGES文件夹下面
 *
 * @author administrator
 */

class CoreLocale {
	/**
	 * _options 设置语言包的选项
	 * $this->_options['lang'] 应用程序使用什么语言包.php-gettext支持的所有语言都可以.
	 * 在ubuntu下使用sudo vim /usr/share/il8n/SUPPORTED 主要是utf8编码
	 * $this->_options['domain'] 生成的.mo文件的名字.一般是应用程序名
	 *
	 * @var array
	 * @access protected
	 */
	protected $_options,$_l10n;
	public static $LANG = array();

	/**
	 * 构造函数 对象初始化是设置语言包的参数
	 *
	 * @param string $lang
	 * @access public
	 * @return void
	 */
	public function __construct($lang = null) {
		switch (strtolower($lang)) {
			case 'zh_tw':
				$this -> _options = array('lang' => 'zh_TW.UTF8', 'domain' => 'main');
				break;
			case 'en':
			case 'en_us':
			case 'en_gb':
				$this -> _options = array('lang' => 'en_US.UTF8', 'domain' => 'main');
				break;
			case 'zh_cn':
			default:
				$this -> _options = array('lang' => 'zh_CN.UTF8', 'domain' => 'main');
				break;
		}

		$this -> setLocale();
	}
	public function getLocale(){
		return $this -> _options['lang'];
	}
	/**
	 * 设置应用程序语言包的参数，放在$this->_options中
	 *
	 * @param mixed $options
	 * @return void
	 */
	public function setOptions($options) {
		if (!empty($options)) $this -> _options = array_merge($this -> _options, $options);
	}

	/**
	 * 设置应用程序语言包
	 *
	 * @access public
	 * @return void
	 */
	public function setLocale() {
		putenv('LANG=' . $this -> _options['lang']);
		putenv('LANGUAGE=' . $this -> _options['lang']);
		setlocale(LC_ALL, $this -> _options['lang']);
		if (!function_exists('bindtextdomain')) {
			$this->load_textdomain($this -> _options['domain'], CoreLoader::$system['language_folder'] . '/' . $this -> _options['domain'] . '.mo');
		} else {
			bindtextdomain($this -> _options['domain'], CoreLoader::$system['language_folder'] . '/');
			textdomain($this -> _options['domain']);
			bind_textdomain_codeset($this -> _options['domain'], 'UTF-8');
		}
	}
	// ====================================
	// gettext兼容
	// ====================================
	public function load_textdomain($domain, $mofile) {
		include_once FRAMEWORK_CORE_PATH.'/Translate/gettext/streams.php';
		include_once FRAMEWORK_CORE_PATH.'/Translate/gettext/gettext.php';
		if (is_readable($mofile)) {
			$input = new CachedFileReader($mofile);
		} else {
			return;
		}

		$gettext = new gettext_reader($input);

		if (isset($this -> _l10n[$domain])) {
			$this -> _l10n[$domain] -> load_tables();
			$gettext -> load_tables();
			$this -> _l10n[$domain] -> cache_translations = array_merge($gettext -> cache_translations, $this -> _l10n[$domain] -> cache_translations);
		} else {
			$this -> _l10n[$domain] = $gettext;
		}

		unset($input, $gettext);
	}

	public function ngettext($text, $plural, $count) {
		if(isset(self::$LANG[$text]))return self::$LANG[$text];
		if(function_exists('ngettext'))return ngettext($text, $plural, $count);
		$domain = $this -> _options['domain'];
		return is_object($this -> _l10n[$domain]) ? $this -> _l10n[$domain] -> ngettext($text, $plural, $count) : $text;
	}

	public function gettext($text) {
		if(isset(self::$LANG[$text]))return self::$LANG[$text];
		if(function_exists('gettext'))return gettext($text);
		$domain = $this -> _options['domain'];
		return is_object($this -> _l10n[$domain]) ? $this -> _l10n[$domain] -> translate($text) : $text;
	}

    public function l10n($domain){
        if (isset($this -> _l10n["$domain"])) {
            return $this -> _l10n["$domain"];
        }
        return false;
    }
}
/**
 * $lang = 'zh_TW';
 * $locale = new CoreLocale($lang);
 */
?>