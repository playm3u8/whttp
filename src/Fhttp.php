<?php
// +----------------------------------------------------------------------
// | HTTP请求类(采集爬虫专用)
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: laoge <3385168058@qq.com>
// +----------------------------------------------------------------------
namespace PL;

/**
 * http请求类库 (fsockopen)
 */
class Fhttp
{
    
    /**
     * 连接句柄
     * @var null
     */
    private $hand = Null;
    
    /**
     * 默认阻塞模式，反则非阻塞模式
     * @var boolean
     */
    private $blocking = true;
    
    /**
     * 默认请求超时(秒)
     * @var integer
     */
    private $timeout = 2;
    
    /**
     * URL解析数据
     * @var array
     */
    private $parse = array();
    
    /**
     * POST提交数据
     * @var string
     */
    private $postStr = "";
    
    /**
     * 请求头信息
     * @var array
     */
    private $header = array();
    
    /**
     * Gzip
     * @var boolean
     */
    private $is_gzip = false;
    
    /**
     * 过滤字符取中
     * @var array
     */
    private $filter_zhong = array();
    
    /**
     * 过滤字符取右
     * @var string
     */
    private $filter_you = "";
    
    /**
     * 过滤字符取左
     * @var string
     */
    private $filter_zuo = "";
    
    /**
     * 是否utf8解码
     * @var boolean
     */
    private $utf8 = false;

    /**
     * 代理IP
     * @var string
     */
    private $proxy_ip = "";

    /**
     * 重定向
     * @var boolean
     */
    private $jump     = true;

    /**
     * 获取响应头部
     * @var boolean
     */
    private $no_body  = false;
    
    /**
     * 默认请求头
     * @var array
     */
    private $default_header = array(
        'Accept: */*', 
        'Accept-Language: zh-cn', 
        'Accept-Encoding: gzip', // 采用GZIP 访问
        'Connection: Close', 
        'User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36'
    );
    
    /**
     * 请求地址
     * @param string $url 访问URL
     */
    public function __construct(string $url)
    {
        // 解析URL地址
        $this->parse = $this->parse_urls($url);
        // 设置请求协议
        $this->structure("GET " . $this->parse['uPath'] . " HTTP/1.0");
        // 设置请求HOST
        $this->structure("Host: " . $this->parse['host']);
        // 设置默认请求头
        $this->structure($this->default_header);
        // 设置请求来路
        $this->structure("Referer: " . $url);
        // 返回对象
        return $this;
    }
    
    /**
     * 设置POST
     * @param  string $value 数据
     * @return $this        
     */
    public function data(string $value)
    {
        // 检测提交数据
        if (empty($value)) {
            die('ERROR: 没有提交数据');
        }
        if (gettype($value) != "string" && gettype($value) != "array") {
            die('ERROR: 错误提交类型');
        }
        // 设置请求协议
        $this->header[0] = "POST " . $this->parse['uPath'] . " HTTP/1.0";
        // 再请求头添加POST请求标识
        $this->structure("Content-Type: application/x-www-form-urlencoded");
        // 赋值变量
        $this->postStr = $value;
        return $this;
    }
    

    /**
     * 设置来路
     * @param  string $value url
     * @return $this
     */
    public function referer(string $value = "")
    {
        // 设置请求来路
        $this->structure("Referer: " . $value);
        return $this;
    }

    /**
     * 重定向
     * @param  bool   $value 真或假
     * @return $this
     */
    public function jump(bool $value = false)
    {
        $this->jump = $value;
        return $this;
    }

    /**
     * 伪装IP
     * @param  string|array $value IP
     * @return $this
     */
    public function fool_ip($value)
    {
        $str_ip = Null;
        if (gettype($value) == 'array') {
            $str_ip = implode(",", $value);
        } else if (gettype($value) == 'string') {
            $str_ip = $value;
        } else {
            die('ERROR: 伪装IP不能为空哦');
        }
        // 设置到请求头中
        $this->structure('Client-IP: ' . $str_ip);
        $this->structure('X-Forwarded-For: ' . $str_ip);
        return $this;
    }

    /**
     * 设置utf8编码
     * @return $this
     */
    public function utf8()
    {
        // 设置编码
        $this->utf8 = true;
        return $this;
    }

    /**
     * 设置请求头
     * @param  array  $value 请求头
     * @return $this        
     */
    public function header(array $value)
    {
        // 合并更新设置请求头
        if ($value) {
            $this->structure($value);
        }
        return $this;
    }

    /**
     * 取结果内容中间字符
     * @param  [type] $str1 前字符
     * @param  [type] $str2 后字符
     * @return $this       
     */
    public function core(string $str1, string $str2)
    {
        if (empty($str1) || empty($str2)) {
            die('ERROR: 过滤字符有误');
        }
        $this->filter_zhong = array(
            $str1,
            $str2
        );
        return $this;
    }
    
    /**
     * 取结果右边字符
     * @param  string $str 前字符
     * @return $this      
     */
    public function right(string $str)
    {
        if (empty($str)) {
            die('ERROR: 过滤字符有误');
        }
        $this->filter_you = $str;
        return $this;
    }
    
    /**
     * 取结果左边字符
     * @param  string $str 后字符
     * @return $this      
     */
    public function left(string $str)
    {
        if (empty($str)) {
            die('ERROR: 过滤字符有误');
        }
        $this->filter_zuo = $str;
        return $this;
    }

    /**
     * 为资源流设置阻塞或者阻塞模式
     * @param  bool|boolean $value 真或假
     * @return $this
     */
    public function blocking($value = false)
    {
        $this->blocking = $value;
        return $this;
    }
    
    /**
     * 设置Cookie
     * @param  string $value Cookie
     * @return $this        
     */
    public function cookie(string $value="")
    {
        if(!empty($value)){
            // 设置请求来路
            $this->structure("Cookie: " . $value);
        }
        return $this;
    }

    /**
     * 设置代理IP请求
     * @param  string $value 代理IP
     * @return $this       
     */
    public function proxy_ip(string $value)
    {
        // 检查代理数据
        if (strpos($value, ':') === false) {
            die('ERROR: 示例 127.0.0.1:80');
        }
        $this->proxy_ip = $value;
        return $this;
    }
    
    /**
     * 设置超时
     * @param  integer $value 秒
     * @return $this 
     */
    public function timeout($value)
    {
        $this->timeout = $value;
        return $this;
    }
    
    /**
     * 以数组形式返回
     * @param  string $value 名称(.号分割)
     * @return array|string
     */
    public function getJson(string $value="")
    {
        // 获取全部数据
        $return = $this->getAll();
        if (!$return['body']) {
            return Null;
        } 
        // 编码
        $data = json_decode(trim($return['body'], chr(239) . chr(187) . chr(191)), true);
        if(empty($value)){
            return $data;
        }
        return fast($data, $value);
    }

    /**
     * 获取响应内容
     * @return array
     */
    public function getBody()
    {
        $return = $this->getAll();
        // 获取全部数据
        return $return['body'];
    }

    /**
     * 获取响应头部
     * @param  string $name 名称(.号分割)
     * @return string|array       
     */
    public function getHeaders(string $name="")
    {
        // 变量赋值
        $this->no_body = true;
        $return = $this->getAll();
        // 获取全部数据
        return fast($return, "headers.".$name);
    }

    /**
     * 获取全部数据
     * @param  bool|boolean $initial 是否需要初始数据
     * @return sring|array                
     */
    public function getAll(bool $initial=false)
    {
        $return = array(
            'body'    => "",
            'error'   => "",
            'headers'  => Null,
            'blocked' => false
        );
        // 发送请求
        $this->send();
        // 读取数据
        $reponse = Null;
        $info    = stream_get_meta_data($this->hand);
        while ((!feof($this->hand)) && (!$info['timed_out']))
        {
            $reponse .= fgets($this->hand, 4096);
            // 从封装协议文件指针中取得报头／元数据
            $info = stream_get_meta_data($this->hand);
            // 异步请求直接跳出
            if (!$info['blocked']) break;
            // 冲刷出（送出）输出缓冲区中的内容
            // ob_flush(); flush();
            // 头部与内容分界处
            if (strstr($reponse, "\r\n"."\r\n")) {
                // 不要BODY就直接跳出
                if ($this->no_body) break;
                // 处理重定向
                if ($this->jump) {
                    if (strstr(strtolower($reponse), "location:")) {
                        // 提取URL
                        preg_match("/ocation:(.*?)$/im",$reponse,$match); 
                        // 输出重定向之前头部
                        $return['headers_302'] = format_header($reponse);
                        // 重定向处理Cookie
                        if (empty($return['headers_302']['Set-Cookie'])) {
                            $scookie = "";
                        } else {
                            $scookie = $return['headers_302']['Set-Cookie'];
                        }
                        // 请求重定向
                        $reponse = (new fhttp(trim($match[1])))->cookie($scookie)->getAll(true);
                        break;
                    }
                }
            }
        }
        // 为重定向输出（初始数据）
        if ($initial) {
            if (!$info['timed_out']) {
                return $reponse;
            } else {
                // 重定向超时输出
                return $reponse.PHP_EOL."Connection Timed Out!";
            }
        }
        // 超时输出错误
        if ($info['timed_out']) {
            $return['error'] = "Connection Timed Out!";
            return $return;
        } else if($info['blocked'] == false) {
            // 异步请求直接退出
            return $return;
        }
        // 分割响应头和响应内容
        $pos = strpos($reponse, "\r\n" . "\r\n");
        if ($pos === false) {
            $return['blocked'] = true;
            $return['body']    = $this->gzdecode($reponse);
            return $return;
        } else {
            $return['headers']  = format_header(substr($reponse, 0, $pos));
            $body   = substr($reponse, $pos + 2 * strlen("\r\n"));
            $return['blocked'] = true;
            $return['body']    = $this->gzdecode($body);
            return $return;
        }
    }
    
    /**
     * 发送请求
     * @return Null
     */
    public function send()
    {
        // 如果设置了请求Gzip在这里就还要设置为Gzip解码
        foreach ($this->header as $value) {
            $value = strtolower($value);
            if (strstr($value, 'gzip') && strstr($value, 'accept-encoding')) {
                // 找到请求头有gzip标示请求就采用gzip解码
                $this->is_gzip = true;
            }
        }
        // 设置请求方式
        if ($this->parse['port'] == 443) {
            $this->parse['host'] = "ssl://" . $this->parse['host'];
        }
        // 设置代理请求
        if (!empty($this->proxy_ip)) {
            $this->parse['host'] = parse_url($this->proxy_ip, PHP_URL_HOST);
            $this->parse['port'] = parse_url($this->proxy_ip, PHP_URL_PORT);
        }
        // 连接请求服务器
        // echo $this->timeout."<br>";
        // 注意：如果你要对建立在套接字基础上的读写操作设置操作时间设置连接时限，
        // 请使用stream_set_timeout()，fsockopen()的连接时限（timeout）的参数仅仅在套接字连接的时候生效。
        $this->hand = fsockopen($this->parse['host'], $this->parse['port'], $errno, $errstr, $this->timeout);
        // 如果请求连接失败直接返回错误提示
        if ($this->hand == false) {
            die("ERROR: $errno - $errstr");
        }
        // 获取提交数据长度
        if ($this->postStr) {
            $this->structure("Content-Length: " . strlen($this->postStr));
            $this->structure(PHP_EOL . $this->postStr);
        } else {
            $this->structure(PHP_EOL);
        }
        // 为资源流设置阻塞或者阻塞模式
        stream_set_blocking($this->hand, $this->blocking);
        // 设置超时
        stream_set_timeout($this->hand, $this->timeout);
        // 写入文件
        @fwrite($this->hand, implode(PHP_EOL, $this->header));
    }
    
    /**
     * Gzip解码
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function gzdecode($data)
    {
        $flags       = ord(substr($data, 3, 1));
        $headerlen   = 10;
        $extralen    = 0;
        $filenamelen = 0;
        if ($flags & 4) {
            $extralen = unpack('v', substr($data, 10, 2));
            $extralen = $extralen[1];
            $headerlen += 2 + $extralen;
        }
        if ($flags & 8) // Filename
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 16) // Comment
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 2) // CRC at end of file
            $headerlen += 2;
        $unpacked = @gzinflate(substr($data, $headerlen));
        if ($unpacked === FALSE)
            $unpacked = $data;
        // 处理utf8编码
        if ($this->utf8) {
            if (!empty($unpacked)) {
                $unpacked = mb_convert_encoding($unpacked, 'utf-8', 'GBK,UTF-8,ASCII');
            }
        }
        // 过滤字符
        if (!empty($this->filter_you)) {
            // 取右
            $unpacked = right($unpacked, $this->filter_you);
        } elseif (!empty($this->filter_zuo)) {
            // 取左
            $unpacked = left($unpacked, $this->filter_zuo);
        } elseif ($this->filter_zhong) {
            // 取中
            $unpacked = core($unpacked, $this->filter_zhong[0], $this->filter_zhong[1]);
        }
        return $unpacked;
    }
    
    /**
     * chunked解码 HTTP/1.1 需要
     * @param  [type] $in [description]
     * @return [type]     [description]
     */
    private function chunked_decode($in)
    {
        $out = '';
        while ($in != '') {
            $lf_pos = strpos($in, "\012");
            if ($lf_pos === false) {
                $out .= $in;
                break;
            }
            $chunk_hex = trim(substr($in, 0, $lf_pos));
            $sc_pos    = strpos($chunk_hex, ';');
            if ($sc_pos !== false)
                $chunk_hex = substr($chunk_hex, 0, $sc_pos);
            if ($chunk_hex == '') {
                $out .= substr($in, 0, $lf_pos);
                $in = substr($in, $lf_pos + 1);
                continue;
            }
            $chunk_len = hexdec($chunk_hex);
            if ($chunk_len) {
                $out .= substr($in, $lf_pos + 1, $chunk_len);
                $in = substr($in, $lf_pos + 2 + $chunk_len);
            } else {
                $in = '';
            }
        }
        return $out;
    }
    
    /**
     * 解析URL地址
     * @param  string $url URL
     * @return array
     */
    private function parse_urls(string $url)
    {
        // 解析URL
        $array = parse_url($url);
        if(!isset($array['port'])) $array['port'] = "";
        if(!isset($array['query'])) $array['query'] = "";
        if(!isset($array['path'])) $array['path'] = "";
        // 请求路径
        if (!$array['path']) {
            // 不存在就加个/
            $array['uPath'] = "/";
        } else {
            $array['uPath'] = $array['path'] . ($array['query'] ? '?' . $array['query'] : "");
        }
        // 端口
        if (!$array['port']) {
            if ($array['scheme'] == 'https') {
                $array['port'] = 443;
            } else {
                $array['port'] = 80;
            }
        }
        return $array;
    }

    /**
     * 构建请求
     * @param  array $value 新成员
     * @return array
     */
    private function structure($value)
    {
        // 定义变量
        $array = array();
        
        if (empty($value))
            return;
        
        if (gettype($value) == 'string') {
            $array[0] = $value;
        } elseif (gettype($value) == 'array') {
            $array = $value;
        } else {
            return;
        }
        // 如果原始数据为空就直接添加
        if (!$this->header)
            $this->header = $array;
        // 加入数据
        foreach ($array as $v) {
            $a = explode(':', $v);
            $i = 0;
            foreach ($this->header as $va) {
                $b = explode(':', $va);
                if ($b[0] == $a[0]) {
                    $this->header[$i] = $v;
                    break;
                }
                if (count($this->header) == $i + 1) {
                    $this->header[$i + 1] = $v;
                }
                $i++;
            }
        }
    }
    
    /**
     * 后置操作方法(销毁处理)
     */
    public function __destruct()
    {
        if ($this->hand) {
            // 销毁
            // echo $this->hand."<br>";
            fclose($this->hand);
        }
    }
}