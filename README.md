# Whttp
# PHP非阻塞并发HTTP请求类(采集爬虫专用)
*下面是详细使用方法*
```
$ composer require playm3u8/whttp
```
*1. 引用命名空间*
```php
use PL\Whttp;
```

*2. 请求方式*
```php
// GET:
$http = Whttp::get('https://www.baidu.com');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
```php
// POST:
$http = Whttp::post('https://www.baidu.com',['name'=>'playm3u8']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
```php
// PUT:
$http = Whttp::put('https://www.baidu.com',['name'=>'playm3u8']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
```php
// PATCH:
$http = Whttp::patch('https://www.baidu.com',['name'=>'playm3u8']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
```php
// DELETE:
$http = Whttp::delete('https://www.baidu.com',['name'=>'playm3u8']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
*3. 设置Cookie*
```php
$http = Whttp::get('https://www.baidu.com')->cookie('user=playm3u8');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http,true);
```
*4. 我们来尝试获取百度搜索的Headers，看看是怎么操作的。*
```php
$http = Whttp::get('https://www.baidu.com')->nobody(true);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getHeaders();
}
p($http,true);
```
*5. 获取响应状态（code）*
```php
$http = Whttp::get('https://www.baidu.com');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getCode();
}
p($http,true);
```
*6. 获取响应状态（info）*
```php
$http = Whttp::get('https://www.baidu.com');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getInfo();
}
p($http,true);
```
*7. 获取JSON再格式化为数组*
```php
$http = Whttp::get('http://route.showapi.com/6-1');
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getJson();
}
p($http,true);
```
*8. 批量处理URL*
```php
$urls = array(
	'http://route.showapi.com/6-1',
	'http://route.showapi.com/6-1',
	'http://route.showapi.com/6-1',
);
Whttp::get($urls)->getGany(function($data){
	if($data['error']){
		echo "error: ".$data['error']."<br>";
	} else {
		// 不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
		p($data);
	}
	// 可以吧数据返回出去
	// return "sssss";
});
```
*9. 下载文件*
```php
// 单个URL
$urls = 'https://dldir1.qq.com/weixin/Windows/WeChatSetup.exe';
// 多个URL
/*
$urls = array(
	'https://dldir1.qq.com/weixin/Windows/WeChatSetup.exe',
	'https://dldir1.qq.com/weixin/Windows/WeChatSetup.exe'
);
*/
$http = Whttp::get($urls)->timeout(60);
// 开始下载
$http->getDownload();
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getAll('download');
}
p($http,true);
```
*9. 上传文件*
```php
$path = 'qrcode-viewfile.png';
$data = [
  'id'   => 'WU_FILE_0',
  'name' => 'qrcode-viewfile.png',
  'type' => 'image/png',
  'lastModifiedDate' => date('r',filemtime($path)).' (中国标准时间)',
  'size' => filesize($path),
  'file' => new CURLFile($path),
];
$http = Whttp::post('http://www.wwei.cn/qrcode-fileupload.html?op=index_jiema', $data);
$http = $http->header(['Access-Sign: *','Origin: http://www.wwei.cn']);
p($http->getJson(), true);
```
*10. 过程干预*
```php
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
p($http,true);
```
*11. 原代码说明（参考）*
```php
/**
 * 请求模式参数
 * @var array
 */
private static $method = array(
    'GET'     => ['string|array', 'string|array'],
    'POST'    => ['string|array', 'string|array'],
    'PUT'     => ['string|array', 'string|array'],
    'PATCH'   => ['string|array', 'string|array'],
    'DELETE'  => ['string|array', 'string|array'],
);
```
```php
/**
 * 设置参数列表1
 * @var array
 */
protected static $setlist1 = array(
    'jump'      => ['boolean|NULL'],       
    // 跳过重定向(默认会跳过重定向)(可空)

    'header'    => ['array'],        
    // 请求协议头

    'cookie'    => ['string'],   
    // 请求cookie

    'timeout'   => ['integer', 'integer|NULL'],
    // 默认超时时间都是5秒
    // 超时时间(参数1响应超时、参数2连接超时)默认设置一个参数是请求超时，支持数组(秒)

    'timeoutms' => ['integer', 'integer|NULL'],
    // 默认超时时间都是5000毫秒
    // 超时时间(参数1响应超时、参数2连接超时)默认设置一个参数是请求超时，支持数组(毫秒)

    'nobody'    => ['boolean|NULL'],            
    // 不要body 只返回响应头信息(默认要body)(超快)(可空)

    'referer'   => ['string'],       
    // 伪装请求来路
    
    'proxy'     => ['string'],       
    // HTTP代理
    
    'socks5'    => ['string'], 
    // socks5代理
    
    'fool'      => ['string'],       
    // 伪装用户IP，有些无效
    
    'utf8'      => ['boolean|NULL'],     
    // 解码UTF8响应内容(在返回内容乱码的情况下使用)(可空) 
    
    'left'      => ['string'],    
    // 截取返回Body指定左边字符
    
    'core'      => ['string', 'string'],    
    // 截取返回Body指定中间字符
    
    'right'     => ['string'],   
    // 截取返回Body指定右边字符
    
    'cache'     => ['string', 'string|NULL', 'integer', 'integer', 'integer'],
    // 缓存配置 [IP,密码,缓存时间,限制超时次数,超时超过次数缓存的时间]
    
    'writefunc' => ['callable'],    
    // 回调方法,可以干预实时获取的内容,有2个参数 function($ch,$exec){}
);
```
*返回方法说明*
```php
/**
 * 获取响应状态码(不支持并发)
 * @return int 状态码
 */
public function getCode();

/**
 * 获取响应头部(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return string       
 */
public function getHeaders (string $name="");

/**
 * 获取响应内容(不支持并发)
 * @return data
 */
public function getBody();

/**
 * 获取请求信息(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array
 */
public function getInfo(string $name="");

/**
 * 获取错误信息(不支持并发)
 * @return string
 */
public function getError();

/**
 * 以数组形式返回(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array       
 */
public function getJson(string $name="");

/**
 * 获取到全部信息(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array
 */
public function getAll(string $name="");

/**
 * 执行多任务并发
 * 回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
 * @param  callable $callback 回调函数，有1个参数 function($data){}
 */
public function getGany(callable $callback);

/**
 * 下载文件(支持批量)
 * @param  string $name 文件名称,为空自动更具URL识别文件名
 * @param  string $path 保存目录
 * @return string       
 */
public function getDownload($name=null, $path);
```
