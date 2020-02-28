<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

$url = "https://git.mvgao.com/get.php";

$result = Whttp::get([$url,$url],"qwq=ww");

p($result->getAll());
