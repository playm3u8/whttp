<?php

require_once '../autoload.php';
use PL\Whttp;

$result = Whttp::get('https://openwrt.download/?dir=R21.3.18/packages/aarch64_generic/packages')->jump()->core("perl-net-cidr-lite","all.ipk")->getBody();
p($result);