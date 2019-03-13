<?php
/**
 * 非框架调用自动加载
 */

// 当前路径
$Path = pathinfo(__FILE__,PATHINFO_DIRNAME);

// 函数助手
require_once $Path.'/src/helper.php';

// 自动注册
spl_autoload_register(function ($class) use ($Path){
    $string = explode("\\", $class);
    require_once $Path.'/src/'.$string[1].'.php';
});
