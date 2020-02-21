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

$http = Whttp::get('http://www.mvgao.com')->timeout(200);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getAll();
}
p($http,true);
