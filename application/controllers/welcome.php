<?php

/**
 * Description of index
 *
 * @author Administrator
 */
class Welcome extends CoreController {

    public function doIndex($name = '') {
        $this->helper('config');


		// =========================================
		// 方法1.直接操作数据库(需要提供完整表名称)
		// =========================================
		//$this->database();
		//dump($this->db);
		//$this->db->cache_on(); //开启缓存
		//$data=$this->db->get('swh_user',$num=10,$offset=1)->result();
		//echo '[',$this->db->last_query(),']'; //开启缓存后，此处输出将为空


		// ==================================================================
		// 方法2.通过模型操作数据库(在模型中需要提供表名称中不包含前缀的部分)
		// ==================================================================
		//$this->model('User');
		//$data=$this->model->User->first();
		//echo $this->model->User->db->last_query();


		//dump($data);
        $this->view('welcome', array('msg' => $name, 'ver' => $this->config('myconfig', 'app')));
    }

	//自动生成模型类
    public function doGenM($name = '') {
		$tables=$this->db->list_tables();
		$system=CoreLoader::$system;
		self::contentType();
		if(!is_dir($system['model_folder'].'/base') && !mkdir($system['model_folder'].'/base',0777,TRUE)){
			die('文件夹:'.$system['model_folder'].'/base 创建失败，请检查文件夹“'.$system['model_folder'].'”是否具有可写权限。');
		}
		foreach($tables as $table){
			$mdlFile=$system['model_folder'].'/base/'.$table.'BaseModel'.$system['model_file_suffix'];
			file_put_contents($mdlFile, '<?php
class '.$table.'BaseModel extends CoreTableModel {
	public function __construct() {
		parent::__construct();
		$this->init(get_class($this));
	}
}
') or die('<span style="color:red">写入文件失败！</span>请检查文件夹“'.$system['model_folder'].'/base”是否具有可写权限。');
			echo '生成基类：'.$table.'BaseModel <span style="color:green">[成功]</span><br />';
			$mdlFile=$system['model_folder'].'/'.$table.$system['model_file_suffix'];
			if(file_exists($mdlFile)){
				echo '已存在模型类：'.$table.' <span style="color:gray">[跳过]</span><br />';
				continue;
			}
			file_put_contents($mdlFile, '<?php
defined(\'__DIR__\') OR define(\'__DIR__\',dirname(__FILE__));
include __DIR__.\'/base/'.$table.'BaseModel'.$system['model_file_suffix'].'\';
class '.$table.' extends '.$table.'BaseModel {
	public function __construct() {
		parent::__construct();
	}
}
');			echo '生成模型类：'.$table.' <span style="color:green">[成功]</span><br />';
		}
		echo '恭喜！所有模型基类已经生成完毕。';
    }

}
