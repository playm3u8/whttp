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
$http = Whttp::get('https://www.baidu.com')->nobody();
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
Whttp::get($urls)->gany(function($data){
    if($data['error']){
        echo "error: ".$data['error']."<br>";
    } else {
        // 不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
        p($data);
    }
    // 可以吧数据返回出去
    // return "sssss";
})->getAll();
```
*9. 下载文件*
```php
// 单个URL
$url = 'https://www.baidu.com/index.html';
$http = Whttp::get($url)->getDownload();
if (empty($http['error']) {
    $http = "error: ".$http['error'];
} else {
    $http = $http['download'];
}
p($http,true);
// (简单)批量, 建议在命令行模式下运行
$url[] = 'https://www.baidu.com/index.html';
$url[] = 'https://www.baidu.com/index.html';
$url[] = 'https://www.baidu.com/index.html';
$rsult = Whttp::get($url)
    ->savepath($path)
    ->concurrent(10)
    ->gany(Function($data) {
        if($data['error']){
            echo "error: ".$data['error']."\n";
        } else {
            // 不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
            p($data['download']);
        }
        // 可以吧数据修改了返回出去
        $data['download']['state'] = 123;
        return $data;
    }
)->getDownload();
p($result,true);
```
*10. 上传文件*
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
*11. 过程干预*
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
*12. 一个缓存例子*
```php
$param = [
    'redis' => [
        'host'     => '127.0.0.1',
        // 获取今天剩余时间(秒)
        'expire'   => 86400-(time()+8*3600)%86400,
        // 'decache' => true,
        'callback' => function($data) {
            // 先判断数据是否是正常的，不是想要的就不缓存
            if( strlen((string)$data['body']) != 64){
                // 不缓存
                return false;
            } else {
                // 缓存
                return true;
            }
        },
    ],

    'url'  => 'http://demo.com/v1/login/',
    'data' => 'user=123&password=123',
];
$token = Whttp::get($param['url'], $param['data'])
            ->cache($param['redis'])
            ->core('token":"','"')
            ->getBody();
return (string)$token;
```

*13. 更多说明
```php
/**
 * 请求模式参数
 * @var array
 */
private static $method = [
    'GET'     => ['string|array', 'string|array'],
    'POST'    => ['string|array', 'string|array'],
    'PUT'     => ['string|array', 'string|array'],
    'PATCH'   => ['string|array', 'string|array'],
    'DELETE'  => ['string|array', 'string|array'],
];

/**
 * 设置参数列表1
 * @var array
 */
protected static $setlist1 = [
    'jump'      => ['boolean|NULL'],       
    // 跳过重定向(默认会跳过重定向)(可空)

    'header'    => ['array'],        
    // 请求协议头

    'cookie'    => ['string'],   
    // 请求cookie

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
    
    'cache'     => ['array|NULL'],
    /* 默认缓存配置(Redis)(为空时就使用下面默认配置)
    $default = [
        'host'    => '127.0.0.1',  // Redis连接IP
        'pass'    => '',           // 密码
        'expire'  => 60,           // 默认缓存到期时间
        'decache' => false,        // 删除缓存并且重新请求
        'cacheid' => '',           // 设置缓存id,不设置默认id
        // 允许失败或超时请求次数
        // 意思就是请求3次都错误就请求返回空，
        // 避免服务器高请求导致大量占用资源卡死
        'count'   => 3,  // 默认0不限制
        // 超时请求高于次数设置的缓存时间（秒）
        // 意思就是上面的错误次数后,服务器返回空的时间
        'overtimedue' => 60,
    ];
    */

    'writefunc' => ['callable'],    
    // 回调方法,可以干预实时获取的内容,有2个参数 function($ch,$exec){}
    
    'savepath'  => ['string'],
    // 下载保存的路径
    
    'savename'  => ['string'],
    // 下载保存文件名称(批量下载无效)(默认下载地址获取文件名称)
    
    'concurrent' => ['integer|NULL'],
    // 设置并发数量限制(默认为10)

    'gany'       => ['object'],
    // 回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁 function($data){}
];

// 返回方法说明
/**
 * 获取响应状态码(不支持并发)
 * @return int 状态码
 */
// public function getCode();

/**
 * 获取响应头部(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return string       
 */
// public function getHeaders (string $name="");

/**
 * 获取响应内容(不支持并发)
 * @return data
 */
// public function getBody();

/**
 * 获取请求信息(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array
 */
// public function getInfo(string $name="");

/**
 * 获取错误信息(不支持并发)
 * @return string
 */
// public function getError();

/**
 * 以数组形式返回(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array       
 */
// public function getJson(string $name="");

/**
 * 获取到全部信息(不支持并发)
 * @param  string $name 名称(.号分割)
 * @return array
 */
// public function getAll(string $name="");

/**
 * 下载文件(批量下载无法显示进度)
 * @Author   laoge
 * @DateTime 2021-03-23
 * @param    callable   $callback  回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
 * @return   array                 
 */
// public function getDownload(callable $callback=null)

```