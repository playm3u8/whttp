<?php
ini_set("html_errors","On");
ini_set("display_errors","On");
require_once dirname(__DIR__)."/autoload.php";

use PL\Whttp;

$http = Whttp::get('https://www.baidu.com')->cache(20)->nobody(true);
if ($http->getError()) {
	$http = "error: ".$http->getError();
} else {
	$http = $http->getHeaders();
}
p($http);