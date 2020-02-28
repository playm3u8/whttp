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
    private $setlist2 = [
        'url'    => ['string|array'],  // 请求地址
        'data'   => ['string|array'],  // 提交数据支持文本和数组
        'method' => ['string'],        // 请求类型
    ];

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
    private $default_header = ['Accept-Encoding: gzip'];

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
            $pname   = strtolower($func);
            $setlist = array_merge($this->setlist2, Whttp::$setlist1);
            if (in_array($pname, array_keys($setlist))) {
                if (count($params) == 1) {
                    $array = $params[0];
                } else {
                    for ($i=0; $i < count($params); $i++) {
                        if (is_null($params[$i])) {
                            $array[$i] = null;
                        } else {
                            $array[$i] = $params[$i];
                        }
                    }
                }
                // 参数数量和类型
                $value = $setlist[$pname];
                // URL和DATA参数只约束类型不限制数量
                if ($pname == 'url' || $pname == 'data') goto end;
                // 不为数组的全部为单参数
                $count1 = (gettype($array) != 'array')? 1:count($array);
                // 约束参数数量
                if ($count1 != count($value) && $pname != 'url' && $pname != 'data') {
                    // url参数不限制
                    if ($count1 > count($value)) {
                        $this->Error($pname.':传入的参数太多');
                    }
                    for ($i=0; $i < count($value); $i++) { 
                        if(strpos($value[$i], "NULL") === false) { 
                            // 必要参数
                            if ($i+1 > $count1){
                                $this->Error($pname.':参数'.($i+1).'不能为空');
                            }
                        }
                    }
                }
                // 参数类型约束
                if (gettype($array) != 'array') {
                    // 单个参数的
                    end:
                    if(strpos($value[0], gettype($array)) === false){ 
                        $this->Error($pname.':传入参数类型有误');
                    } 
                } else {
                    // 多个参数的
                    for ($i=0; $i < count($value); $i++) { 
                        if (strpos($value[$i], gettype($array[$i])) === false) {
                            $this->Error($pname.':传入参数'.($i+1).'类型有误');
                        }
                    }
                }
                // 约束重复的参数
                if ($this->method) {
                    if (in_array($pname, array_keys($this->method))) {
                        $this->Error('参数'.$pname.'被重复设置');
                    }
                }
                $this->method[$pname] = $array;
            } else {
                $this->Error('似乎没有'.$pname.'成员');
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
        return fast($return, "headers.State.StatusCode");
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string       
     */
    public function getHeaders ($name="") 
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['headers']) return Null;
        return fast($return, "headers.".$name);
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
        return fast($return, "body");
    }

    /**
     * 获取请求信息(不支持并发)
     * @return array
     */
    public function getInfo($name="") 
    { 
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return['info']) return Null;
        return fast($return, "info.".$name);
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
        return fast($return, "error");
    }

    /**
     * 以数组形式返回(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array       
     */
    public function getJson($name="")
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
        return fast($data, $name);
    }

    /**
     * 获取到全部信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    public function getAll($name="")
    {
        // 发送请求
        $return = $this->send();
        // 没有直接返回
        if(!$return) return Null;
        // 编码JSON
        return fast($return, $name);
    }

    /**
     * 执行多任务并发
     * 回调处理,不是每个请求都很快响应，这里就可以做到谁请求完成了就处理谁
     * @param  callable $callback 回调函数
     */
    public function getGany($callback) 
    { 
        $this->callback = $callback;
        // 处理配置信息
        $options = $this->config($this->method);
        if (count($options) == 1) {
            $this->Error("不支持单个URL请求");
        }
        return $this->send($options); 
    }

    /**
     * 下载文件(支持批量)
     * @param  string $name 文件名称,为空自动更具URL识别文件名
     * @param  string $path 保存目录
     * @return string       
     */
    public function getDownload($name=null, $path)
    {
        $this->method['fp_name'] = $name;
        // 检查保存路径的完整性
        if (empty($path)) {
            $this->Error("保存文件路径不能为空");
        } elseif(substr($path, -1) != "/") {
            $path = $path."/";
        }
        $this->method['fp_path'] = $path;
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
        // 数据存在就直接返回
        if($this->data) return $this->data;
        // 处理配置信息
        $options = ($options)? $options : $this->config($this->method);
        if (count($options) == 1) {
            // 单一请求
            return $this->single($options);
        } elseif (count($options) > 1) {
            // 判断是否调用了getGany方法
            if ($this->callback == Null) {
                $this->Error('请使用 "getGany" 方法');
            }
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

        // 缓存ID标示
        $cacid = $this->getCacheID($options[0]);
        // 识别缓存驱动
        $ReINFO = $this->method['cache'];
        if (!empty($ReINFO)) {
            // 默认Redis配置
            $default = [
                'host'    => '127.0.0.1',
                'pass'    => '',
                'expire'  => 60,
                'count'   => 3,      // 允许超时请求次数
                'overtimedue' => 60, // 超时请求高于次数设置的缓存时间（秒）
            ];
            if (gettype($ReINFO) == 'integer') {
                // 赋值有效期
                $default['expire'] = $ReINFO;

            } elseif(gettype($ReINFO) == 'array'){
                $default['host']        = empty($ReINFO[0])? $default['host']:$ReINFO[0];
                $default['pass']        = empty($ReINFO[1])? $default['pass']:$ReINFO[1];
                $default['expire']      = empty($ReINFO[2])? $default['expire']:$ReINFO[2];
                $default['count']       = empty($ReINFO[3])? $default['count']:$ReINFO[3];
                $default['overtimedue'] = empty($ReINFO[4])? $default['overtimedue']:$ReINFO[4];
            } else {
                $this->Error("缓存配置错误");
            }
            // 实例化Redis
            $predis = new Predis($default);
            // 判断是否存在
            if ($predis->has($cacid)) {
                // 获取缓存解压数据
                $this->data = unserialize(gzinflate($predis->get($cacid)));
                return $this->data;
            }
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
        // 只要不出错，所有数据都加入缓存
        // 连续请求超时次数高于设定次数将进入缓存
        $count         = $default['count'];   // 次
        $overtimedue   = $default['overtimedue']; // 秒
        $curl_error_id = "curl_error_".$cacid;
        // 如果设置了缓存就进入写入处理
        if (!empty($ReINFO)) {
            if (empty($this->data['error']) || $predis->get($curl_error_id) >= $count-1) {
                // 设置了超时次数限制就走限制的缓存时间
                if ($predis->has($curl_error_id)) {
                    // 清除记录
                    $predis->rm($curl_error_id);
                    $default['expire']   = $overtimedue;
                    $this->data['error'] = "Cache: ".$this->data['error'];
                }
                $predis->set($cacid, gzdeflate(serialize($this->data)), $default['expire']);
            } else {
                if (strpos($this->data['error'], "timed out") !== false) {
                    // 请求超时记录一次
                    if ($count > 0){
                        $predis->increment($curl_error_id);
                    }
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
        $op = [];
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
        $return   = [];
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
        if (!empty($_SERVER['HTTP_USER_AGENT'])){
            $User_Agent = ['User-Agent: '.$_SERVER['HTTP_USER_AGENT']];
            // 合并请求头
            $this->default_header = arrUp($this->default_header, $User_Agent);
        }
        // 检查URL请求地址是否为空
        if (gettype($out['url']) == "array") {
            if (empty($out['url'][0])) {
                $this->Error("数组url不能为空");
            }
        } elseif (gettype($out['url']) == "NULL") {
            $this->Error("Url不能为空");
        }
        // 处理多批量URL
        if (!$out) return [];
        if (gettype($out['url']) == 'array') {
            $urls    = $out['url'];
        } elseif (gettype($out['url']) == 'string') {
            $urls[0] = $out['url'];
        } else {
            return [];
        }
        // 批量配置请求INFO
        foreach ($urls as $id => $url) {
            // 处理GET地址
            if ($out['method'] == 'GET') {
                if (isset($out['data'])) {
                    if(is_array($out['data'])){
                        $url = $url."?".merge_string($out['data']);
                    } else {
                        $url = $url."?".$out['data'];
                    }
                    unset($out['data']);
                }
            }
            // 默认值
            $options = [ 
                // 请求地址
                CURLOPT_URL            => $url,
                // 设置cURL允许执行的最长毫秒数
                CURLOPT_NOSIGNAL       => true,
                /** 使用 cURL 下载 MP3 文件是一个对开发人员来说不错的例子，CURLOPT_CONNECTTIMEOUT 可以设置为10秒，标识如果服务器10秒内没有响应，脚本就会断开连接，CURLOPT_TIMEOUT 可以设置为100秒，如果MP3文件100秒内没有下载完成，脚本将会断开连接。
                需要注意的是：CURLOPT_TIMEOUT 默认为0，意思是永远不会断开链接。所以不设置的话，可能因为链接太慢，会把 HTTP 资源用完。
                在 WordPress 中，wp_http 类，这两个值是一样的，默认是设置为 5 秒。 */
                // 请求超时时间
                CURLOPT_TIMEOUT_MS     => defined("CURL_TIMEOUT_MS")? CURL_TIMEOUT_MS:5000,    // 默认5秒,可以通过常量来统一设置默认
                // 尝试连接等待的时间，以毫秒为单位。设置为0，则无限等待
                CURLOPT_CONNECTTIMEOUT_MS => defined("CURL_CONNECTTIMEOUT_MS")? CURL_CONNECTTIMEOUT_MS:5000, // 默认5秒,可以通过常量来统一设置默认
                // 设置需要返回请求头信息
                CURLOPT_HEADER         => empty($out['fp_path'])? true : false,
                // 设置已文本流方式返回,反则就会直接输出至浏览器显示了
                CURLOPT_RETURNTRANSFER => true,
                // 设置请求方式'GET,POST','PUT','PATCH','DELETE'
                CURLOPT_CUSTOMREQUEST  => $out['method'],
                // 指定最多的 HTTP 重定向次数
                CURLOPT_MAXREDIRS      => 4,
                // 获取远程文档中的修改时间信息
                CURLOPT_FILETIME       => true,
                // 根据 Location: 重定向时，自动设置 header 中的Referer:信息
                CURLOPT_AUTOREFERER    => true,
                // 默认请求来路
                CURLOPT_REFERER        => empty($out['referer'])? $url : $out['referer'],
                // 默认请求头
                CURLOPT_HTTPHEADER     => arrUp($this->default_header, empty($out['header'])? []:$out['header']),
            ];
            // 禁止重定向，默认重定向直接跳过
            if(array_key_exists('jump', $out)) {
                if (is_null($out['jump']) || $out['jump']) {
                    $options[CURLOPT_FOLLOWLOCATION] = false;
                } else {
                    $options[CURLOPT_FOLLOWLOCATION] = true;
                }
            } else {
                $options[CURLOPT_FOLLOWLOCATION] = true;
            }
            
            // 设置要请求头和内容一起返回，反则只会返回请求头，这样好处就是请求很快得到请求头的信息
            if(array_key_exists('nobody', $out)) {
                if (is_null($out['nobody']) || $out['nobody']) 
                {
                    $options[CURLOPT_NOBODY] = true;
                }
            }

            // 回调处理事件, 开启了exec就不输出了
            if (array_key_exists('writefunc', $out)) {
                if (is_callable($out['writefunc'])) {
                    $options[CURLOPT_WRITEFUNCTION] = [$this, 'parent::receiveResponse'];
                }
            }
            // 下载处理(单URL请求)
            if (count($urls) == 1) {
                if (!empty($out['fp_path'])) {
                    $options[CURLOPT_WRITEFUNCTION] = [$this, 'parent::receiveDownload'];
                }
            }
            // 处理提交数据
            if (in_array($out['method'], ['POST','PUT','PATCH','DELETE'])) {
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

            // 设置超时时间
            if (array_key_exists('timeoutms', $out) && array_key_exists('timeout', $out)) {
                $this->Error("不能同时设置'请求超时'和'连接超时'");
            }

            if (array_key_exists('timeout', $out)) $out['timeoutms'] = $out['timeout'];
            
            if(array_key_exists('timeoutms', $out)) {
                if (gettype($out['timeoutms']) == 'integer') {
                    // 设置请求超时
                    if (array_key_exists('timeout', $out)){
                        $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms']*1000;
                    } else {
                        $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms'];
                    }
                } elseif (gettype($out['timeoutms']) == 'array') 
                {
                    if (!empty($out['timeoutms'][0])){
                        // 设置请求超时
                        if (array_key_exists('timeout', $out)){
                            $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms'][0]*1000;
                        } else {
                            $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms'][0];
                        }
                    }
                    if (!empty($out['timeoutms'][1])){
                        // 设置连接超时
                        if (array_key_exists('timeout', $out)){
                            $options[CURLOPT_CONNECTTIMEOUT_MS] = $out['timeoutms'][1]*1000;
                        } else {
                            $options[CURLOPT_CONNECTTIMEOUT_MS] = $out['timeoutms'][1];
                        }
                    }
                }
            }
            if (array_key_exists('timeout', $out)) unset($out['timeout']);

            // 设置代理
            if (!empty($out['proxy'])) {
                // 设置HTTP代理
                $options[CURLOPT_PROXY] = $out['proxy'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            } else if(!empty($out['socks5'])) {
                // 设置SOCKS5代理
                $options[CURLOPT_PROXY] = $out['socks5'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            }

            // 设置伪代理
            if (array_key_exists('fool', $out)) {
                if (empty($out['fool'])) $this->Error("代理不能是空的");
                if (gettype($out['fool']) == 'array') {
                    $string = implode(",", $out['fool']);
                } else if (gettype($out['fool']) == 'string') {
                    $string = $out['fool'];
                }
                $options[CURLOPT_HTTPHEADER] = arrUp($options[CURLOPT_HTTPHEADER],[
                    'Client-IP: ' . $string,
                    'X-Forwarded-For: ' . $string
                ]);
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
        $data = [
            'body'     => null,
            'headers'  => null,
            'download' => ['state' => false, 'path' => null],
        ];
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

                    if (!$down = download($data['body'],$name,$this->method['fp_path'])) 
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
            $data['headers'] = format_header(end($headerStr));
            // 把父请求头取出来,有先到后
            if (count($headerStr) > 1) {
                for ($i=0; $i < count($headerStr)-1; $i++) { 
                    $data['headers']['Father'][$i] = format_header($headerStr[$i]);
                }
            }
        }
        // 过滤字符串
        if (!empty($data['body'])) {
            // UTF8编码处理
            if(array_key_exists('utf8', $this->method)) {
                if (is_null($this->method['utf8']) || $this->method['utf8']) 
                {
                    $data['body'] = mb_convert_encoding($data['body'],'utf-8','GBK,UTF-8,ASCII');
                }
            }
            // 过滤字符
            if (!empty($this->method['right'])) {
                // 取右
                $data['body'] = right($data['body'], $this->method['right']);
            } elseif (!empty($this->method['left'])) {
                // 取左
                $data['body'] = left($data['body'], $this->method['left']);
            } elseif (!empty($this->method['core'])) {
                // 取中
                $data['body'] = core($data['body'], $this->method['core'][0], $this->method['core'][1]);
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
     * 错误提示
     * @Author   laoge
     * @DateTime 2020-02-27
     * @param    string     $string 错误信息
     * @return        
     */
    private function error($string) { throw new Exception($string);}

    /**
     * 销毁处理
     */
    public function __destruct()
    {
        unset($this->method, $this->data, $this->exec, $this->fptmp, $this->call_return);
    }
}
