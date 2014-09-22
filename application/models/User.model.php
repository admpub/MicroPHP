<?php
class User extends CoreTableModel {
	public function __construct() {
		parent :: __construct();
		$this->init(__CLASS__);
	}
	public function first() {
		return $this -> find(array('id'=>1));
	}
}
