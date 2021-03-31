<?php
// 函数助手
require_once '../autoload.php';
use PL\Whttp;

/*
$array = [
	'host' => '10.0.0.3',
	'password' => '',
];
*/

// 默认Redis配置
$default = [
    'host'    => '10.0.0.3',
    'pass'    => '',
    'expire'  => 60,
];

// p($array);
// if(array_key_exists('0',$array)){
// 	echo "存在";
// } else {
// 	echo "不存在";
// }
// p($header,true);

// exit;
$result = Whttp::get('http://10.0.0.2/whttp/demo/test.php')->timeoutms(1000)->cache($default)->getAll();
p($result);