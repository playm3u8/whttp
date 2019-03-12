<?php
ini_set("html_errors","On");
ini_set("display_errors","On");
require_once(__DIR__.'/../Whttp.php');
use playm3u8\Whttp;


/*
// 向浏览器输出下载文件
$url = 'https://public-filelist.oss-cn-hangzhou.aliyuncs.com/ecloud_5.1.1_setup.exe';
Whttp::proxyDownload($url);
*/

/*
$urls = 'https://www.baidu.com';
$http = Whttp::get($urls)->writefunc(function($ch, $exec){
	// 一段一段的读取，可以用来响应数据进行分析
	echo $exec;
	// 必须要,断开连接返回false
	return true;
});
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
Whttp::p($http,true);
*/
/*
$urls = array(
	'http://route.showapi.com/6-1',
	'http://route.showapi.com/6-1',
	'http://route.showapi.com/6-1',
);
Whttp::get($urls)->getGany(function($data){
	if($data['error']){
		echo "error: ".$http->getError()."<br>";
	} else {
		Whttp::p($data);
	}
});
*/
/*
$http = Whttp::get('https://www.baidu.com')->nobody(true);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getHeaders();
}
Whttp::p($http,true);
*/
/*
$http = Whttp::get('https://www.baidu.com');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
Whttp::p($http,true);
*/
// $http = Whttp::get('https://packagist.org/packages/list.json')->timeout(60)->ctimeout(10);
// $http->getDownload();
// if ($http->getError()) {
// 	$http = "error: ".$http->getError();
// } else {
// 	$http = $http->getAll();
// }
// Whttp::p($http,true);

/*
$http = Whttp::put('https://packagist.org/packages/playm3u8/whttp',['update' => '1'])->cookie('pauth=UGFja2FnaXN0XFdlYkJ1bmRsZVxFbnRpdHlcVXNlcjpjR3hoZVcwemRUZz06MTU4MTI5NjgyNjo1NWQ5OTIxODc0MmMzN2MxNTIzM2YyOWU2MDljZTljNTVhODZhMjdjZjAxMjYwMDNkZjA3MmQzMmIwNDMwZjZh')->ctimeout(10)->timeout(10);

if ($http->getError()) {

	$http = "error: ".$http->getError();

} else {
	$http = $http->getBody();
}

Whttp::p($http,true);
*/

// $file = pathinfo(parse_url('http://api.daicuo.cc',PHP_URL_PATH),PATHINFO_BASENAME);
// Whttp::p($file);



// $http = Whttp::get(["https://www.baidu.com","https://www.baidu.com"])->timeout(5)->nobody(true)
// 	->getGany(function($html){

// 	Whttp::p($html['headers']);
// });

// $http = Whttp::get("http://baidu.com")->nobody(true)->getAll();
// Whttp::p($http,true);

// $test = '11111';
// $http = Whttp::get(["http://baidu.com","http://baidu.com"])->writefunc('ddddd')->nobody(true)->getGany(function($data) use (&$test){
//     echo $test."<br>";
//     $test = "22222";
// });

// echo "ww:".$test."<br>";

// Whttp::p($http,true);

// if ($http->getError()) {
// 	echo "error: ".$http->getError();
// } else {
// 	Whttp::p($http->getHeaders());
// }

function ddddd($ch, $exec) {
	return true;
}

// exit;
// $targetUrl = 'http://yapp.applinzi.com/demo';

// 参数参考
// http://php.net/manual/zh/context.http.php
// 设置上下文context默认值
// stream_context_set_default(
//     [
//         'http' => [
//             'method'  => 'GET',
//             'timeout' => (float)0.5, // 发现这个超时时间有点偏大误差，更具实际选择吧
//             // 'proxy'   => '192.168.0.198:80',
//             // 'ignore_errors' => true,
//         ]
//     ]
// );

// $result = get_headers($targetUrl);

// Whttp::p($result);
