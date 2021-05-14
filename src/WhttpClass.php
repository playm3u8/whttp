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
use PL\ProgressBar;

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
    private $default_header = [
        'Accept-Encoding: gzip',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36',
    ];

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
        'callback' => null,  // 缓存数据先处理,选择性进行数据缓存(true:缓存,false:不缓存)
    ];

    private $downloaded = false;
    private $ismulti    = false;
    private $isdown     = false;
    private $progress   = false;
    private $file       = 12304;
    private $path       = 12305;

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
        return (int)$this->getHeaders("state.code");
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string       
     */
    public function getHeaders (string $name="") 
    {
        if ($this->ismulti) {
            return '';
        }
        $result = $this->send();
        if(!$result['headers']) return '';

        if(empty($name)){
            return $result['headers'];
        } else {
            return trim(fast($result, "headers.".$name));
        }
    }

    /**
     * 获取响应头部(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return string
     */
    public function getCookie (string $name="")
    {
        $result = $this->getHeaders("cookie");
        if(!empty($name)) {
            return core($result, $name.'=', ';');
        } else {
            return $result;
        }
    }

    /**
     * 获取响应内容(不支持并发)
     * @return data
     */
    public function getBody() 
    { 
        if ($this->ismulti) {
            return '';
        }
        $result = $this->send();
        if(!$result['body']) return '';
        return fast($result, "body");
    }

    /**
     * 获取请求信息(不支持并发)
     * @return array
     */
    public function getInfo(string $name="") 
    { 
        if ($this->ismulti) {
            return '';
        }
        $result = $this->send();
        if(!$result['info']) return '';
        if(empty($name)){
            return $result['info'];
        } else {
            return fast($result, "info.".$name);
        }
    }

    /**
     * 获取错误信息(不支持并发)
     * @return string
     */
    public function getError() 
    { 
        if ($this->ismulti) {
            return '';
        }
        $result = $this->send();
        if($result['body']) return '';
        return fast($result, "error");
    }

    /**
     * 以数组形式返回(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array       
     */
    public function getJson(string $name="")
    {
        if ($this->ismulti) {
            return '';
        }
        $result = $this->send();
        if(!$result['body']) return '';
        $data = json_decode(trim($result['body'], chr(239) . chr(187) . chr(191)), true);
        if(empty($name)) return $data;
        return fast($data, $name);
    }

    /**
     * 获取到全部信息(不支持并发)
     * @param  string $name 名称(.号分割)
     * @return array
     */
    public function getAll(string $name="")
    {
        $result = $this->send();
        if(!$result) return [];
        return fast($result, $name);
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
        $this->method['fp_path'] = isset($this->method['savepath'])? $this->method['savepath']:"./";
        $this->method['fp_name'] = isset($this->method['savename'])? $this->method['savename']:"";

        $result = $this->send($this->config($this->method));
        if ($this->ismulti == false || count($this->method['url']) == 1) {
            if(empty($result['error'])){
                if ($this->progress) {
                    printf("download: [%-50s] %d%%\r"."\n", str_repeat('#',100/100*50), 100/100*100);
                    printf('file: '.$result['download']['path']."\n");
                }
            } else {
                if ($this->progress) printf("\n");
            }
        }
        return $result;
    }

    /**
     * 多线程下载文件(目前只能支持单请求)
     * @Author   laoge
     * @DateTime 2021-05-13
     * @param    int|integer $threads [description]
     * @return   [type]               [description]
     */
    public function getDownloadEx(int $threads=10, bool $progress=false): array
    {
        $return_data = [
            'error'     => '',
            'download'  => [],
        ];
        $fp_path = isset($this->method['savepath'])? $this->method['savepath']:"./";
        $fp_name = isset($this->method['savename'])? $this->method['savename']:"";
        $options = $this->config($this->method);
        if (count($options) > 1) {
            $return_data['error'] = '只能接收一个url请求';
            return $return_data;
        }
        if (empty($fp_name)) {
            $fp_name = getUrlfile($options[0][CURLOPT_URL]);
        }
        // 内部请求获取文件总大小
        if ($url_info = get_urlfileslicing($options[0][CURLOPT_URL], $threads)) {
            // echo 'file: '$fp_name."\n";
            // 内部并发请求
            if ($progress) {
                get($url_info)->concurrent($threads)
                    ->gany(function($data){
                        $count = count($data['request']['url']);
                        $speed = $data['complete']+1;
                        $progress =  $speed / $count * 100;
                        ProgressBar::progressBarPercent('Download:', $progress, 50, ['█',' ']);
                    })->getDownload();
            } else {
                get($url_info)->concurrent($threads)->getDownload();
            }
            // 验证文件片段是否下载完成
            foreach ($url_info as $value1) {
                if (!$tmpfile = file_exists(path_suffix($value1['param']['savepath']).$value1['param']['savename'])) {
                    $return_data['error'] = '文件片段下载缺漏,请重新下载';
                    break;
                }
            }
            if (empty($return_data['error'])) {
                // 合并文件
                $savefile = path_suffix($fp_path).$fp_name;
                try {
                    $fp = fopen($savefile, 'w+');
                    fseek($fp, 0);
                    foreach ($url_info as $value2) {
                        $file  = path_suffix($value2['param']['savepath']).$value2['param']['savename'];
                        $bytes = read_file($file);
                        fwrite($fp, $bytes, strlen($bytes));
                        unlink($file);
                    }
                    fclose($fp);
                    if ($url_info[0]['param']['file_length'] != filesize($savefile)) {
                        $return_data['error'] = '文件下载失败,下载文件大小不匹配';
                    } else {
                        $return_data['download']['name'] = $fp_name;
                        $return_data['download']['path'] = $savefile;
                        $return_data['download']['size'] = filesize($savefile);
                    }
                } catch (\Exception $e) {
                    $return_data['error'] = $e->getMessage();
                }
            }
            if (empty($return_data['error'])) {
                $this->delete_tmp($url_info[0]['param']['savename']);
            }
            return $return_data;
        } else {
            $return_data['error'] = '下载url的Header获取失败,或不支持切片下载';
            return $return_data;
        }
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
            unset($this->method['gany']);
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
        $this->data['cacheinfo']  = [];
        if (!array_key_exists("cache",$this->method)) return [];

        if (gettype($this->method['cache']) != 'NULL'){
            if(array_key_exists("0", $this->method['cache'])) {
                $this->Error("[cache]参数设置错误,参数1必须包含 'host' key");
            }
            foreach ($this->method['cache'] as $key => $value) $this->redis_config[$key] = $value;
        }

        $predis = new Predis($this->redis_config);

        if (!empty($this->redis_config['cacheid'])) {

            $cacid = "curlCache_".$this->redis_config['cacheid'];
        } else {
            $cacid = "curlCache_".md5($this->getCacheID($options[0]).$this->redis_config['count'].$this->redis_config['overtimedue']);
        }

        if ( $this->redis_config['decache']) {
            $predis->rm($cacid);
        }

        if (!$data) {
            if ($predis->has($cacid)) {
                $this->data = unserialize(gzinflate($predis->get($cacid)));
                if($this->data) {
                    $this->data['cacheinfo'] = [
                        'cache_id'  => $cacid,
                        'cache_exp' => $predis->ttl($cacid),
                    ];
                    return $this->data;
                }
            } else {
                return [];
            }
        } else {
            if(! empty($this->redis_config['callback'])) {
                // 处理缓存回调
                $call_return = call_user_func_array($this->redis_config['callback'], array($data));
                // 删除回调数据,不然会夹带很多数据
                unset($this->redis_config['callback']);
                if ($call_return == false) {
                    // 返回假为不缓存结果
                    return [];
                }
            }
            $count         = $this->redis_config['count'];
            $overtimedue   = $this->redis_config['overtimedue'];
            $curl_error_id = "curl_error_".md5($count.$overtimedue.$cacid);
            if (empty($data['error']) || $predis->get($curl_error_id) >= $count-1) {
                if ($predis->has($curl_error_id)) {
                    $predis->rm($curl_error_id);
                    $this->redis_config['expire']   = $overtimedue;
                    $data['error'] = "Cache: ".$data['error'];
                }
                $this->data['cacheinfo'] = [
                    'cache_id'  => $cacid,
                    'cache_exp' => $this->redis_config['expire'],
                ];
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
        $data['body']     = '';
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
                    $data['headers']['father'][$i] = format_header($headerStr[$i]);
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
        $dulifile = false;
        if (isset($options[$this->file])) {
            if (empty($options[$this->file]) == false) {
                $dulifile = true;
                $this->method['fp_name'] = $options[$this->file];
            }
        }

        if (isset($options[$this->path])) {
            if (empty($options[$this->path]) == false) {
                $this->method['fp_path'] = $options[$this->path];
            }
        }

        $http_code = $data['info']['http_code'];
        if ($dulifile == false) {
            if (empty($this->method['fp_name']) || $this->ismulti) {
                $file_name = getUrlfile($options[CURLOPT_URL]);
                $this->method['fp_name'] = empty($file_name)? getRandstr():$file_name;
            }
        }

        $result = [
            'error' => $data['error'],
            'download' => [
                'name' => $this->method['fp_name'],
                'path' => "",
                'size' => 0
            ]
        ];

        if (!empty($data['error'])) return $result;

        if (in_array($http_code, [200, 302, 206]) == false) {
            $result['error'] = "download code:".$http_code;
            return $result;
        }

        $this->method['fp_path'] = path_suffix($this->method['fp_path']);
        if(!file_exists($this->method['fp_path'])) {
            mkdir ($this->method['fp_path'], 0777, true);
        }

        $fopen = @fopen($this->method['fp_path'].$this->method['fp_name'], "w");
        if(!$fopen) {
            $result['error'] = "没有权限写入失败";
            return $result;
        }
        @fseek($this->fptmp[(string)$ch], 0);
        $file_length = $this->pipe_streams($this->fptmp[(string)$ch], $fopen);
        @fclose($fopen);
        @fclose($this->fptmp[(string)$ch]);
        if ($file_length != $data['info']['size_download'] || $file_length == 0) {
            $result['error'] = "下载失败,文件接收大小不对";
            return $result;
        } else {
            $result['download']['size']  = $file_length;
            $result['download']['path']  = $this->method['fp_path'].$this->method['fp_name'];
        }
        return $result;
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
                    unset($this->data[$id]['request']['gany']);
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
        $urls = [];
        if (!$out) return [];
        if (empty($_SERVER['HTTP_USER_AGENT']) == false) {
            $User_Agent = ['User-Agent: '.$_SERVER['HTTP_USER_AGENT']];
            $this->default_header = update_header($this->default_header, $User_Agent);
        }

        if (empty($out['header']) == false) {
            $this->default_header = update_header($this->default_header, $out['header']);
        }

        if (gettype($out['url']) == "array") {
            if (empty($out['url'][0])) {
                $this->Error("数组url不能为空");
            } else {
                if(count($out['url']) > 1){
                    $urls    = $out['url'];
                } else {
                    $urls[0] = $out['url'][0];
                }
            }
        } elseif (gettype($out['url']) == 'string') {

            if (empty($out['url'])) {
                $this->Error("请求url不能为空");
            } else {
                $urls[0] = $out['url'];
            }
        }

        if (isset($out['concurrent'])) {
            if ($out['concurrent'] >= 1) {
                $this->maxConcurrent = $out['concurrent'];
            } else {
                $this->Error("并发数必须大于1");
            }
        }

        foreach ($urls as $id => $info) {
            $param = [];
            // $url类型有2种
            if (is_array($info)) {
                $url = $info['url'];
                $param = isset($info['param'])? $info['param']:[];
            } else {
                $url = $info;
            }

            if (validation_url($url) == false) {
                $this->Error("请求URL地址有误,请检查");
            }

            $urlencode_utf8 = false;
            if ($out['method'] == 'GET') {
                if (isset($out['data'])) {
                    if (is_array($out['data'])) {
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
                CURLOPT_HEADER         => $this->isdown ? false : true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $out['method'],
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_FILETIME       => true,
                CURLOPT_AUTOREFERER    => true,
                CURLOPT_REFERER        => empty($out['referer'])? $url : $out['referer'],
                CURLOPT_HTTPHEADER     => $this->default_header,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_NOBODY         => false
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
            if ($this->isdown) {
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
            // 独立修改每个请求参数
            // 修改请求头
            if (isset($param['header'])) {
                if (is_array($param['header'])) {
                    $options[CURLOPT_HTTPHEADER] = update_header($this->default_header, $param['header']);
                }
            }
            // 保存文件名称
            if (isset($param['savename'])) {
                if (empty($param['savename']) == false) {
                    $options[$this->file] = $param['savename'];
                }
            }
            // 保存文件路径
            if (isset($param['savepath'])) {
                if (empty($param['savepath']) == false) {
                    $options[$this->path] = $param['savepath'];
                }
            }
            $result[$id] = $options;
        }
        return $result;
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
     * 清除不完整的临时文件
     * @Author   laoge
     * @DateTime 2021-05-14
     * @param    string     $savename [description]
     * @return   [type]               [description]
     */
    private function delete_tmp(string $savename) 
    {
        $mask = "whhtp_".core($savename, 'htp_', '-')."-*";
        array_map( "unlink", glob($mask));
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
        printf("download: [%-50s] %d%%\r", str_repeat('#',$bar/100*50), $bar/100*100);
    }
}