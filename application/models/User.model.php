<?php
class User extends CoreTableModel {
	public function __construct() {
		parent :: __construct();
		$this->init(get_class($this));
	}
	public function first() {
		return $this -> find(array('id'=>1));
	}
}
