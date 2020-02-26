<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

// echo gettype(strpos("1234567", "1"));

// exit;
$url = "https://git.mvgao.com/get.php";

// 原始保存路径
$filePh = './file/image';

$result = get($url,"r=2");

$result = $result->jump();
// 下载图片文件
$result = $result->timeout(4);

// 缓存下载的图片
// $result = $result->cache('127.0.0.1','',30,3,10);

// 下载图片文件
$result = $result->getAll();

p($result);
