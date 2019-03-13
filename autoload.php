<?php

// 非框架调用会自动加载
spl_autoload_register(function ($class) {
    $string = explode("\\", $class);
    require_once pathinfo(__FILE__,PATHINFO_DIRNAME).'/src/'.$string[1].'.php';
});
