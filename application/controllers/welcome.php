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
        $this->view("welcome", array('msg' => $name, 'ver' => $this->config('myconfig', 'app')));
    }

}
