<?php
// +----------------------------------------------------------------------
// | 非阻塞并发HTTP请求类(采集爬虫专用)
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: laoge <3385168058@qq.com>
// +----------------------------------------------------------------------
namespace PL;
use Exception;

/**
 * HTTP请求类库
 */
class Whttp extends WhttpClass
{
    /**
     * 请求模式参数
     * @var array
     */
    private static $method = array(
        'GET',        // get(string|array)
        'POST',       // post(string|array, string|array)
        'PUT',        // put(string|array, string|array)
        'PATCH',      // patch(string|array, string|array)
        'DELETE',     // delete(string|array, string|array)
    );

    /**
     * 设置参数列表1
     * @var array
     */
    protected static $setlist1 = array(
        'jump',       // (bool)          跳过重定向(默认会跳过重定向)
        'header',     // (array)         请求协议头
        'cookie',     // (string)        请求cookie
        'timeout',    // (float)         请求超时时间(秒)
        'ctimeout',   // (float)         连接超时时间(秒)
        'nobody',     // (bool)          不要body 只返回响应头信息(默认要body)(超快)
        'referer',    // (string)        伪装请求来路
        'proxy',      // (string)        代理IP
        'fool',       // (string)        伪装用户IP，有些无效
        'utf8',       // (bool)          解码UTF8响应内容(在返回内容乱码的情况下使用) 
        'left',       // (string)        截取返回Body指定左边字符
        'core',       // (string,string) 截取返回Body指定中间字符
        'right',      // (string)        截取返回Body指定右边字符
        'cache',      // (integer,object)缓存时间(秒)默认是File方式缓存|第二个参数可空,是可以传入框架缓存驱动(thinkphp)
        'writefunc',  // (callable)      回调方法,可以干预实时获取的内容,有2个参数 function($ch,$exec){}
    );

    // 返回方法说明
    /**
     * 获取响应状态码(不支持并发)
     * @return int 状态码
     */
    // public function getCode();

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string       
     */
    // public function getHeaders (string $name="");

    /**
     * 获取响应内容(不支持并发)
     * @return data
     */
    // public function getBody();

    /**
     * 获取请求信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    // public function getInfo(string $name="");

    /**
     * 获取错误信息(不支持并发)
     * @return string
     */
    // public function getError();

    /**
     * 以数组形式返回(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array       
     */
    // public function getJson(string $name="");

    /**
     * 获取到全部信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    // public function getAll(string $name="");

    /**
     * 执行多任务并发
     * 回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
     * @param  callable $callback 回调函数，有1个参数 function($data){}
     */
    // public function getGany(callable $callback);

    /**
     * 下载文件(支持批量)
     * @param  string $name 文件名称,为空自动更具URL识别文件名
     * @param  string $path 保存目录
     * @return string       
     */
    // public function getDownload($name=null, $path=null);

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @param  string $func      方法名
     * @param  array  $params    参数
     * @return mixed           
     */
    public static function __callStatic($func, $params)
    {
        if (isset($func)) {
            $func = strtoupper($func);
            if (in_array($func, self::$method)) {
                $class = new self();
                if(count($params) == 1) {
                    return $class->method($func)->url($params[0]);
                } else if (count($params) == 2) {
                    return $class->method($func)->url($params[0])->data($params[1]);
                } else {
                    throw new Exception("$func 参数有误");
                }
            } else {
                throw new Exception("$func 好像没有这个方法");
            }
        }
    }
}