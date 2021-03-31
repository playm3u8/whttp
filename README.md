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
    ->iscommand()
    ->savepath($path)
    ->concurrent(10)
    ->getDownload(Function($data) {
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
);
p($result,true);
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