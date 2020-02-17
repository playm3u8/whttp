<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";


$http = get('http://o96.cc:4000/get.php', ['abc'=>'123','eee'=>'34555']);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getBody();
}
p($http, true);


/*
$http = get('https://www.baidu.com')->timeout(200)->nobody(true);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getHeaders();
}
p($http,true);
*/