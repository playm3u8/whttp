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
spl_autoload_register(function ($class) {
    $string = explode("\\", $class);
    require_once pathinfo(__FILE__,PATHINFO_DIRNAME).'/'.$string[1].'.php';
});

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
        'cache',      // (integer)       设置缓存时间(秒)采用的是File方式缓存
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

    /**
     * 取字符串左边
     * @param  string $str       被找字符
     * @param  string $right_str 右边字符
     * @return string            结果字符
     */
    public static function _left($str, $right_str)
    {
        $pos = strpos( $str, $right_str);
        if($pos === false){
            return null;
        }
        if (!$string = substr($str, 0, $pos)){
            return null;
        }else{
            return $string;
        }
    }

    /**
     * 取字符串中间
     * @param  string $str      被找字符
     * @param  string $leftStr  左边字符串
     * @param  string $rightStr 右边字符串
     * @return string           结果字符
     */
    public static function _core($str, $leftStr, $rightStr)
    {
        $left = strpos($str, $leftStr);
        if ($left === false) {
            return null;
        }
        $right = strpos($str, $rightStr, $left + strlen($leftStr));
        if ($left === false or $right === false) {
            return null;
        }
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
    }
    
    /**
     * 取字符串右边
     * @param  string $str      被找字符
     * @param  string $left_str 左边字符
     * @return string           结果字符
     */
    public function _right($str, $left_str)
    {
        $pos = strrpos($str, $left_str);
        if($pos === false){
            return null;
        }else{
            return substr($str, $pos + strlen($left_str));
        }
    }

    /**
     * 一维数组合并更新
     * @param  array $array1 被更新数组
     * @param  array $array2 新数组
     * @return array
     */
    public static function arrUp($array, $array2)
    {
        $arr = array();
        if(!$array2) return $array;
        if (gettype($array2) == 'array') {
            $arr = $array2;
        } elseif (gettype($array2) == 'string') {
            $arr[0] = $array2;
        }
        foreach ($arr as $v) {
            $a = explode(':', $v);
            $i = 0;
            foreach ($array as $va) {
                $b = explode(':', $va);
                if ($b[0] == $a[0]) {
                    $array[$i] = $v;
                    break;
                }
                if (count($array) == $i + 1) {
                    $array[$i + 1] = $v;
                }
                $i++;
            }
        }
        return $array;
    }

    /**
     * 方便快速获取二维数组
     * @param  array  $data    数据
     * @param  string $strName 配置参数名（.号分割）
     * @return array|string       
     */
    public static function fast($data, string $strName="") 
    {
        $strArray = "";
        if (substr($strName, -1) == '.') {
            $strName = substr($strName, 0, -1);
        }
        if(empty($strName)) return $data;
        $strName  = explode('.', $strName);
        for ($i=0; $i < count($strName); $i++) 
        { 
            $strArray .= '[$strName['.$i.']]';
        }
        eval('$empty = empty($data'.$strArray.');');
        if($empty) return Null;
        eval('$data  = $data'.$strArray.';');
        return $data;
    }

    /**
     * 下载文件
     * @param  string $name 文件名称
     * @param  string $path 保存目录
     * @return string       
     */
    public static function download($body, $name, $path=null)
    {
        if (empty($name) || !$body) return Null;

        if (empty($path)) $path = __DIR__.'/../runtime/file/';

        // 创建目录
        if(!file_exists($path)) mkdir ($path, 0777, true);
        // 打开文件
        if(!$fp = fopen($path.$name, "w")) {
            throw new Exception("Unable to open file!");
        }
        // 写入文件
        fwrite($fp, $body);
        // 关闭文件
        fclose($fp);
        if(!file_exists($path.$name)) return Null;
        return $path.$name;
    }

    /**
     * 代理下载输出给用户
     * @param  string $url  下载地址(URL)
     * @param  string $name 下载文件名称
     * @return bool
     */
    public static function proxyDownload($url, $name=null) 
    {
        if (empty($url)) return null;
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['user-agent: '.$_SERVER['HTTP_USER_AGENT']]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 10485760);
        $flag = 0;
        if (empty($name)) {
            $name = pathinfo(parse_url($url ,PHP_URL_PATH),PATHINFO_BASENAME);
            if(empty($name)) {
                // 获取失败直接用host命名
                $name = parse_url($url, PHP_URL_HOST);
            }
        }
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch ,$str) use (&$flag, $name)
        {
            $flag++;
            $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if($flag==1){
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                header("HTTP/1.1 ".$code);
                header("Content-Type: ".$type);
                header("Content-Length: ".$size);
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control:max-age=2592000');
                header('Content-Disposition: attachment; filename="'.$name.'"');
            }
            echo $str;
            return strlen($str);
        });
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * 调试格式化输出
     * @param  array   $array 打印数组
     * @param  boolean $die   是否运行停止
     * @return null
     */
    public static function p($array,$die=false)
    {
        echo "<pre>".PHP_EOL;
        print_r($array);
        echo "</pre>";
        if($die) die;
    }

}