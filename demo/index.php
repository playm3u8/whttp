<?php
// ini_set("html_errors","On");
// ini_set("display_errors","On");
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__)."/autoload.php";
use PL\Whttp;

// $result = Whttp::get("https://www.baidu.com")->getAll();
// p($result);

$client = new Predis\Client();
$client->set('foo', 'bar.......');
$value = $client->get('foo');
echo $value;