<?php

/**
 * Description of index
 *
 * @author Administrator
 */
class Home extends CoreController {

    public function doIndex($name = '') {
        $this->view("welcome", array('msg' => $name, 'ver' => $this->config('myconfig', 'app')));
    }


}

