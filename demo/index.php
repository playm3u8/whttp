<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

/*
$http = get('http://o96.cc:4000/get.php', ['abc'=>'123','eee'=>'34555']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getAll();
}
p($http, true);
*/


/*
$urls = array(
	'https://www.mvgao.com',
	'https://www.mvgao.com',
	'https://www.mvgao.com',
);
Whttp::get($urls)->getGany(function($data){
	if($data['error']){
		echo "error: ".$data['error']."<br>";
	} else {
		// 不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
		p($data['headers']);
	}
	// 可以吧数据返回出去
	// return "sssss";
});
*/


$http = Whttp::get(['https://www.mvgao.com'])->nobody();
// if ($http->getError()) {
// 	$http = "error: ".$http->getError();
// } else {
	// $http1 = $http->getHeaders();
	// $http2 = $http->getInfo();
// }
$http1 = $http->getHeaders();
$http2 = $http->getInfo();
p($http1);
p($http2,true);


