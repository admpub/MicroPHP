<?php

/**
 * Description of index
 *
 * @author Administrator
 */
class Welcome extends CoreController {

    public function doIndex($name = '') {
        $this->helper('config');
		//$this->database();
		//dump($this->db);
        $this->view("welcome", array('msg' => $name, 'ver' => $this->config('myconfig', 'app')));
    }

}
