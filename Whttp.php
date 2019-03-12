<?php
// +----------------------------------------------------------------------
// | 非阻塞并发HTTP请求类(采集爬虫专用)
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: laoge <3385168058@qq.com>
// +----------------------------------------------------------------------
namespace playm3u8;
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

        if (empty($path)) $path = __DIR__.'/runtime/file/';

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

/**
 * HTTP请求类库
 */
class WhttpClass
{
    /**
     * 请求响应数据
     * @var array
     */
    private $data;

    /**
     * 设置方法
     * @var array
     */
    private $method;

    /**
     * 回调指针
     * @var callable
     */
    private $callback;

    /**
     * 设置参数列表2(隐藏的)
     * @var array
     */
    private $setlist2 = array(
        'url',        // (string|array) 请求地址
        'data',       // (string|array) 提交数据支持文本和数组
        'method',     // (string)       请求类型
    );

    /**
     * 干预获取过程储存的数据
     * @var array
     */
    private $exec;

    /**
     * 下载创建的临时文件
     * @var array
     */
    private $fptmp;

    private $call_return = Null;

    /**
     * 默认请求头
     * @var array
     */
    private $default_header = array('Accept-Encoding: gzip');

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $func 方法名
     * @param array  $params 参数
     * @return mixed
     */
    public function __call($func, $params)
    {
        if (isset($func)) {
            if (in_array($func, Whttp::$setlist1) || in_array($func, $this->setlist2)) {
                if (count($params) == 2 && !is_null($params[1])) {
                    $this->method[strtolower($func)] = array($params[0], $params[1]);
                } else {
                    $this->method[strtolower($func)] = empty($params[0])? false : $params[0];
                }
            } else {
                throw new Exception("$func 好像没有这个方法");
            }
        }
        return $this;
    }

    /**
     * 获取响应状态码(不支持并发)
     * @return int 状态码
     */
    public function getCode() 
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['headers']) return Null;
        return Whttp::fast($return, "headers.State.StatusCode");
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string       
     */
    public function getHeaders (string $name="") 
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['headers']) return Null;
        return Whttp::fast($return, "headers.".$name);
    }

    /**
     * 获取响应内容(不支持并发)
     * @return data
     */
    public function getBody() 
    { 
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['body']) return Null;
        return Whttp::fast($return, "body");
    }

    /**
     * 获取请求信息(不支持并发)
     * @return array
     */
    public function getInfo(string $name="") 
    { 
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['info']) return Null;
        return Whttp::fast($return, "info.".$name);
    }

    /**
     * 获取错误信息(不支持并发)
     * @return string
     */
    public function getError() 
    { 
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if($return['body']) return Null;
        return Whttp::fast($return, "error");
    }

    /**
     * 以数组形式返回(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array       
     */
    public function getJson(string $name="")
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['body']) return Null;
        // 编码JSON
        $data = json_decode(trim($return['body'], chr(239) . chr(187) . chr(191)), true);
        if(empty($name)){
            return $data;
        }
        return Whttp::fast($data, $name);
    }

    /**
     * 获取到全部信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    public function getAll(string $name="")
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return) return Null;
        // 编码JSON
        return Whttp::fast($return, $name);
    }

    /**
     * 执行多任务并发
     * 回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
     * @param  callable $callback 回调函数
     */
    public function getGany(callable $callback) 
    { 
        $this->callback = $callback;
        // 处理配置信息
        $options = $this->config($this->method);
        if (count($options) == 1) {
            throw new Exception("不支持单URL请求");
        }
        return $this->send($options); 
    }

    /**
     * 下载文件(支持批量)
     * @param  string $name 文件名称,为空自动更具URL识别文件名
     * @param  string $path 保存目录
     * @return string       
     */
    public function getDownload($name=null, $path=null)
    {
        $this->method['fp_name'] = $name;
        $this->method['fp_path'] = empty($path)? __DIR__.'/runtime/file/' : $path;
        // 发送请求
        $return = $this->send($this->config($this->method));
        return $return;
    }

    /**
     * 发送请求
     * @return array 响应结果
     */
    private function send($options=null) 
    {
        // 处理配置信息
        $options = ($options)? $options : $this->config($this->method);
        if (count($options) == 1) {
            // 单一请求
            return $this->single($options);
        } elseif (count($options) > 1) {
            // 批量请求
            return $this->multi($options);
        }
    }

    /**
     * 单地址请求
     * @param  array $options 请求配置
     * @return array          响应结果
     */
    private function single($options) 
    {
        $cacid = Null;
        $cache = new Cache();
        // 数据存在就直接返回
        if($this->data) return $this->data;
        // 缓存ID标示
        $cacid = $this->getCacheID($options[0]);
        // 判断是否存在
        if ($cache->has($cacid)) 
        {
            // 获取缓存解压数据
            $this->data = unserialize(gzinflate($cache->get($cacid)));
            return $this->data;
        }
        // 初始化
        $ch = curl_init();
        // 临时文件
        if (!empty($this->method['fp_path']))
        {
            $this->fptmp[(string)$ch] = tmpfile();
        }
        curl_setopt_array($ch, $options[0]);
        // 发送请求
        $this->data['exec'] = curl_exec($ch);
        // 获取响应全部信息，包括有请求头和内容
        if (!empty($this->exec[(string)$ch])) {
            // 获取截取数据
            $this->data['exec'] = $this->exec[(string)$ch];
        }
        // 获取请求返回详细数据
        $this->data['info']    = curl_getinfo($ch);
        // 获取错误信息
        $this->data['error']   = empty($this->data['exec'])? curl_error($ch) : Null;
        // 处理响应数据
        $dataExec = $this->getExec($this->data['exec'], $this->data['info'], $options[0], $ch);
        // 获取响应头部
        $this->data['headers']  = $dataExec['headers'];
        // 获取响应内容
        $this->data['body']     = $dataExec['body'];
        // 下载信息
        $this->data['download'] = $dataExec['download'];
        // 销毁
        curl_close($ch);
        // 删除无用数据
        unset($this->data['exec']);
        // 缓存写入处理
        if (!empty($this->method['cache'])) {
            if (gettype($this->method['cache']) != 'integer') {
                throw new Exception("缓存时间设置有误");
            }
            // 判断是否存在
            if (!$cache->has($cacid)) 
            {
                // 压缩写入缓存
                if (empty($this->data['errer']) && $this->data['headers']) {
                    $cache->set($cacid, gzdeflate(serialize($this->data)), $this->method['cache']);
                }
            }
        }
        return $this->data;
    }

    /**
     * 并发请求
     * @param  array $options 配置
     * @return array          
     */
    private function multi($options) 
    {
        $op = array();
        // 初始化(并发)
        $mh = curl_multi_init();
        // 批量设置
        foreach ($options as $value) {
            // 初始化
            $ch = curl_init();
            // 记录 options
            $op[(string)$ch] = $value;
            // 设置 options
            curl_setopt_array($ch, $value);
            // 添加到并发处理
            curl_multi_add_handle($mh, $ch);
        }
        // 并发处理
        $return   = array();
        $id       = 0;
        $active   = Null;
        $callData = Null;
        do {
            while (($code = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);
            if ($code != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($mh)) {
                // 获取请求句柄
                $ch                   = $done['handle'];
                // 获取错误信息
                $error                = curl_error($ch);
                // 获取请求返回详细数据
                $info                 = curl_getinfo($ch);
                // 提取出请求host
                $host                 = parse_url($info['url'], PHP_URL_HOST);
                // 获取响应全部信息，包括有请求头和内容
                if (!empty($this->exec[(string)$ch])) {
                    // 获取截取数据
                    $exec         = $this->exec[(string)$ch];
                } else {
                    $exec         = curl_multi_getcontent($ch);
                }
                // 请求句柄
                $return[$id]['id']    = (int) $ch;
                $return[$id]['host']  = $host;
                $return[$id]['info']  = $info;
                $return[$id]['error'] = empty($exec)? curl_error($ch) : Null;
                // 处理响应数据
                $dataExec = $this->getExec($exec, $info, $op[(string)$ch], $ch);
                // 获取响应头部
                $return[$id]['headers']  = $dataExec['headers'];
                // 获取响应内容
                $return[$id]['body']     = $dataExec['body'];
                // 下载信息
                $return[$id]['download'] = $dataExec['download'];
                // 回调处理方式
                if (is_callable($this->callback)) {
                    // 请求句柄
                    $func['id']      = (int) $ch;
                    // 请求host
                    $func['host']    = $return[$id]['host'];
                    $func['info']    = $return[$id]['info'];
                    $func['error']   = $return[$id]['error'];
                    $func['headers'] = $return[$id]['headers'];
                    $func['body']    = $return[$id]['body'];
                    $call_return = call_user_func_array($this->callback, array($func));
                    if ($call_return) {
                        $this->call_return[] = $call_return;
                    }
                } 
                // 计次
                $id++;
                // 销毁处理
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            if ($active) {
                // 等待响应的时间(可调，具体善待验证)
                // 阻塞直到cURL批处理连接中有活动连接
                // 注：等待时间为0.5秒，请求响应时间也会增加0.5秒的耗时
                curl_multi_select($mh,0.5);
            }
        } while ($active);
        // 销毁
        curl_multi_close($mh);
        // 返回数据
        return ($this->call_return)? $this->call_return : $return;
    }  

    /**
     * 接收回调响应内容，可以用来响应数据进行分析
     * @param  [type] $ch   [description]
     * @param  [type] $exec [description]
     * @return int
     */
    private function receiveResponse($ch, $exec)
    {
        // 留给外部处理
        if (is_callable($this->method['writefunc'])) 
        {
            $result = call_user_func_array($this->method['writefunc'], array($ch, $exec));
            if ($result == false) {
                return $result;
            }
        }

        // 以下是截取全部
        if (empty($this->exec[(string)$ch])) {
            $this->exec[(string)$ch] = $exec;
        } else {
            $this->exec[(string)$ch] .= $exec;
        }

        // 返回当前接受数据大小
        return strlen($exec);
    }

    /**
     * 下载回调
     * @param  [type] $ch   [description]
     * @param  [type] $exec [description]
     * @return int      
     */
    private function receiveDownload($ch, $exec)
    {
        // 开始下载
        if (!empty($this->fptmp[(string)$ch])) {
            return fwrite($this->fptmp[(string)$ch], $exec);
        } else {
            // 断开连接
            return 0;
        }
    }

    /**
     * 配置请求
     * @param  array $out 请求设置
     * @return array      配置
     */
    private function config($out) 
    {
        // 获取用户浏览器标示
        $User_Agent = array(
            'User-Agent: '.$_SERVER['HTTP_USER_AGENT']
        );
        // 合并请求头
        $this->default_header = Whttp::arrUp($this->default_header, $User_Agent);
        // 处理多批量URL
        if (!$out) return array();
        if (gettype($out['url']) == 'array') {
            $urls    = $out['url'];
        } elseif (gettype($out['url']) == 'string') {
            $urls[0] = $out['url'];
        } else {
            return array();
        }
        foreach ($urls as $id => $url) {
            // 默认值
            $options = array( 
                // 请求地址
                CURLOPT_URL            => $url,
                // 设置cURL允许执行的最长毫秒数
                CURLOPT_NOSIGNAL       => true,
                // 默认2000毫秒
                CURLOPT_TIMEOUT_MS     => 2000,
                // 尝试连接等待的时间，以毫秒为单位。设置为0，则无限等待
                CURLOPT_CONNECTTIMEOUT_MS => 2000, // 默认500毫秒
                // 设置需要返回请求头信息
                CURLOPT_HEADER         => empty($out['fp_path'])? true : false,
                // 设置已文本流方式返回,反则就会直接输出至浏览器显示了
                CURLOPT_RETURNTRANSFER => true,
                // 设置要请求头和内容一起返回，反则只会返回请求头，这样好处就是请求很快得到请求头的信息
                CURLOPT_NOBODY         => empty($out['nobody'])? false : true,
                // 设置请求方式'GET,POST','PUT','PATCH','DELETE'
                CURLOPT_CUSTOMREQUEST  => $out['method'],
                // 默认重定向直接跳过
                CURLOPT_FOLLOWLOCATION => array_key_exists('jump', $out)? $out['jump']:true,
                // 指定最多的 HTTP 重定向次数
                CURLOPT_MAXREDIRS      => 4,
                // 获取远程文档中的修改时间信息
                CURLOPT_FILETIME       => true,
                // 根据 Location: 重定向时，自动设置 header 中的Referer:信息
                CURLOPT_AUTOREFERER    => true,
                // 默认请求来路
                CURLOPT_REFERER        => empty($out['referer'])? $url : $out['referer'],
                // 默认请求头
                CURLOPT_HTTPHEADER     => Whttp::arrUp($this->default_header, empty($out['header'])? array():$out['header']),
            );
            // 处理GET地址
            if ($out['method'] == 'GET') {
                if (isset($out['data'])) {
                    unset($out['data']);
                }
            }
            // 回调处理事件, 开启了exec就不输出了
            if (array_key_exists('writefunc', $out)) {
                if (is_callable($out['writefunc'])) {
                    $options[CURLOPT_WRITEFUNCTION] = array($this, 'parent::receiveResponse');
                }
            }
            // 下载处理(单URL请求)
            if (count($urls) == 1) {
                if (!empty($out['fp_path'])) {
                    $options[CURLOPT_WRITEFUNCTION] = array($this, 'parent::receiveDownload');
                }
            }
            // 处理提交数据
            if (in_array($out['method'], array('POST','PUT','PATCH','DELETE'))) {
                // 设置请求类型
                if ($out['data']) {
                    $options[CURLOPT_POST]       = true;
                    $options[CURLOPT_POSTFIELDS] = $out['data'];
                }
            }
            // SSL设置
            if (parse_url($url, PHP_URL_SCHEME) == "https") {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = false;
            }
            // 设置Cookie
            if(!empty($out['cookie'])) $options[CURLOPT_COOKIE] = $out['cookie'];
            // 设置请求超时
            if(!empty($out['timeout'])) $options[CURLOPT_TIMEOUT_MS] = round($out['timeout']*1000,0);
            // 设置连接超时
            if(!empty($out['ctimeout'])) $options[CURLOPT_CONNECTTIMEOUT_MS] = round($out['ctimeout']*1000,0);
            // 设置代理
            if (empty($out['proxy']) == false) 
            {
                $options[CURLOPT_PROXY] = $out['proxy'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            }
            // 设置伪代理
            if (empty($out['fool']) == false) 
            {
                if (gettype($out['fool']) == 'array') {
                    $string = implode(",", $out['fool']);
                } else if (gettype($out['fool']) == 'string') {
                    $string = $out['fool'];
                } else {
                    throw new Exception("伪装IP不能为空哦");
                }
                $options[CURLOPT_HTTPHEADER] = Whttp::arrUp($options[CURLOPT_HTTPHEADER],array(
                    'Client-IP: ' . $string,
                    'X-Forwarded-For: ' . $string
                ));
            }
            $header_STR = strtolower(implode(PHP_EOL, $options[CURLOPT_HTTPHEADER]));
            if (strstr($header_STR, 'gzip') && strstr($header_STR, 'accept-encoding')) {
                // 找到请求头有gzip标示请求就采用gzip解码
                $options[CURLOPT_ENCODING] = 'gzip';              
            }
            $return[$id] = $options;
        }
        return $return;
    }

    /**
     * 内部处理响应exec数据
     * @param  string $exec    响应数据
     * @param  array  $info    请求信息
     * @param  array  $options 配置信息
     * @param  resource $ch    请求句柄
     * @return array
     */
    private function getExec($exec, $info, $options, $ch)
    {
        $data = array(
            'body'     => null,
            'headers'  => null,
            'download' => array( 'state' => false, 'path' => null),
        );
        //  无数据处理
        if (!$exec) {
            if(!empty($this->method['fp_path'])) {
                // 下载失败关闭临时文件
                fclose($this->fptmp[(string)$ch]);
            }
            return $data; 
        } 
        // 无响应头请求
        if($options[CURLOPT_HEADER] == false) {
            // 直接输出
            $data['body'] = $exec;

            // 文件下载
            if (!empty($this->method['fp_path'])) {

                // 保存到指定位置
                $name = $this->method['fp_name'];
                if (empty($name)) 
                {
                    $name = pathinfo(parse_url($info['url'] ,PHP_URL_PATH),PATHINFO_BASENAME);

                    if(empty($name)) {
                        // 获取失败直接关闭临时文件
                        $name = parse_url($info['url'], PHP_URL_HOST);
                    }
                }
                // 保存文件到指定位置
                if(!file_exists($this->method['fp_path'])) 
                {
                    // 目录不存在直接创建
                    mkdir ($this->method['fp_path'], 0777, true);
                }

                if (gettype($data['body']) == 'boolean') {

                    if(!$fp = fopen($this->method['fp_path'].$name, "w")) {

                        fclose($this->fptmp[(string)$ch]);
                        // 输出下载信息
                        $data['download']['state'] = false;
                        $data['download']['path']  = "写入失败";
                    } else {
                        fseek($this->fptmp[(string)$ch], 0);
                        $download_length = $this->pipe_streams($this->fptmp[(string)$ch], $fp);
                        fclose($fp);
                        // 关闭临时文件会直接找到删除
                        fclose($this->fptmp[(string)$ch]);
                        // 效验下载大小，输出下载信息
                        if ($download_length != $info['size_download']) {

                            $data['download']['state'] = false;
                            $data['download']['path']  = "下载失败";

                        } else {
                            $data['download']['state'] = true;
                            $data['download']['path']  = $this->method['fp_path'].$name;
                        }
                    }

                } else {

                    if (!$down = Whttp::download($data['body'],$name,$this->method['fp_path'])) 
                    {
                        $data['download']['state'] = false;
                        $data['download']['path']  = "批量下载失败";
                    } else {

                        $data['download']['state'] = true;
                        $data['download']['path'] = $this->method['fp_path'].$name;
                    }

                    // 删除body数据
                    $data['body'] = true;
                }
            }

        } else {
            // 得到响应内容
            $data['body'] = substr($exec, $info['header_size']);
            // 得到响应头内容
            $headerStr    = substr($exec, 0, $info['header_size'] - 4);
            // 处理多响应头
            $headerStr    = explode("\r\n"."\r\n", $headerStr);
            // 取最后一个响应头
            $data['headers'] = $this->format_header(end($headerStr));
            // 把父请求头取出来,有先到后
            if (count($headerStr) > 1) {
                for ($i=0; $i < count($headerStr)-1; $i++) { 
                    $data['headers']['Father'][$i] = $this->format_header($headerStr[$i]);
                }
            }
        }
        // 过滤字符串
        if (!empty($data['body'])) {
            if (isset($this->method['utf8']) && $this->method['utf8'] == true) {
                $data['body'] = mb_convert_encoding($data['body'], 'utf-8', 'GBK,UTF-8,ASCII');
            }
            // 过滤字符
            if (!empty($this->method['right'])) {
                // 取右
                $data['body'] = Whttp::_right($data['body'], $this->method['right']);
            } elseif (!empty($this->method['left'])) {
                // 取左
                $data['body'] = Whttp::_left($data['body'], $this->method['left']);
            } elseif (!empty($this->method['core'])) {
                // 取中
                $data['body'] = Whttp::_core($data['body'], $this->method['core'][0], $this->method['core'][1]);
            }
        }
        return $data;
    }

    /**
     * 复制流文件(不占内存)
     * @param  [type] $in  [description]
     * @param  [type] $out [description]
     * @return [type]      [description]
     */
    private function pipe_streams($in, $out)
    {
        $size = 0;
        while (!feof($in)) $size += fwrite($out,fread($in,8192));
        return $size;
    }

    /**
     * 处理缓存标识
     * @param  array $options 请求配置
     * @return string     
     */
    private function getCacheID($options)
    {
        unset($options[CURLOPT_WRITEFUNCTION]);
        return md5(serialize($options));
    }

   /**
     * 格式化响应头部
     * @param  string $value 协议头
     * @return array        
     */
    private function format_header(string $value) 
    {
        $array  = array();
        // 分割成数组
        $header = explode(PHP_EOL, $value);
        if (strstr($value, 'Set-Cookie')) $array['Set-Cookie'] = Null;
        // 把多行响应头信息转为组数数据
        foreach ($header as $value) {
            // 从左查找":"的位置
            $wz = strpos($value, ":");
            if ($wz !== false) {
                // 取出返回请求头名称
                $cName = substr($value, 0, $wz);
                // 整理多行Cookie数据
                if ($cName == "Set-Cookie") {
                    // 获取Cookie值全部,里面会包含一些无用的信息需要去除掉
                    $cName_value = substr($value, $wz + 2);
                    if (strpos($cName_value, ';') === false) {
                        // 如果没有无用的信息就直接此行提取全部值
                        $array[$cName] .= $cName_value . "; ";
                    } else {
                        // 只取出";"最前面的数据，后面的不要
                        $array[$cName] .= substr($cName_value, 0, strpos($cName_value, ';')) . "; ";
                    }
                } else {
                    // 处理其他返回请求头数据
                    $array[$cName] = substr($value, $wz + 2);
                }
            } else {
                // 处理状态
                if(preg_match_all('/(\d{1,2}\.\d{1,2})\s+(\d{3})\s+(.*)/', $value, $matches)){
                    $array['State']['ProtocolVersion'] = $matches[1][0];
                    $array['State']['StatusCode']      = $matches[2][0];
                    $array['State']['ReasonPhrase']    = $matches[3][0];
                }
            }
        }
        // 把Cookie移动到最后，强迫症需理解
        if (array_key_exists('Set-Cookie', $array)) {
            $uArray = $array['Set-Cookie'];
            unset($array['Set-Cookie']);
            $array['Cookies'] = $uArray;
        }
        return $array;
    }

    /**
     * 销毁处理
     */
    public function __destruct()
    {
        unset($this->method, $this->data, $this->exec, $this->fptmp, $this->call_return);
    }
}

/**
 * 文件类型缓存类
 * @author    liu21st <liu21st@gmail.com>
 */
class Cache
{
    protected $options = array(
        'expire'        => 60,
        'cache_subdir'  => true,
        'prefix'        => '',
        'path'          => __DIR__.'/runtime/cache/',
        'data_compress' => false,
    );

    protected $expire;

    protected $tag;

    /**
     * 构造函数
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        if (substr($this->options['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->options['path'] .= DIRECTORY_SEPARATOR;
        }
        $this->init();
    }

    /**
     * 初始化检查
     * @access private
     * @return boolean
     */
    private function init()
    {
        // 创建项目缓存目录
        if (!is_dir($this->options['path'])) {
            if (mkdir($this->options['path'], 0755, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 取得变量的存储文件名
     * @access protected
     * @param  string $name 缓存变量名
     * @param  bool   $auto 是否自动创建目录
     * @return string
     */
    protected function getCacheKey($name, $auto = false)
    {
        $name = md5($name);
        if ($this->options['cache_subdir']) {
            // 使用子目录
            $name = substr($name, 0, 2) . DIRECTORY_SEPARATOR . substr($name, 2);
        }
        if ($this->options['prefix']) {
            $name = $this->options['prefix'] . DIRECTORY_SEPARATOR . $name;
        }
        $filename = $this->options['path'] . $name . '.php';
        $dir      = dirname($filename);

        if ($auto && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $filename;
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has($name)
    {
        return $this->get($name) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($name, $default = false)
    {
        $filename = $this->getCacheKey($name);
        if (!is_file($filename)) {
            return $default;
        }
        $content      = file_get_contents($filename);
        $this->expire = null;
        if (false !== $content) {
            $expire = (int) substr($content, 8, 12);
            if (0 != $expire && time() > filemtime($filename) + $expire) {
                return $default;
            }
            $this->expire = $expire;
            $content      = substr($content, 32);
            if ($this->options['data_compress'] && function_exists('gzcompress')) {
                //启用数据压缩
                $content = gzuncompress($content);
            }
            $content = unserialize($content);
            return $content;
        } else {
            return $default;
        }
    }

    /**
     * 写入缓存
     * @access public
     * @param string            $name 缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        $filename = $this->getCacheKey($name, true);
        if ($this->tag && !is_file($filename)) {
            $first = true;
        }
        $data = serialize($value);
        if ($this->options['data_compress'] && function_exists('gzcompress')) {
            //数据压缩
            $data = gzcompress($data, 3);
        }
        $data   = "<?php\n//" . sprintf('%012d', $expire) . "\n exit();?>\n" . $data;
        $result = file_put_contents($filename, $data);
        if ($result) {
            isset($first) && $this->setTagItem($filename);
            clearstatcache();
            return true;
        } else {
            return false;
        }
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string    $name 缓存变量名
     * @param int       $step 步长
     * @return false|int
     */
    public function inc($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) + $step;
            $expire = $this->expire;
        } else {
            $value  = $step;
            $expire = 0;
        }

        return $this->set($name, $value, $expire) ? $value : false;
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string    $name 缓存变量名
     * @param int       $step 步长
     * @return false|int
     */
    public function dec($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) - $step;
            $expire = $this->expire;
        } else {
            $value  = -$step;
            $expire = 0;
        }

        return $this->set($name, $value, $expire) ? $value : false;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $filename = $this->getCacheKey($name);
        try {
            return $this->unlink($filename);
        } catch (\Exception $e) {
        }
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null)
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                $this->unlink($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        $files = (array) glob($this->options['path'] . ($this->options['prefix'] ? $this->options['prefix'] . DIRECTORY_SEPARATOR : '') . '*');
        foreach ($files as $path) {
            if (is_dir($path)) {
                $matches = glob($path . '/*.php');
                if (is_array($matches)) {
                    array_map('unlink', $matches);
                }
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        return true;
    }

    /**
     * 判断文件是否存在后，删除
     * @param $path
     * @return bool
     * @author byron sampson <xiaobo.sun@qq.com>
     * @return boolean
     */
    private function unlink($path)
    {
        return is_file($path) && unlink($path);
    }
}
