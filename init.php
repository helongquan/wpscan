<?php
function_exists('curl_init')? '' : die('cURL Must be installed for geturl function to work. Ask your host to enable it or uncomment extension=php_curl.dll in php.ini');
ini_set('memory_limit', -1);
date_default_timezone_set('PRC');
define("wordlist",'pass.list');
define("max_user",10);// 最大列出用户数
define("USERAGENT",'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.2 Safari/537.36');
define("START_TIME", func_time());
define("Thread",10); //设置线程
define("TimeOut",5);//设置超时时间
define("params",$argv[0]);
define("target",$argv[1]);
if(target){
	$domain = trim(target);
	define("domain",$domain);
}else{

	exit('php '.params.' http://192.168.0.108/wordpress/');
}

require_once 'RollingCurl.php';

function func_time() {
    list($microsec, $sec) = explode(' ', microtime());
    return $microsec + $sec;
}


/***
*  计算执行时间
*/
function show_time(){
	echo str_repeat(" ", 50) . PHP_EOL . 'time: ' . round((func_time() - START_TIME), 4) . ' sec ' . PHP_EOL;
}
?>