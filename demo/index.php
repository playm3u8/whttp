<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

$url = "https://git.mvgao.com/get.php";

$result = Whttp::get($url,"r=2")->jump(true)->timeout(4,5)->cache('127.0.0.1','',30,3,10);

p($result->getInfo());
