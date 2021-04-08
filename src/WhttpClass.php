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
     * 并发默认最高数量
     * @var integer
     */
    private $maxConcurrent = 10;

    /**
     * 并发执行计数
     * @var integer
     */
    private $currentIndex  = 0;

    /**
     * 并发请求完成数量
     * @var integer
     */
    private $complete = 0;

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

    /**
     * 回调返回结果
     * @var null
     */
    private $call_return = Null;

    /**
     * 默认请求头
     * @var array
     */
    private $default_header = ['Accept-Encoding: gzip'];

    /**
     * 默认Redis配置
     * @var array
     */
    private $redis_config = [
        'host'    => '127.0.0.1',
        'pass'    => '',
        'expire'  => 60,
        'decache' => false,
        'cacheid' => '',     // 设置缓存id,不设置默认id
        'count'   => 0,      // 允许超时请求次数 0不限制
        'overtimedue' => 60, // 超时请求高于次数设置的缓存时间（秒）
    ];

    private $downloaded = false;
    private $ismulti    = false;
    private $isdown     = false;
    private $progress   = false;

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $func 方法名
     * @param array  $params 参数
     * @return mixed
     */
    public function __call($func, $params)
    {
        $array = null;
        if (isset($func)) {
            $func   = strtolower($func);
            $setlist = array_merge($this->setlist2, Whttp::$setlist1);
            if (in_array($func, array_keys($setlist))) {
                $filter = $setlist[$func];
                $this->InspectionQuantity($filter, $params, $func);
                $this->TypeComparison($filter, $params, $func);
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
                if ($this->method) {
                    if (in_array($func, array_keys($this->method))) {
                        $this->Error('参数['.$func.'()]被重复设置');
                    }
                }
                $this->method[$func] = $array;
            } else {
                $this->Error('内置似乎没有['.$func.'()]方法');
            }
        }
        return $this;
    }

    /**
     * 方法设置的参数类型对比
     * @Author   laoge
     * @DateTime 2021-03-30
     * @param    array      $filter 约束的参数
     * @param    array      $params 方法设置的参数
     * @param    string     $func   方法名称
     * @return   null
     */
    private function TypeComparison(array $filter, array $params, string $func)
    {
        $isTrue = true;
        if (count($params) == 1) {
            $type1 = $filter[0];
            $type2 = gettype($params[0]);
            if(strpos($type1, $type2) === false){ 
                $isTrue = false;
            }
        } else {
            for ($i=0; $i < count($filter); $i++) { 
                if(!$params) {
                    $type = 'NULL';
                } else {
                    $type = gettype($params[$i]);
                }
                if(strpos($filter[$i], $type) === false){
                    $isTrue = false;
                    break;
                }
            }
        }
        if($isTrue == false) {
            $msg = implode(",", $filter);
            $this->Error("[{$func}]参数类型有误,此方法类型有: {$func}({$msg})");
        }
    }

    /**
     * 方法设置的参数数量对比
     * @Author   laoge
     * @DateTime 2021-03-30
     * @param    array      $filter 约束的参数
     * @param    array      $params 方法设置的参数
     * @param    string     $func   方法名称
     * @return   null
     */
    private function InspectionQuantity(array $filter, array $params, string $func)
    {
        $func_count   = count($params);
        $filter_count = count($filter);
        if ($func_count > $filter_count){
            $msg = implode(",", $filter);
            $this->Error("[{$func}]参数过多: {$func}({$msg})");
        } else {
            for ($i=0; $i < count($filter); $i++) { 
                if(strpos($filter[$i], "NULL") === false) { 
                    if ($i+1 > $func_count){
                        $msg = implode(",", $filter);
                        $this->Error("[{$func}]参数".($i+1)."不能为空: {$func}({$msg})");
                    }
                }
            }
        }
    }

    /**
     * 获取响应状态码(不支持并发)
     * @return int 状态码
     */
    public function getCode() 
    {
        return $this->getHeaders("headers.State.StatusCode");
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string       
     */
    public function getHeaders ($name="") 
    {
        if ($this->ismulti) {
            return null;
        }
        $return = $this->send();
        if(!$return['headers']) return Null;

        if(empty($name)){
            return $return['headers'];
        } else {
            return trim(fast($return, "headers.".$name));
        }
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string
     */
    public function getCookie ($name="")
    {
        return $this->getHeaders("headers.Cookie");
    }

    /**
     * 获取响应内容(不支持并发)
     * @return data
     */
    public function getBody() 
    { 
        if ($this->ismulti) {
            return null;
        }
        $return = $this->send();
        if(!$return['body']) return Null;
        return fast($return, "body");
    }

    /**
     * 获取请求信息(不支持并发)
     * @return array
     */
    public function getInfo($name="") 
    { 
        if ($this->ismulti) {
            return null;
        }
        $return = $this->send();
        if(!$return['info']) return Null;
        if(empty($name)){
            return $return['info'];
        } else {
            return fast($return, "info.".$name);
        }
    }

    /**
     * 获取错误信息(不支持并发)
     * @return string
     */
    public function getError() 
    { 
        if ($this->ismulti) {
            return null;
        }
        $return = $this->send();
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
        if ($this->ismulti) {
            return null;
        }
        $return = $this->send();
        if(!$return['body']) return Null;
        $data = json_decode(trim($return['body'], chr(239) . chr(187) . chr(191)), true);
        if(empty($name)) return $data;
        return fast($data, $name);
    }

    /**
     * 获取到全部信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    public function getAll($name="")
    {
        $return = $this->send();
        if(!$return) return Null;
        return fast($return, $name);
    }

    /**
     * 下载文件(批量下载无法显示进度)
     * @param  bool|boolean $progress
     * @return array
     */
    public function getDownload(bool $progress=false)
    {
        $this->progress = $progress;
        $this->isdown = true;
        $this->method['fp_path'] = isset($this->method['savepath'])? $this->method['savepath']:"";
        $this->method['fp_name'] = isset($this->method['savename'])? $this->method['savename']:"";
        if (empty($this->method['fp_path'])) {
            $this->Error("保存文件路径不能为空");
        } 
        if(substr($this->method['fp_path'], -1) != "/") {
            $this->method['fp_path'] = $this->method['fp_path']."/";
        }
        $return = $this->send($this->config($this->method));
        if ($this->ismulti == false || count($this->method['url']) == 1) {
            if(empty($return['error'])){
                if ($this->progress) {
                    printf("progress: [%-50s] %d%% Done\r"."\n", str_repeat('#',100/100*50), 100/100*100);  
                }
            } else {
                if ($this->progress) printf("\n");
            }
        }
        return $return;
    }

    /**
     * 发送请求
     * @return array 响应结果
     */
    private function send($options=null) 
    {
        if($this->data) return $this->data;
        $options = ($options)? $options : $this->config($this->method);
        if(!array_key_exists('gany' ,$this->method)){
            if (count($options) == 1) {
                return $this->single($options);
            }
        }
        if(array_key_exists('gany' ,$this->method)) {
            $this->callback = $this->method['gany'];
        }
        $multi = [];
        do {
            $concurrent = min($this->maxConcurrent, $this->_moreToDo());
            $result = $this->multi($options, $concurrent);
            foreach ($result as $value) $multi[] = $value;
        } while($this->_moreToDo());
        return $multi;
    }

    /**
     * 获取当前并发进度
     * @Author   laoge
     * @DateTime 2021-03-24
     * @return   integer     执行到第几进度
     */
    private function _moreToDo()
    {
        $result = count($this->method['url']) - $this->currentIndex;
        return $result;
    }

    /**
     * 单地址请求
     * @param  array $options 请求配置
     * @return array          响应结果
     */
    private function single($options) 
    {
        $this->data = $this->curlcache($options);
        if($this->data) {
            return $this->data;
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, $options[0]);
            if($this->isdown) $this->fptmp[(string)$ch] = tmpfile();
            $this->data = $this->getExec1($options[0], $ch);
            $this->data['iscache']  = false;
            $this->curlcache($options, $this->data);
            curl_close($ch);
            return $this->data;
        }
    }

    /**
     * 处理请求缓存
     * @Author   laoge
     * @DateTime 2021-04-04
     * @param    array      $options [description]
     * @param    array      $data    [description]
     * @return   [type]              [description]
     */
    private function curlcache($options, $data=[])
    {
        if (!array_key_exists("cache",$this->method)) return [];

        if (gettype($this->method['cache']) != 'NULL'){
            if(array_key_exists("0", $this->method['cache'])) {
                $this->Error("[cache]参数设置错误,参数1必须包含 'host' key");
            }
            foreach ($this->method['cache'] as $key => $value) $this->redis_config[$key] = $value;
        }

        $predis = new Predis($this->redis_config);

        if (!empty($redis_config['cacheid'])) {

            $cacid = "curlCache_".$redis_config['cacheid'];
        } else {
            $cacid = "curlCache_".md5($this->getCacheID($options[0]).$this->redis_config['count'].$this->redis_config['overtimedue']);
        }

        if ( $this->redis_config['decache']) {
            $predis->rm($cacid);
        }

        if (!$data) {
            if ($predis->has($cacid)) {
                $this->data = unserialize(gzinflate($predis->get($cacid)));
                if($this->data) return $this->data;
            } else {
                return [];
            }
        } else {
            $count         = $this->redis_config['count'];
            $overtimedue   = $this->redis_config['overtimedue'];
            $curl_error_id = "curl_error_".md5($count.$overtimedue.$cacid);
            if (empty($data['error']) || $predis->get($curl_error_id) >= $count-1) {
                if ($predis->has($curl_error_id)) {
                    $predis->rm($curl_error_id);
                    $this->redis_config['expire']   = $overtimedue;
                    $data['error'] = "Cache: ".$data['error'];
                }
                $data['iscache'] = true;
                if ($data['headers'] || $data['body'] || $data['download']['size'] > 0) {
                    $predis->set($cacid, gzdeflate(serialize($data)), $this->redis_config['expire']);
                }
            } else {
                if ($this->redis_config['count'] > 0){
                    if (!empty($data['error'])) {
                        if ($count > 0){
                            $predis->increment($curl_error_id);
                        }
                    }
                }
                /*
                if (strpos($data['error'], "timed out") !== false) {
                    if ($count > 0){
                        $predis->increment($curl_error_id);
                    }
                }
                */
            }
        }
    }

    /**
     * 内部处理响应exec数据
     * @param  array  $options 配置信息
     * @param  resource $ch    请求句柄
     * @return array
     */
    private function getExec1($options, $ch)
    {
        $data = [];
        if (!empty($this->exec[(string)$ch])) {
            $exec = $this->exec[(string)$ch];
        } else {
            if($this->ismulti) {
                $exec = curl_multi_getcontent($ch);
            } else {
                $exec = curl_exec($ch);
                if(gettype($exec) == 'boolean') $exec = Null;
            }
        }
        $data['id']       = (int)$ch;
        $data['info']     = $this->deInfourl(curl_getinfo($ch));
        $data['error']    = curl_error($ch);
        $data['host']     = parse_url($data['info']['url'], PHP_URL_HOST);
        $data['headers']  = [];
        $data['body']     = null;
        $data['download'] = [];
        if ($this->isdown) {
            $down_files = $this->down_files($data, $options, $ch);
            if(!empty($down_files['error'])) {
                @fclose($this->fptmp[(string)$ch]);
            }
            foreach ($down_files as $key => $value) $data[$key] = $value;
        } else {
            $headerStr    = substr($exec, 0, $data['info']['header_size'] - 4);
            $headerStr    = explode("\r\n"."\r\n", $headerStr);
            $data['headers'] = format_header(end($headerStr));
            if (count($headerStr) > 1) {
                for ($i=0; $i < count($headerStr)-1; $i++) {
                    $data['headers']['Father'][$i] = format_header($headerStr[$i]);
                }
            }
            $data['body'] = substr($exec, $data['info']['header_size']);
            if ($options[CURLOPT_NOBODY] == false) {
                if(array_key_exists('utf8', $this->method)) {
                    if (is_null($this->method['utf8']) || $this->method['utf8']) {
                        $data['body'] = mb_convert_encoding($data['body'],'utf-8','GBK,UTF-8,ASCII');
                    }
                }
                if (!empty($this->method['right'])) {
                    $data['body'] = right($data['body'], $this->method['right']);
                } elseif (!empty($this->method['left'])) {
                    $data['body'] = left($data['body'], $this->method['left']);
                } elseif (!empty($this->method['core'])) {
                    $data['body'] = core($data['body'], $this->method['core'][0], $this->method['core'][1]);
                }
            }
        }
        return $data;
    }

    /**
     * 处理文件下载
     * @Author   laoge
     * @DateTime 2021-04-04
     * @param    array     $data    [description]
     * @param    array     $options [description]
     * @param    [type]     $ch      [description]
     * @return   [type]              [description]
     */
    private function down_files($data, $options, $ch)
    {
        $http_code = $data['info']['http_code'];
        if (empty($this->method['fp_name']) || $this->ismulti) {
            $file_name = getUrlfile($options[CURLOPT_URL]);
            $this->method['fp_name'] = empty($file_name)? getRandstr():$file_name;
        }

        $return = [
            'error' => $data['error'],
            'download' => [
                'name' => $this->method['fp_name'],
                'path' => "",
                'size' => 0
            ]
        ];
        if (!empty($data['error'])) return $return;
        if($http_code != 200 && $http_code != 302) {
            $return['error'] = "download code:".$http_code;
            return $return;
        }

        if(!file_exists($this->method['fp_path'])) {
            mkdir ($this->method['fp_path'], 0777, true);
        }
        $fopen = fopen($this->method['fp_path'].$this->method['fp_name'], "w");
        if(!$fopen) {
            $return['error'] = "没有权限写入失败";
            return $return;
        }
        fseek($this->fptmp[(string)$ch], 0);
        $file_length = $this->pipe_streams($this->fptmp[(string)$ch], $fopen);
        @fclose($fopen);
        @fclose($this->fptmp[(string)$ch]);
        if ($file_length != $data['info']['size_download'] || $file_length == 0) {
            $return['error'] = "下载失败,文件接收大小不对";
            return $return;
        } else {
            $return['download']['size']  = $file_length;
            $return['download']['path']  = $this->method['fp_path'].$this->method['fp_name'];
        }
        return $return;
    }

    /**
     * 并发请求
     * @param  array $options 配置
     * @return array          
     */
    private function multi($options, $num) 
    {
        $this->ismulti = true;
        if(array_key_exists("cache", $this->method)) $this->Error("批量请求不能使用缓存");
        $op = [];
        $mh = curl_multi_init();
        while ($num-- > 0) {
            $ch = curl_init();
            $op[(string)$ch] = $options[$this->currentIndex];
            curl_setopt_array($ch, $options[$this->currentIndex]);
            curl_multi_add_handle($mh, $ch);
            if($this->isdown) $this->fptmp[(string)$ch] = tmpfile();
            $this->currentIndex++;
        }

        $id = 0;
        $active   = Null;
        do {
            while (($code = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);
            if ($code != CURLM_OK) {
                break;
            }
            while ($done = curl_multi_info_read($mh)) {
                $ch = $done['handle'];
                $this->data[$id] = $this->getExec1($op[(string)$ch], $ch);
                if (is_callable($this->callback)) {
                    $this->data[$id]['request'] = $this->method;
                    $this->data[$id]['complete'] = $this->complete++;
                    $call_return = call_user_func_array($this->callback, array($this->data[$id]));
                    if ($call_return) {
                        $this->call_return[] = $call_return;
                    }
                } 
                $id++;
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            if ($active) {
                curl_multi_select($mh,0.5);
            }
        } while ($active);
        curl_multi_close($mh);
        return ($this->call_return)? $this->call_return : $this->data;
    }  

    /**
     * 接收回调响应内容，可以用来响应数据进行分析
     * @param  [type] $ch   [description]
     * @param  [type] $exec [description]
     * @return int
     */
    private function receiveResponse($ch, $exec)
    {

        if (is_callable($this->method['writefunc'])) {
            $result = call_user_func_array($this->method['writefunc'], array($ch, $exec));
            if ($result == false) {
                return $result;
            }
        }

        if (empty($this->exec[(string)$ch])) {
            $this->exec[(string)$ch] = $exec;
        } else {
            $this->exec[(string)$ch] .= $exec;
        }

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
        if (empty($this->fptmp[(string)$ch])) {
            return 0;
        } else {
            return fwrite($this->fptmp[(string)$ch], $exec);
        }
    }

    /**
     * 配置请求
     * @param  array $out 请求设置
     * @return array      配置
     */
    private function config($out) 
    {
        if (!empty($_SERVER['HTTP_USER_AGENT'])){
            $User_Agent = ['User-Agent: '.$_SERVER['HTTP_USER_AGENT']];
            $this->default_header = arrUp($this->default_header, $User_Agent);
        }

        if (gettype($out['url']) == "array") {
            if (empty($out['url'][0])) {
                $this->Error("数组url不能为空");
            }
        } elseif (gettype($out['url']) == "NULL") {
            $this->Error("Url不能为空");
        }

        if (!$out) return [];
        if (gettype($out['url']) == 'array') 
        {
            if(count($out['url']) > 1){
                $urls    = $out['url'];
            } else {
                $urls[0] = $out['url'][0];
            }
        } elseif (gettype($out['url']) == 'string') {
            $urls[0] = $out['url'];
        } else {
            return [];
        }

        if(array_key_exists('concurrent' ,$this->method)){
            if($this->method['concurrent'] >= 1){
                $this->maxConcurrent = $this->method['concurrent'];
            } else {
                $this->Error("并发数必须大于1");
            }
        }

        foreach ($urls as $id => $url) {
            $urlencode_utf8 = false;
            if ($out['method'] == 'GET') {
                if (isset($out['data'])) {
                    if(is_array($out['data'])){
                        $urlencode_utf8 = true;
                        $url = $url."?".merge_string($out['data']);
                    } else {
                        $url = $url."?".$out['data'];
                    }
                    unset($out['data']);
                }
            }

            $options = [ 
                CURLOPT_URL            => ($urlencode_utf8)? $url:urlencode_utf8($url),
                CURLOPT_NOSIGNAL       => true,
                CURLOPT_TIMEOUT_MS     => defined("CURL_TIMEOUT_MS")? CURL_TIMEOUT_MS:5000,
                CURLOPT_CONNECTTIMEOUT_MS => defined("CURL_CONNECTTIMEOUT_MS")? CURL_CONNECTTIMEOUT_MS:5000,
                CURLOPT_HEADER         => empty($out['fp_path'])? true : false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $out['method'],
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_FILETIME       => true,
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_REFERER        => empty($out['referer'])? $url : $out['referer'],
                CURLOPT_HTTPHEADER     => arrUp($this->default_header, empty($out['header'])? []:$out['header']),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_NOBODY         => false,
            ];

            if(array_key_exists('jump', $out)) {
                if (is_null($out['jump']) || $out['jump']) {
                    $options[CURLOPT_FOLLOWLOCATION] = false;
                } else {
                    $options[CURLOPT_FOLLOWLOCATION] = true;
                }
            }
            
            if(array_key_exists('nobody', $out)) {
                if (is_null($out['nobody']) || $out['nobody']) {
                    $options[CURLOPT_NOBODY] = true;
                } else {
                    $options[CURLOPT_NOBODY] = false;
                }
            }

            if (array_key_exists('writefunc', $out)) {
                if (is_callable($out['writefunc'])) {
                    $options[CURLOPT_WRITEFUNCTION] = [$this, 'parent::receiveResponse'];
                }
            }

            if (in_array($out['method'], ['POST','PUT','PATCH','DELETE'])) {
                if (array_key_exists('data', $out)) {
                    $options[CURLOPT_POST]       = true;
                    $options[CURLOPT_POSTFIELDS] = $out['data'];
                } else {
                    $options[CURLOPT_POST]       = true;
                    $options[CURLOPT_POSTFIELDS] = "";
                }
            }

            if (parse_url($url, PHP_URL_SCHEME) == "https") {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = false;
            }

            if(!empty($out['cookie'])) $options[CURLOPT_COOKIE] = $out['cookie'];
            if (isset($out['fp_path'])) {
                if (count($urls) == 1) {
                    if($this->progress) {
                        $options[CURLOPT_NOPROGRESS] = false;
                        $options[CURLOPT_PROGRESSFUNCTION] = [$this, 'parent::progress'];
                    }
                }
                $options[CURLOPT_WRITEFUNCTION] = [$this, 'parent::receiveDownload'];
                $options[CURLOPT_TIMEOUT_MS] = 0;
                $options[CURLOPT_CONNECTTIMEOUT_MS] = 1000*60;
            }

            if(array_key_exists('timeoutms', $out)) {
                if (gettype($out['timeoutms']) == 'integer') {
                    $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms'];
                } elseif (gettype($out['timeoutms']) == 'array') {
                    if (isset($out['timeoutms'][0])){
                        $options[CURLOPT_TIMEOUT_MS] = $out['timeoutms'][0];
                    }
                    if (isset($out['timeoutms'][1])){
                        $options[CURLOPT_CONNECTTIMEOUT_MS] = $out['timeoutms'][1];
                    }
                }
            }

            if (!empty($out['proxy'])) {
                $options[CURLOPT_PROXY] = $out['proxy'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            } else if(!empty($out['socks5'])) {
                $options[CURLOPT_PROXY] = $out['socks5'];
                $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            }

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
                $options[CURLOPT_ENCODING] = 'gzip';              
            }
            $return[$id] = $options;
        }
        return $return;
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
        // 必要删除回调事件属性
        // 必要删除回调事件属性
        // 必要删除回调事件属性
        unset($options[CURLOPT_WRITEFUNCTION]);
        unset($options[CURLOPT_PROGRESSFUNCTION]);
        return md5(serialize($options));
    }

    /**
     * 解码返回请求INFO里面的URL地址
     * @Author   laoge
     * @DateTime 2021-04-03
     * @param    array      $info [description]
     * @return   [type]           [description]
     */
    private function deInfourl($info=[]) 
    {
        $info['url'] = rawurldecode((string)$info['url']);
        return $info;
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
        $this->method      = null;
        $this->data        = null;
        $this->exec        = null;
        $this->fptmp       = null;
        $this->call_return = null;
        $this->downloaded  = null;
        unset($this->method,$this->data,$this->exec,$this->fptmp,$this->call_return,$this->downloaded);
    }

    /**
     * 进度条下载.
     *
     * @param $ch
     * @param $countDownloadSize    总下载量
     * @param $currentDownloadSize  当前下载量
     * @param $countUploadSize      
     * @param $currentUploadSize
     */
    private function progress($ch, $countDownloadSize, $currentDownloadSize, $countUploadSize, $currentUploadSize)
    {
        if (0 === $countDownloadSize) {
            return false;
        }
        if ($countDownloadSize > $currentDownloadSize) {
            $this->downloaded = false;
        }
        elseif ($this->downloaded) {
            return false;
        }
        elseif ($currentDownloadSize === $countDownloadSize) {
            return false;
        }
        $bar = $currentDownloadSize / $countDownloadSize * 100;
        $bar = (int)round($bar, 2);
        printf("progress: [%-50s] %d%% Done\r", str_repeat('#',$bar/100*50), $bar/100*100);
    }
}