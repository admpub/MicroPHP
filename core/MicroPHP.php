<?php
/*
 * Copyright 2014 coscms.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
define('FRAMEWORK_CORE_PATH',defined('__DIR__')?__DIR__:dirname(__FILE__));
define('FRAMEWORK_CORE_RUNTIME',FRAMEWORK_CORE_PATH.'/~Runtime.php');
isset($system) OR die('$system is undefined.');

function compress_php($phpFile,$delStart=true){
	$content=php_strip_whitespace($phpFile);
	$content=preg_replace('/[\\s]*\\?\\>$/','',trim($content));
	$delStart && $content=preg_replace('/^\\<\\?php[\\s]*/','',$content);
	return $content;
}

if(!$system['debug'] && file_exists(FRAMEWORK_CORE_RUNTIME)){
	include(FRAMEWORK_CORE_RUNTIME);
}else{
	$coreFiles=array('Helper','Loader','Input','Controller','Model','Router','Rule','DB','Cache','Session');
	if($system['debug']){
		foreach($coreFiles as $fileName){
			include(FRAMEWORK_CORE_PATH.'/'.$fileName.'.php');
		}
		file_exists(FRAMEWORK_CORE_RUNTIME) && unlink(FRAMEWORK_CORE_RUNTIME);
	}else{
		$content='';
		foreach($coreFiles as $fileName){
			$content.=compress_php(FRAMEWORK_CORE_PATH.'/'.$fileName.'.php');
		}
		file_put_contents(FRAMEWORK_CORE_RUNTIME,'<?php '.$content) OR die(FRAMEWORK_CORE_RUNTIME.' is not writeable.');
		include FRAMEWORK_CORE_RUNTIME;
	}
}


class CI_DB_Cache extends phpFastCache{

}
