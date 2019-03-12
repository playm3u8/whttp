<?php
ini_set("html_errors","On");
ini_set("display_errors","On");
require_once(__DIR__.'/../Whttp.php');
use playm3u8\Whttp;


$http = Whttp::get('https://www.baidu.com')->nobody(true);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getHeaders();
}
Whttp::p($http,true);
