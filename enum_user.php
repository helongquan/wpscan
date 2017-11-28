<?php

//include 'init.php';
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