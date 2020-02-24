<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

$url = "https://img.m-team.cc/images/2019/07/17/dcecf8520ac05449dde71569455fd590.jpg";

// 原始保存路径
$filePh = './file/image';

$result = get($url);

// 下载图片文件
$result = $result->timeout(3,6);

// 缓存下载的图片
$result = $result->cache(3600);

// 下载图片文件
$result = $result->getDownload(uuid().".jpg", $filePh);

p($result);
