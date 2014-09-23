<?php
interface phpfastcache_driver {
    /*
     * Check if this Cache driver is available for server or not
     */
     function __construct($option = array());

     function checkdriver();

    /*
     * SET
     * set a obj to cache
     */
     function driver_set($keyword, $value = '', $time = 300, $option = array() );

    /*
     * GET
     * return null or value of cache
     */
     function driver_get($keyword, $option = array());

    /*
     * Delete
     * Delete a cache
     */
     function driver_delete($keyword, $option = array());

    /*
     * clean
     * Clean up whole cache
     */
     function driver_clean($option = array());
}

class phpfastcache_apc extends phpFastCache implements phpfastcache_driver {

    function checkdriver() {
        // Check apc
        if (extension_loaded('apc') && ini_get('apc.enabled')) {
            return true;
        } else {
            return false;
        }
    }

    function __construct($option = array()) {
        $this->setOption($option);
        if (!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array()) {
        if (isset($option['skipExisting']) && $option['skipExisting'] == true) {
            return apc_add($keyword, $value, $time);
        } else {
            return apc_store($keyword, $value, $time);
        }
    }

    function driver_get($keyword, $option = array()) {
        // return null if no caching
        // return value if in caching
        $data = apc_fetch($keyword, $bo);
        if ($bo === false) {
            return null;
        }
        return $data;
    }

    function driver_delete($keyword, $option = array()) {
        return apc_delete($keyword);
    }
    function driver_clean($option = array()) {
        @apc_clear_cache();
        @apc_clear_cache('user');
    }

    function driver_isExisting($keyword) {
        if (apc_exists($keyword)) {
            return true;
        } else {
            return false;
        }
    }

}

class phpfastcache_files extends phpFastCache implements phpfastcache_driver {

    function checkdriver() {
        if (is_writable($this->getPath())) {
            return true;
        } else {

        }
        return false;
    }

    /*
     * Init Cache Path
     */

    function __construct($option = array()) {

        $this->setOption($option);
        $this->getPath();

        if (!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }
    }

    /*
     * Return $FILE FULL PATH
     */

    private function getFilePath($keyword, $skip = false) {
        $path = $this->getPath();
        $code = md5($keyword);
        $folder = substr($code, 0, 2);
        $path = $path . '/' . $folder;
        /*
         * Skip Create Sub Folders;
         */
        if ($skip == false) {
            if (!file_exists($path)) {
                if (!@mkdir($path, 0777)) {
                    throw new Exception('PLEASE CHMOD ' . $this->getPath() . ' - 0777 OR ANY WRITABLE PERMISSION!', 92);
                }
            } elseif (!is_writeable($path)) {
                @chmod($path, 0777);
            }
        }

        $file_path = $path . '/' . $code . '.txt';
        return $file_path;
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array()) {
        $file_path = $this->getFilePath($keyword);
        //  echo "<br>DEBUG SET: ".$keyword." - ".$value." - ".$time."<br>";
        $data = $this->encode($value);

        $toWrite = true;
        /*
         * Skip if Existing Caching in Options
         */
        if (isset($option['skipExisting']) && $option['skipExisting'] == true && file_exists($file_path)) {
            $content = $this->readfile($file_path);
            $old = $this->decode($content);
            $toWrite = false;
            if ($this->isExpired($old)) {
                $toWrite = true;
            }
        }

        if ($toWrite == true) {
            $f = fopen($file_path, 'w+');
            fwrite($f, $data);
            fclose($f);
        }
    }

    function driver_get($keyword, $option = array()) {

        $file_path = $this->getFilePath($keyword);
        if (!file_exists($file_path)) {
            return null;
        }

        $content = $this->readfile($file_path);
        $object = $this->decode($content);

        if ($this->isExpired($object)) {
            @unlink($file_path);
            $this->auto_clean_expired();
            return null;
        }

        return $object;
    }

    function driver_delete($keyword, $option = array()) {
        $file_path = $this->getFilePath($keyword, true);
        if (@unlink($file_path)) {
            return true;
        } else {
            return false;
        }
    }

    function auto_clean_expired() {
        $autoclean = $this->get('keyword_clean_up_driver_files');
        if ($autoclean == null) {
            $this->set('keyword_clean_up_driver_files', 3600 * 24);
        }
    }

    function driver_clean($option = array()) {

        $path = $this->getPath();
        $dir = @opendir($path);
        if (!$dir) {
            throw new Exception("Can't read PATH:" . $path, 94);
        }

        while ($file = readdir($dir)) {
            if ($file != '.' && $file != '..' && is_dir($path . '/' . $file)) {
                // read sub dir
                $subdir = @opendir($path . '/' . $file);
                if (!$subdir) {
                    throw new Exception("Can't read path:" . $path . '/' . $file, 93);
                }

                while ($f = readdir($subdir)) {
                    if ($f != '.' && $f != '..') {
                        $file_path = $path . '/' . $file . '/' . $f;
                        unlink($file_path);
                    }
                } // end read subdir
            } // end if
        } // end while
    }

    function driver_isExisting($keyword) {
        $file_path = $this->getFilePath($keyword, true);
        if (!file_exists($file_path)) {
            return false;
        } else {
            // check expired or not
            $value = $this->get($keyword);
            if ($value == null) {
                return false;
            } else {
                return true;
            }
        }
    }

    function isExpired($object) {

        //expired_in为0永不过期
        if (!empty($object['expired_in']) && isset($object['expired_time']) && @date('U') >= $object['expired_time']) {
            return true;
        } else {
            return false;
        }
    }

}

class phpfastcache_memcache extends phpFastCache implements phpfastcache_driver {

    var $instant;

    function checkdriver() {
        // Check memcache
        if(function_exists('memcache_connect')) {
            return true;
        }
        return false;
    }

    function __construct($option = array()) {
        $this->setOption($option);
        if(!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }
        if ($this->checkdriver() && !is_object($this->instant)) {
            $this->instant = new Memcache();
        }
    }

    function connectServer() {
        $server = $this->option['server'];
        if(count($server) < 1) {
            $server = array(
                array('127.0.0.1',11211),
            );
        }

        foreach($server as $s) {
            $name = $s[0].'_'.$s[1];
            if(!isset($this->checked[$name])) {
                $this->instant->addserver($s[0],$s[1]);
                $this->checked[$name] = 1;
            }

        }
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array() ) {
        $this->connectServer();
        if(isset($option['skipExisting']) && $option['skipExisting'] == true) {
            return $this->instant->add($keyword, $value, false, $time );

        } else {
            return $this->instant->set($keyword, $value, false, $time );
        }

    }

    function driver_get($keyword, $option = array()) {
        $this->connectServer();
        // return null if no caching
        // return value if in caching
        $x = $this->instant->get($keyword);
        if($x == false) {
            return null;
        } else {
            return $x;
        }
    }

    function driver_delete($keyword, $option = array()) {
        $this->connectServer();
         $this->instant->delete($keyword);
    }

    function driver_clean($option = array()) {
        $this->connectServer();
        $this->instant->flush();
    }

    function driver_isExisting($keyword) {
        $this->connectServer();
        $x = $this->get($keyword);
        if($x == null) {
            return false;
        } else {
            return true;
        }
    }



}
class phpfastcache_memcached extends phpFastCache implements phpfastcache_driver {

    var $instant;

    function checkdriver() {
        if (class_exists('Memcached',FALSE)) {
            return true;
        }
        return false;
    }

    function __construct($option = array()) {
        $this->setOption($option);
        if (!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }

        if ($this->checkdriver() && !is_object($this->instant)) {
            $this->instant = new Memcached();
        }
    }

    function connectServer() {
        $s = $this->option['server'];
        if (count($s) < 1) {
            $s = array(
                array('127.0.0.1', 11211, 100),
            );
        }

        foreach ($s as $server) {
            $name = isset($server[0]) ? $server[0] : '127.0.0.1';
            $port = isset($server[1]) ? $server[1] : 11211;
            $sharing = isset($server[2]) ? $server[2] : 0;
            $checked = $name . '_' . $port;
            if (!isset($this->checked[$checked])) {
                if ($sharing > 0) {
                    $this->instant->addServer($name, $port, $sharing);
                } else {
                    $this->instant->addServer($name, $port);
                }
                $this->checked[$checked] = 1;
            }
        }
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array()) {
        $this->connectServer();
        if (isset($option['isExisting']) && $option['isExisting'] == true) {
            return $this->instant->add($keyword, $value, time() + $time);
        } else {
            return $this->instant->set($keyword, $value, time() + $time);
        }
    }

    function driver_get($keyword, $option = array()) {
        // return null if no caching
        // return value if in caching
        $this->connectServer();
        $x = $this->instant->get($keyword);
        if ($x == false) {
            return null;
        } else {
            return $x;
        }
    }

    function driver_delete($keyword, $option = array()) {
        $this->connectServer();
        $this->instant->delete($keyword);
    }

    function driver_clean($option = array()) {
        $this->connectServer();
        $this->instant->flush();
    }

    function driver_isExisting($keyword) {
        $this->connectServer();
        $x = $this->get($keyword);
        if ($x == null) {
            return false;
        } else {
            return true;
        }
    }

}
class phpfastcache_sqlite extends phpFastCache implements phpfastcache_driver {

    var $max_size = 10; // 10 mb
    var $instant = array();
    var $indexing = NULL;
    var $path = '';
    var $currentDB = 1;

    /*
     * INIT NEW DB
     */

    function initDB(PDO $db) {
        $db->exec('drop table if exists "caching"');
        $db->exec('CREATE TABLE "caching" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "keyword" VARCHAR UNIQUE, "object" BLOB, "exp" INTEGER)');
        $db->exec('CREATE UNIQUE INDEX "cleaup" ON "caching" ("keyword","exp")');
        $db->exec('CREATE INDEX "exp" ON "caching" ("exp")');
        $db->exec('CREATE UNIQUE INDEX "keyword" ON "caching" ("keyword")');
    }

    /*
     * INIT Indexing DB
     */

    function initIndexing(PDO $db) {

        // delete everything before reset indexing
        $dir = opendir($this->path);
        while ($file = readdir($dir)) {
            if ($file != '.' && $file != '..' && $file != 'indexing' && $file != 'dbfastcache') {
                unlink($this->path . '/' . $file);
            }
        }

        $db->exec('drop table if exists "balancing"');
        $db->exec('CREATE TABLE "balancing" ("keyword" VARCHAR PRIMARY KEY NOT NULL UNIQUE, "db" INTEGER)');
        $db->exec('CREATE INDEX "db" ON "balancing" ("db")');
        $db->exec('CREATE UNIQUE INDEX "lookup" ON "balacing" ("keyword")');
    }

    /*
     * INIT Instant DB
     * Return Database of Keyword
     */

    function indexing($keyword) {
        if ($this->indexing == NULL) {
            $createTable = false;
            if (!file_exists($this->path . '/indexing')) {
                $createTable = true;
            }

            $PDO = new PDO('sqlite:' . $this->path . '/indexing');
            $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($createTable == true) {
                $this->initIndexing($PDO);
            }
            $this->indexing = $PDO;
            unset($PDO);

            $stm = $this->indexing->prepare('SELECT MAX(`db`) as `db` FROM `balancing`');
            $stm->execute();
            $row = $stm->fetch(PDO::FETCH_ASSOC);
            if (!isset($row['db'])) {
                $db = 1;
            } elseif ($row['db'] <= 1) {
                $db = 1;
            } else {
                $db = $row['db'];
            }

            // check file size

            $size = file_exists($this->path . '/db' . $db) ? filesize($this->path . '/db' . $db) : 1;
            $size = round($size / 1024 / 1024, 1);


            if ($size > $this->max_size) {
                $db = $db + 1;
            }
            $this->currentDB = $db;
        }

        // look for keyword
        $stm = $this->indexing->prepare('SELECT * FROM `balancing` WHERE `keyword`=:keyword LIMIT 1');
        $stm->execute(array(
            ':keyword' => $keyword
        ));
        $row = $stm->fetch(PDO::FETCH_ASSOC);
        if (isset($row['db']) && $row['db'] != '') {
            $db = $row['db'];
        } else {
            /*
             * Insert new to Indexing
             */
            $db = $this->currentDB;
            $stm = $this->indexing->prepare('INSERT INTO `balancing` (`keyword`,`db`) VALUES(:keyword, :db)');
            $stm->execute(array(
                ':keyword' => $keyword,
                ':db' => $db,
            ));
        }

        return $db;
    }

    function db($keyword, $reset = false) {
        /*
         * Default is fastcache
         */
        $instant = $this->indexing($keyword);

        /*
         * init instant
         */
        if (!isset($this->instant[$instant])) {
            // check DB Files ready or not
            $createTable = false;
            if (!file_exists($this->path . '/db' . $instant) || $reset == true) {
                $createTable = true;
            }
            $PDO = new PDO('sqlite:' . $this->path . '/db' . $instant);
            $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($createTable == true) {
                $this->initDB($PDO);
            }

            $this->instant[$instant] = $PDO;
            unset($PDO);
        }


        return $this->instant[$instant];
    }

    function checkdriver() {
        if (extension_loaded('pdo_sqlite') && is_writeable($this->getPath())) {
            return true;
        }
        return false;
    }

    /*
     * Init Main Database & Sub Database
     */

    function __construct($option = array()) {
        /*
         * init the path
         */
        $this->setOption($option);
        if (!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }

        if ($option['storage'] == 'sqlite' && !file_exists($this->getPath() . '/sqlite')) {
            if (!@mkdir($this->getPath() . '/sqlite', 0777)) {
                die('Sorry, Please CHMOD 0777 for this path: ' . $this->getPath());
            }
        }
        $this->path = $this->getPath() . '/sqlite';
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array()) {
        $skipExisting = isset($option['skipExisting']) ? $option['skipExisting'] : false;
        $toWrite = true;

        // check in cache first
        $in_cache = $this->get($keyword, $option);

        if ($skipExisting == true) {
            if ($in_cache == null) {
                $toWrite = true;
            } else {
                $toWrite = false;
            }
        }

        if ($toWrite == true) {
            try {
                $stm = $this->db($keyword)->prepare('INSERT OR REPLACE INTO `caching` (`keyword`,`object`,`exp`) values(:keyword,:object,:exp)');
                $stm->execute(array(
                    ':keyword' => $keyword,
                    ':object' => $this->encode($value),
                    ':exp' => @date('U') + (Int) $time,
                ));

                return true;
            } catch (PDOException $e) {
                $stm = $this->db($keyword, true)->prepare('INSERT OR REPLACE INTO `caching` (`keyword`,`object`,`exp`) values(:keyword,:object,:exp)');
                $stm->execute(array(
                    ':keyword' => $keyword,
                    ':object' => $this->encode($value),
                    ':exp' => @date('U') + (Int) $time,
                ));
            }
        }

        return false;
    }

    function driver_get($keyword, $option = array()) {
        // return null if no caching
        // return value if in caching
        try {
            $stm = $this->db($keyword)->prepare('SELECT * FROM `caching` WHERE `keyword`=:keyword LIMIT 1');
            $stm->execute(array(
                ':keyword' => $keyword
            ));
            $row = $stm->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {

            $stm = $this->db($keyword, true)->prepare('SELECT * FROM `caching` WHERE `keyword`=:keyword LIMIT 1');
            $stm->execute(array(
                ':keyword' => $keyword
            ));
            $row = $stm->fetch(PDO::FETCH_ASSOC);
        }


        if ($this->isExpired($row)) {
            $this->deleteRow($row);
            return null;
        }



        if (isset($row['id'])) {
            $data = $this->decode($row['object']);
            return $data;
        }


        return null;
    }

    function isExpired($row) {
        $object=  $this->decode($row['object']);
        if (!empty($object['expired_in'])&&isset($row['exp']) && @date('U') >= $row['exp']) {
            return true;
        }

        return false;
    }

    function deleteRow($row) {
        $stm = $this->db($row['keyword'])->prepare('DELETE FROM `caching` WHERE (`id`=:id) OR (`exp` <= :U) ');
        $stm->execute(array(
            ':id' => $row['id'],
            ':U' => @date('U'),
        ));
    }

    function driver_delete($keyword, $option = array()) {
        $stm = $this->db($keyword)->prepare('DELETE FROM `caching` WHERE (`keyword`=:keyword) OR (`exp` <= :U)');
        $stm->execute(array(
            ':keyword' => $keyword,
            ':U' => @date('U'),
        ));
    }

    function driver_clean($option = array()) {
        // delete everything before reset indexing
        $dir = opendir($this->path);
        while ($file = readdir($dir)) {
            if ($file != '.' && $file != '..') {
                unlink($this->path . '/' . $file);
            }
        }
    }

    function driver_isExisting($keyword) {
        $stm = $this->db($keyword)->prepare('SELECT COUNT(`id`) as `total` FROM `caching` WHERE `keyword`=:keyword');
        $stm->execute(array(
            ':keyword' => $keyword
        ));
        $data = $stm->fetch(PDO::FETCH_ASSOC);
        if ($data['total'] >= 1) {
            return true;
        } else {
            return false;
        }
    }

}

class phpfastcache_wincache extends phpFastCache implements phpfastcache_driver  {

    function checkdriver() {
        if(extension_loaded('wincache') && function_exists('wincache_ucache_set'))
        {
            return true;
        }
        return false;
    }

    function __construct($option = array()) {
        $this->setOption($option);
        if(!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }

    }

    function driver_set($keyword, $value = '', $time = 300, $option = array() ) {
        if(isset($option['skipExisting']) && $option['skipExisting'] == true) {
            return wincache_ucache_add($keyword, $value, $time);
        } else {
            return wincache_ucache_set($keyword, $value, $time);
        }
    }

    function driver_get($keyword, $option = array()) {
        // return null if no caching
        // return value if in caching

        $x = wincache_ucache_get($keyword,$suc);

        if($suc == false) {
            return null;
        } else {
            return $x;
        }
    }

    function driver_delete($keyword, $option = array()) {
        return wincache_ucache_delete($keyword);
    }

    function driver_clean($option = array()) {
        wincache_ucache_clear();
        return true;
    }

    function driver_isExisting($keyword) {
        if(wincache_ucache_exists($keyword)) {
            return true;
        } else {
            return false;
        }
    }



}

class phpfastcache_xcache extends phpFastCache implements phpfastcache_driver  {

    function checkdriver() {
        // Check xcache
        if(extension_loaded('xcache') && function_exists('xcache_get'))
        {
           return true;
        }
        return false;

    }

    function __construct($option = array()) {
        $this->setOption($option);
        if(!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception('Can\'t use this driver for your website!');
        }

    }

    function driver_set($keyword, $value = '', $time = 300, $option = array() ) {

        if(isset($option['skipExisting']) && $option['skipExisting'] == true) {
            if(!$this->isExisting($keyword)) {
                return xcache_set($keyword,$value,$time);
            }
        } else {
            return xcache_set($keyword,$value,$time);
        }
        return false;
    }

    function driver_get($keyword, $option = array()) {
        // return null if no caching
        // return value if in caching
        $data = xcache_get($keyword);
        if($data === false || $data == '') {
            return null;
        }
        return $data;
    }

    function driver_delete($keyword, $option = array()) {
        return xcache_unset($keyword);
    }
    function driver_clean($option = array()) {
        $cnt = xcache_count(XC_TYPE_VAR);
        for ($i=0; $i < $cnt; $i++) {
            xcache_clear_cache(XC_TYPE_VAR, $i);
        }
        return true;
    }

    function driver_isExisting($keyword) {
        if(xcache_isset($keyword)) {
            return true;
        } else {
            return false;
        }
    }



}
class phpfastcache_redis extends phpFastCache implements phpfastcache_driver {

    var $instant;

    function checkdriver() {
        // Check memcache
        if (class_exists('redis',FALSE)) {
            return true;
        }
        return false;
    }

    function __construct($option = array()) {
        $this->setOption($option);
        if (!$this->checkdriver() && !isset($option['skipError'])) {
            throw new Exception("Can't use this driver for your website!");
        }
        if ($this->checkdriver() && !is_object($this->instant)) {
            $this->instant = new Redis();
        }
    }

    function connectServer() {
        $config = $this->option['redis'];
        $this->instant = new Redis();
        if ($config['type'] == 'sock') {
            $this->instant->connect($config['sock']);
        } else {
            $this->instant->connect($config['host'], $config['port'], $config['timeout'], NULL, $config['retry']);
        }
        if (!is_null($config['password'])) {
            $this->instant->auth($config['password']);
        }
        if (!is_null($config['prefix'])) {
            if ($config['prefix']{strlen($config['prefix']) - 1} != ':') {
                $config['prefix'].=':';
            }
            $this->instant->setOption(Redis::OPT_PREFIX, $config['prefix']);
        }
    }

    function driver_set($keyword, $value = '', $time = 300, $option = array()) {
        $this->connectServer();
        $value = serialize($value);
        return ($time) ? $this->instant->setex($keyword, $time, $value) : $this->instant->set($keyword, $value);
    }

    function driver_get($keyword, $option = array()) {
        $this->connectServer();
        // return null if no caching
        // return value if in caching
        if (($data = $this->instant->get($keyword))) {
            return @unserialize($data);
        } else {
            return null;
        }
    }

    function driver_delete($keyword, $option = array()) {
        $this->connectServer();
        $this->instant->delete($keyword);
    }

    function driver_clean($option = array()) {
        $this->connectServer();
        $this->instant->flushDB();
    }

    function driver_isExisting($keyword) {
        $this->connectServer();
        return $this->instant->exists($keyword);
    }

}


// main class
class phpFastCache {

    var $drivers = array('apc', 'files', 'sqlite', 'memcached', 'redis', 'wincache', 'xcache', 'memcache');
    private static $intances = array();
    public static $storage = 'auto';
    public static $config = array(
        'storage' => 'auto',
        'fallback' => array(
        ),
        'securityKey' => '',
        'htaccess' => false,
        'path' => '',
        'server' => array(
            array('127.0.0.1', 11211, 1),
        ),
        'extensions' => array(),
    );
    var $tmp = array();
    var $checked = array(
        'path' => false,
        'fallback' => false,
        'hook' => false,
    );
    var $is_driver = false;
    var $driver = NULL;
    // default options, this will be merge to Driver's Options
    var $option = array(
        'path' => '', // path for cache folder
        'htaccess' => null, // auto create htaccess
        'securityKey' => '', // Key Folder, Setup Per Domain will good.
        'system' => array(),
        'storage' => '',
        'cachePath' => '',
    );

    function __construct($storage = '', $option = array()) {
        self::setup($option);
        if (!$this->isExistingDriver($storage) && isset(self::$config['fallback'][$storage])) {
            $storage = self::$config['fallback'][$storage];
        }

        if ($storage == '') {
            $storage = self::$storage;
            self::option('storage', $storage);
        } else {
            self::$storage = $storage;
        }

        $this->tmp['storage'] = $storage;

        if ($storage != 'auto' && $storage != '' && $this->isExistingDriver($storage)) {
            $driver = 'phpfastcache_' . $storage;
        } else {
            $storage = $this->autoDriver();
            self::$storage = $storage;
            $driver = 'phpfastcache_' . $storage;
        }

        $this->option('storage', $storage);

        if (class_exists($driver, false)) {
            $this->driver = new $driver($this->option);
            $this->driver->is_driver = true;
        }
    }

    public static function getInstance($type, $config) {
        if (!isset(self::$intances[$type])) {
            self::$intances[$type] = new phpFastCache($type, $config);
        }
        return self::$intances[$type];
    }

    /*
     * Basic Method
     */

    function set($keyword, $value = '', $time = 300, $option = array()) {
        $object = array(
            'value' => $value,
            'write_time' => @date('U'),
            'expired_in' => $time,
            'expired_time' => @date('U') + (Int) $time,
        );
        if ($this->is_driver == true) {
            return $this->driver_set($keyword, $object, $time, $option);
        } else {
            return $this->driver->driver_set($keyword, $object, $time, $option);
        }
    }

    function get($keyword, $option = array()) {
        if ($this->is_driver == true) {
            $object = $this->driver_get($keyword, $option);
        } else {
            $object = $this->driver->driver_get($keyword, $option);
        }

        if ($object == null) {
            return null;
        }
        return isset($object['value'])?$object['value']:$object;
    }

    function delete($keyword, $option = array()) {
        if ($this->is_driver == true) {
            return $this->driver_delete($keyword, $option);
        } else {
            return $this->driver->driver_delete($keyword, $option);
        }
    }

    function clean($option = array()) {
        if ($this->is_driver == true) {
            return $this->driver_clean($option);
        } else {
            return $this->driver->driver_clean($option);
        }
    }

    /*
     * Begin Parent Classes;
     */

    public static function setup($name, $value = '') {
        if (!is_array($name)) {
            if ($name == 'storage') {
                self::$storage = $value;
            }

            self::$config[$name] = $value;
        } else {
            foreach ($name as $n => $value) {
                self::setup($n, $value);
            }
        }
    }

    /*
     * For Auto Driver
     *
     */

    function autoDriver() {
        foreach ($this->drivers as $namex) {
            $class = 'phpfastcache_' . $namex;
            $option = $this->option;
            $option['skipError'] = true;
            $_driver = new $class($option);
            $_driver->option = $option;
            if ($_driver->checkdriver()) {
                return $namex;
            }
        }
        $system = CoreLoader::$system;
        foreach ($system['cache_drivers'] as $filepath) {
            $file = pathinfo($filepath, PATHINFO_BASENAME);
            $namex = str_replace('.php', '', $file);
            $clazz = 'phpfastcache_' . $namex;
            $option = $this->option;
            $option['skipError'] = true;
            $_driver = new $clazz($option);
            $_driver->option = $option;
            if ($_driver->checkdriver()) {
                return $namex;
            }
        }
        return '';
    }

    function option($name, $value = null) {
        if ($value == null) {
            if (isset($this->option[$name])) {
                return $this->option[$name];
            } else {
                return null;
            }
        } else {

            if ($name == 'path') {
                $this->checked['path'] = false;
                $this->driver->checked['path'] = false;
            }
            self::$config[$name] = $value;
            $this->option[$name] = $value;
            $this->driver->option[$name] = $this->option[$name];

            return $this;
        }
    }

    public function setOption($option = array()) {
        $this->option = array_merge($this->option, self::$config, $option);
        $this->checked['path'] = false;
    }

    function __get($name) {
        $this->driver->option = $this->option;
        return $this->driver->get($name);
    }

    function __set($name, $v) {
        $this->driver->option = $this->option;
        if (isset($v[1]) && is_numeric($v[1])) {
            return $this->driver->set($name, $v[0], $v[1], isset($v[2]) ? $v[2] : array() );
        } else {
            throw new Exception("Example ->$name = array('VALUE', 300);", 98);
        }
    }
    private function isExistingDriver($class) {
        $class = strtolower($class);
        if (!class_exists('phpfastcache_' . $class, false)) {
            return false;
        }
        foreach ($this->drivers as $namex) {
            $clazz = 'phpfastcache_' . $namex;
            if (class_exists($clazz, false)) {
                $option = $this->option;
                $option['skipError'] = true;
                $_driver = new $clazz($option);
                $_driver->option = $option;
                if ($_driver->checkdriver() && $class == $namex) {
                    return true;
                }
            }
        }
        $system = CoreLoader::$system;
        foreach ($system['cache_drivers'] as $filepath) {
            $file = pathinfo($filepath, PATHINFO_BASENAME);
            $namex = str_replace('.php', '', $file);
            $clazz = 'phpfastcache_' . $namex;
            if (class_exists($clazz, false)) {
                $option = $this->option;
                $option['skipError'] = true;
                $_driver = new $clazz($option);
                $_driver->option = $option;
                if ($_driver->checkdriver() && $class == $namex) {
                    return true;
                }
            }
        }
        return false;
    }
    public function encode($data) {
        return serialize($data);
    }
    public function decode($value) {
        $x = @unserialize($value);
        if ($x == false) {
            return $value;
        } else {
            return $x;
        }
    }
    public function isPHPModule() {
        if (PHP_SAPI == 'apache2handler') {
            return true;
        } else {
            if (strpos(PHP_SAPI, 'handler') !== false) {
                return true;
            }
        }
        return false;
    }

    /*
     * return PATH for Files & PDO only
     */
    public function getPath($create_path = false) {

        if ($this->option['path'] == '' && self::$config['path'] != '') {
            $this->option('path', self::$config['path']);
        }


        if ($this->option['path'] == '') {
            // revision 618
            if ($this->isPHPModule()) {
                $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
                $this->option('path', $tmp_dir);
            } else {
                $this->option('path', dirname(__FILE__));
            }

            if (self::$config['path'] == '') {
                self::$config['path'] = $this->option('path');
            }
        }
        $full_path = $this->option('path') . '/'; //. $this->option('securityKey') . '/';

        if ($create_path == false && $this->checked['path'] == false) {

            if (!file_exists($full_path) || !is_writable($full_path)) {
                if (!file_exists($full_path)) {
                    @mkdir($full_path, 0777);
                }
                if (!is_writable($full_path)) {
                    @chmod($full_path, 0777);
                }
            }
            $this->checked['path'] = true;
        }

        $this->option['cachePath'] = $full_path;
        return $this->option['cachePath'];
    }
    /*
     * Read File
     * Use file_get_contents OR ALT read
     */
    function readfile($file) {
        if (function_exists('file_get_contents')) {
            return file_get_contents($file);
        } else {
            $string = '';

            $file_handle = @fopen($file, 'r');
            if (!$file_handle) {
                throw new Exception("Can't Read File", 96);
            }
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $string .= $line;
            }
            fclose($file_handle);

            return $string;
        }
    }
}
