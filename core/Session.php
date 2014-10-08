<?php

interface CoreSessionHandle {

    public function start($config=array());
    /**
     * Open the session
     * @return bool
     */
    public function open($save_path, $session_name);
    /**
     * Close the session
     * @return bool
     */
    public function close();
    /**
     * Read the session
     * @param int session id
     * @return string string of the sessoin
     */
    public function read($id);
    /**
     * Write the session
     * @param int session id
     * @param string data of the session
     */
    public function write($id, $data);
    /**
     * Destoroy the session
     * @param int session id
     * @return bool
     */
    public function destroy($id);
    /**
     * Garbage Collector
     * @param int life time (sec.)
     * @return bool
     * @see session.gc_divisor      100
     * @see session.gc_maxlifetime 1440
     * @see session.gc_probability    1
     * @usage execution rate 1/100
     *        (session.gc_probability/session.gc_divisor)
     */
    public function gc($max=0);
}

class MysqlSessionHandle implements CoreSessionHandle {
	private $_config;
	/**
	 * a database MySQLi connection resource
	 * @var resource
	 */
	protected $dbConnection;
	/**
	 * the name of the DB table which handles the sessions
	 * @var string
	 */
	protected $dbTable;
	public function connect() {
		$config = $this->_config;
		$dbHost = $config['host'];
		$dbPort = $config['port'];
		$dbUser = $config['user'];
		$dbPassword = $config['password'];
		$dbDatabase = $config['database'];
		$dbTable = $config['table'];
		$this->dbConnection = new mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase, $dbPort);
		$this->dbTable = $dbTable;
		if (mysqli_connect_error()) {
			throw new Exception('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}//if
	}
	/**
	 * Set db data if no connection is being injected
	 * @param 	string	$dbHost
	 * @param	string	$dbUser
	 * @param	string	$dbPassword
	 * @param	string	$dbDatabase
	 */
	public function start($config = array()) {
		$this->_config = $config = array_merge($config['common'], $config['mysql']);
		session_set_save_handler(array($this, 'open'), array($this, 'close'), array($this, 'read'), array($this, 'write'), array($this, 'destroy'), array($this, 'gc'));
	}
	/**
	 * Open the session
	 * @return bool
	 */
	public function open($save_path, $session_name) {
		if (!is_object($this->dbConnection)) {
			$this->connect();
		}
		return TRUE;
	}
	/**
	 * Close the session
	 * @return bool
	 */
	public function close() {
		return $this->dbConnection->close();
	}
	/**
	 * Read the session
	 * @param int session id
	 * @return string string of the sessoin
	 */
	public function read($id) {
		$sql = sprintf("SELECT data FROM %s WHERE id = '%s'", $this->dbTable, $this->dbConnection->escape_string($id));
		if ($result = $this->dbConnection->query($sql)) {
			if ($result->num_rows && $result->num_rows > 0) {
				$record = $result->fetch_assoc();
				 $sql = sprintf("update  %s set `timestamp` =%s where id='%s' ", $this->dbTable, time() + intval($this->_config['lifetime']), $this->dbConnection->escape_string($id));
				 $this->dbConnection->query($sql);
				return $record['data'];
			} else {
				return false;
			}
		} else {
			return false;
		}
		return true;
	}
	/**
	 * Write the session
	 * @param int session id
	 * @param string data of the session
	 */
	public function write($id, $data) {
		$sql = sprintf("REPLACE INTO %s VALUES('%s', '%s', %s)", $this->dbTable, $this->dbConnection->escape_string($id), $this->dbConnection->escape_string($data), time() + intval($this->_config['lifetime']));
		return $this->dbConnection->query($sql);
	}
	/**
	 * Destoroy the session
	 * @param int session id
	 * @return bool
	 */
	public function destroy($id) {
		unset($_SESSION);
		$sql = sprintf("DELETE FROM %s WHERE `id` = '%s'", $this->dbTable, $this->dbConnection->escape_string($id));
		return $this->dbConnection->query($sql);
	}
	/**
	 * Garbage Collector
	 * @param int life time (sec.)
	 * @return bool
	 * @see session.gc_divisor      100
	 * @see session.gc_maxlifetime 1440
	 * @see session.gc_probability    1
	 * @usage execution rate 1/100
	 *        (session.gc_probability/session.gc_divisor)
	 */
	public function gc($max = 0) {
		$sql = sprintf("DELETE FROM %s WHERE `timestamp` < %s ", $this->dbTable, time());
		return $this->dbConnection->query($sql);
	}
}
class MongodbSessionHandle implements CoreSessionHandle {
	// (helpful for sharding and replication setups)
	protected $_config;
	private $__mongo_collection = NULL;
	private $__current_session = NULL;
	private $__mongo_conn = NULL;
	public function connect() {
		$connection_string = sprintf('mongodb://%s:%s', $this->_config['host'], $this->_config['port']);
		if ($this->_config['user'] != null && $this->_config['password'] != null) {
			$connection_string = sprintf('mongodb://%s:%s@%s:%s/%s', $this->_config['user'], $this->_config['password'], $this->_config['host'], $this->_config['port'], $this->_config['database']);
		}
		$opts = array('connect' => true);
		if ($this->_config['persistent'] && !empty($this->_config['persistentId'])) {
			$opts['persist'] = $this->_config['persistentId'];
		}
		if ($this->_config['replicaSet']) {
			$opts['replicaSet'] = $this->_config['replicaSet'];
		}
		$class = 'MongoClient';
		if (!class_exists($class)) {
			$class = 'Mongo';
		}
		$this->__mongo_conn=$object_conn = new $class($connection_string, $opts);
		$object_mongo = $object_conn->{$this->_config['database']};
		$this->__mongo_collection = $object_mongo->{$this->_config['collection']};
	}
	/**
	 * Default constructor.
	 *
	 * @access  public
	 * @param   array   $config
	 */
	public function start($config = array()) {
		$config = array_merge($config['common'], $config['mongodb']);
		$this->_config = $config;
		session_set_save_handler(array(&$this, 'open'), array(&$this, 'close'), array(&$this, 'read'), array(&$this, 'write'), array(&$this, 'destroy'), array(&$this, 'gc'));
	}
	/**
	 *
	 * check for collection object
	 * @access public
	 * @param string $session_path
	 * @param string $session_name
	 * @return boolean
	 */
	public function open($session_path, $session_name) {
		if (!is_object($this->__mongo_collection)) {
			$this->connect();
		}
		$result = true;
		if ($this->__mongo_collection == NULL) {
			$result = false;
		}
		return $result;
	}
	/**
	 *
	 * doing noting
	 * @access public
	 * @return boolean
	 */
	public function close() {
		$this->__mongo_conn->close();
		return true;
	}
	/**
	 *
	 * Reading session data based on id
	 * @access public
	 * @param string $session_id
	 * @return mixed
	 */
	public function read($session_id) {
		$result = NULL;
		$ret = '';
		$expiry = time();
		$query['_id'] = $session_id;
		$query['expiry'] = array('$gte' => $expiry);
		$result = $this->__mongo_collection->findone($query);
		if ($result) {
			$this->__current_session = $result;
			$result['expiry'] = time() + $this->_config['lifetime'];
			$this->__mongo_collection->update(array("_id" => $session_id), $result);
			$ret = $result['data'];
		}
		return $ret;
	}
	/**
	 *
	 * Writing session data
	 * @access public
	 * @param string $session_id
	 * @param mixed $data
	 * @return boolean
	 */
	public function write($session_id, $data) {
		$result = true;
		$expiry = time() + $this->_config['lifetime'];
		$session_data = array();
		if (empty($this->__current_session)) {
			$session_id = $session_id;
			$session_data['_id'] = $session_id;
			$session_data['data'] = $data;
			$session_data['expiry'] = $expiry;
		} else {
			$session_data = (array) $this->__current_session;
			$session_data['data'] = $data;
			$session_data['expiry'] = $expiry;
		}
		$query['_id'] = $session_id;
		$record = $this->__mongo_collection->findOne($query);
		if ($record == null) {
			$this->__mongo_collection->insert($session_data);
		} else {
			$record['data'] = $data;
			$record['expiry'] = $expiry;
			$this->__mongo_collection->save($record);
		}
		return true;
	}
	/**
	 *
	 * remove session data
	 * @access public
	 * @param string $session_id
	 * @return boolean
	 */
	public function destroy($session_id) {
		unset($_SESSION);
		$query['_id'] = $session_id;
		$this->__mongo_collection->remove($query);
		return true;
	}
	/**
	 *
	 * Garbage collection
	 * @access public
	 * @return boolean
	 */
	public function gc($max = 0) {
		$query = array();
		$query['expiry'] = array(':lt' => time());
		$this->__mongo_collection->remove($query, array('justOne' => false));
		return true;
	}
}

class MemcacheSessionHandle {
	public function start($config = array()) {
		ini_set('session.save_handler', 'memcache');
		ini_set('session.save_path', $config['memcache']);
	}
}

class RedisSessionHandle {
	public function start($config = array()) {
		ini_set('session.save_handler', 'redis');
		ini_set('session.save_path', $config['redis']);
	}
}
