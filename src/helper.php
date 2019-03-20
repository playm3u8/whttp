<?php
// +----------------------------------------------------------------------
// | 函数助手
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: laoge <3385168058@qq.com>
// +----------------------------------------------------------------------
use PL\Fhttp;

// 框架识别
if (defined('THINK_PATH')) 
{
    define('PL_RUNTIME_PATH', RUNTIME_PATH);
} else {
    define('PL_RUNTIME_PATH', dirname(__DIR__).'/runtime/');
}

if (!function_exists('fhttp')) {
    /**
     * 网络请求 (fsockopen)
     * @param  string $url 请求URL地址
     * @return object
     */
    function fhttp($url)
    {
        return (new Fhttp($url));
    }
}

if (!function_exists('left')) {
    /**
     * 取字符串左边
     * @param  string $str       被找字符
     * @param  string $right_str 右边字符
     * @return string            结果字符
     */
    function left($str, $right_str, $mode=false)
    {
        $pos = strpos( $str, $right_str);
        if($pos === false){
            return ($mode)? $str: null;
        }
        if (!$string = substr($str, 0, $pos)){
            return ($mode)? $str: null;
        }else{
            return $string;
        }
    }
}

if (!function_exists('core')) {
    /**
     * 取字符串中间
     * @param  string $str      被找字符
     * @param  string $leftStr  左边字符串
     * @param  string $rightStr 右边字符串
     * @return string           结果字符
     */
    function core($str, $leftStr, $rightStr, $mode=false)
    {
        $left = strpos($str, $leftStr);
        if ($left === false) {
            return ($mode)? $str: null;
        }
        $right = strpos($str, $rightStr, $left + strlen($leftStr));
        if ($left === false or $right === false) {
            return ($mode)? $str: null;
        }
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
    }
}

if (!function_exists('right')) {
    /**
     * 取字符串右边
     * @param  string $str      被找字符
     * @param  string $left_str 左边字符
     * @return string           结果字符
     */
    function right($str, $left_str, $mode=false)
    {
        $pos = strrpos($str, $left_str);
        if($pos === false){
            return ($mode)? $str: null;
        }else{
            return substr($str, $pos + strlen($left_str));
        }
    }
}

if (!function_exists('arrUp')) {
    /**
     * 一维数组合并更新
     * @param  array $array1 被更新数组
     * @param  array $array2 新数组
     * @return array
     */
    function arrUp($array, $array2)
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
}

if (!function_exists('fast')) {
    /**
     * 方便快速获取二维数组
     * @param  array  $data    数据
     * @param  string $strName 配置参数名（.号分割）
     * @return array|string       
     */
    function fast($data, string $strName="") 
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
}

if (!function_exists('download')) {
    /**
     * 下载文件
     * @param  string $name 文件名称
     * @param  string $path 保存目录
     * @return string       
     */
    function download($body, $name, $path=null)
    {
        if (empty($name) || !$body) return Null;

        if (empty($path)) $path = PL_RUNTIME_PATH.'file/';

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
}

if (!function_exists('proxyDownload')) {
    /**
     * 代理下载输出给用户
     * @param  string $url  下载地址(URL)
     * @param  string $name 下载文件名称
     * @return bool
     */
    function proxyDownload($url, $name=null) 
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
}

if (!function_exists('p')) {
    /**
     * 格式化打印数组
     * @param  arr $arr
     * @return string
     */
    function p($arr, $die=false) {
        v_dump( $arr , 1 , '<pre>' , 0 );
        if($die) die;
    }
}

if (!function_exists('shortMd5')) {
    /**
     * 返回16位md5值
     *
     * @param string $str 字符串
     * @return string $str 返回16位的字符串
     */
    function shortMd5($str) {
        return substr(md5($str), 8, 16);
    }
}

if (!function_exists('uuid')) {
    /**
      * Generates an UUID
      *
      * @author     Anis uddin Ahmad
      * @param      string  an optional prefix
      * @param      string  an optional f ('-')
      * @return     string  the formatted uuid
      */
    function uuid($prefix = '',$f = '') {
       $chars = md5(uniqid(mt_rand(), true));
       $uuid  = substr($chars,0,8) . $f;
       $uuid .= substr($chars,8,4) . $f;
       $uuid .= substr($chars,12,4) . $f;
       $uuid .= substr($chars,16,4) . $f;
       $uuid .= substr($chars,20,12);
       return $prefix . $uuid;
    }
}

if (!function_exists('getRandstr')) {
    /**
     * 生成一个随机的字符串
     *
     * @param int $length
     * @param boolean $special_chars
     * @return string
     */
    function getRandstr($length = 12, $special_chars = false){
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $randStr = '';
        for ($i = 0; $i < $length; $i++) {
            $randStr .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $randStr;
    }
}

if (!function_exists('getRandint')) {
    /**
     * 生成随机数字
     * @param  integer $length
     * @return integer
     */
    function getRandint($length = 12){
        $chars = '0123456789';
        $randStr = '';
        for ($i = 0; $i < $length; $i++) {
            $randStr .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $randStr;
    }
}

if (!function_exists('parse_string')) {
    /**
    *   字符串转换成数组
    *
    *   1 参数 输入GET类型字符串
    *
    *   返回值 GET数组
    **/
    function parse_string($s) {
        if (is_array($s)) {
            return $s;
        }
        parse_str($s, $r);
        return $r;
    }
}

if (!function_exists('merge_string')) {
    /**
    *   数组转换成字符串
    *
    *   1 参数 数组
    *
    *   返回值 GET字符串
    **/
    function merge_string($a) {
        if (!is_array($a) && !is_object($a)) {
            return (string) $a;
        }
        return http_build_query(to_array($a));
    }
}

if (!function_exists('to_array')) {
    /**
    *   转成数组
    *
    *   1 参数 数组 或者 对象
    *
    *   返回值 数组
    **/
    function to_array($a) {
        $a = (array) $a;
        foreach ($a as &$v) {
            if (is_array($v) || is_object($v)) {
                $v = to_array($v);
            }
        }
        return $a;
    }
}

if (!function_exists('to_object')) {
    /**
    *   转成对象
    *
    *   1 参数 数组 或者 对象
    *
    *   返回值 对象
    **/
    function to_object($a) {
        $a = (object) $a;
        foreach ($a as &$v) {
            if (is_array($v) || is_object($v)) {
                $v = to_object($v);
            }
        }
        return $a;
    }
}

if (!function_exists('nowtime')) {
    /**
     *  取现行时间戳
     *
     *  无参数
     *
     *  返回值当前时间戳毫秒
     **/
    function nowtime($shiwei = 13){
        $r = explode(' ', microtime());
        $r = ($r[1] + $r[0]) * 1000;
        $r = explode('.', $r);
        if ($shiwei == 10) {
            return substr($r[0], 0, 10);
        } else {
            return $r[0];
        }
    }
}

if (!function_exists('current_ip')) {
    /**
     * 浏览器友好的变量输出
     * @param mixed $var 变量
     * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
     * @param string $label 标签 默认为空
     * @param boolean $strict 是否严谨 默认为true
     * @return void|string
     */
    function v_dump($var, $echo=true, $label=null, $strict=true) {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        }else
            return $output;
    }
}

if (!function_exists('current_ip')) {
    /**
     * 获得 访问者 IP
     * @return string
     */
    function current_ip() {
        static $ip;
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'];

            if (isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $v) {
                    $v = trim($v);
                    if (filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && $_SERVER['SERVER_ADDR'] != $v) {
                        $ip = $v;
                        break;
                    }
                }
            }
            // 兼容请求地址
            if (preg_match('/^(.*(.)\:)(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/', $ip, $matches)) {
                if (strtolower($matches[1]) === '::ffff:') {
                    // ipv4 访问 ipv6
                    $ip = $matches[3];
                } else {
                    // ipv6 访问 ipv4
                    $ip = $matches[1];
                    $pos = strpos($ip, '::') !== false;
                    $arr = str_split(str_pad(dechex(ip2long($matches[3])), 8, '0', STR_PAD_LEFT), 4);
                    $ip = $matches[1];
                    if (strpos($ip, '::') === false) {
                        $ip .= ltrim($arr[0], '0') . ':' . ltrim($arr[1], '0');
                    } else {
                        $ip .= $arr[0] === '0000' ? ($matches[2] == ':' ? '' : '0:') : ltrim($arr[0], '0') . ':';
                        $ip .= $arr[1] === '0000' ? ($matches[2] == ':' ? '' : '0') : ltrim($arr[1], '0');
                    }
                }
            }
            $ip = strtolower($ip);
        }
        return $ip;
    }
}

if (!function_exists('format_header')) {
   /**
     * 格式化响应头部
     * @param  string $value 协议头
     * @return array        
     */
    function format_header(string $value) 
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
}

if (!function_exists('top_domain')) {
    /**
     * 获取url的一级域名
     * @param  [string] $domain [域名]
     * @return [string]         [顶级域名]
     */
    function top_domain($domain) {
      if (substr ( $domain, 0, 7 ) == 'http://') {
          $domain = substr ( $domain, 7 );
      }
      if (strpos ( $domain, '/' ) !== false) {
          $domain = substr ( $domain, 0, strpos ( $domain, '/' ) );
      }
      $domain = strtolower ( $domain );
      $iana_root = array ('ac','ad','ae','aero','af','ag','ai','al','am','an','ao','aq','ar','arpa','as','asia','at','au','aw','ax','az','ba','bb','bd','be','bf','bg','bh','bi','biz','bj','bl','bm','bn','bo','bq','br','bs','bt','bv','bw','by','bz','ca','cat','cc','cd','cf','cg','ch','ci','ck','cl','cm','cn','co','com','coop','cr','cu','cv','cw','cx','cy','cz','de','dj','dk','dm','do','dz','ec','edu','ee','eg','eh','er','es','et','eu','fi','fj','fk','fm','fo','fr','ga','gb','gd','ge','gf','gg','gh','gi','gl','gm','gn','gov','gp','gq','gr','gs','gt','gu','gw','gy','hk','hm','hn','hr','ht','hu','id','ie','il','im','in','info','int','io','iq','ir','is','it','je','jm','jo','jobs','jp','ke','kg','kh','ki','km','kn','kp','kr','kw','ky','kz','la','lb','lc','li','lk','lr','ls','lt','lu','lv','ly','ma','mc','md','me','mf','mg','mh','mil','mk','ml','mm','mn','mo','mobi','mp','mq','mr','ms','mt','mu','museum','mv','mw','mx','my','mz','na','name','nc','ne','net','nf','ng','ni','nl','no','np','nr','nu','nz','om','org','pa','pe','pf','pg','ph','pk','pl','pm','pn','pr','pro','ps','pt','pw','py','qa','re','ro','rs','ru','rw','sa','sb','sc','sd','se','sg','sh','si','sj','sk','sl','sm','sn','so','sr','ss','st','su','sv','sx','sy','sz','tc','td','tel','tf','tg','th','tj','tk','tl','tm','tn','to','tp','tr','travel','tt','tv','tw','tz','ua','ug','uk','um','us','uy','uz','va','vc','ve','vg','vi','vn','vu','wf','ws','xxx','ye','yt','za','zm','zw');
      $sub_domain = explode ( '.', $domain );
      $top_domain = '';
      $top_domain_count = 0;
      for($i = count ( $sub_domain ) - 1; $i >= 0; $i --) {
        if ($i == 0) {
          // just in case of something like NAME.COM
          break;
        }
        if (in_array ( $sub_domain [$i], $iana_root )) {
          $top_domain_count ++;
          $top_domain = '.' . $sub_domain [$i] . $top_domain;
          if ($top_domain_count >= 2) {
            break;
          }
        }
      }
      $top_domain = $sub_domain [count ( $sub_domain ) - $top_domain_count - 1] . $top_domain;
      return $top_domain;
    }
}

if (!function_exists('autHcode')) {
    /**
     * 动态加密解密
     * @param  string  $string    明文或密文
     * @param  string  $operation DECODE表示解密,其它表示加密
     * @param  string  $key       密匙
     * @param  integer $expiry    密文有效期(秒)
     * @return string             结果
     */
    function autHcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
        //处理GET不能识别的字符
        $ystr = array("-", "*");
        $hstr = array("+", "/");
        $string = str_replace($ystr,$hstr,$string);

        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;
        // 密匙
        $key = md5($key ? $key : '#;GX&A_6D*z@K|Z-AAVC01');
        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
        // 参与运算的密匙
        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        // 产生密匙簿
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if($operation == 'DECODE') {
            // substr($result, 0, 10) == 0 验证数据有效性
            // substr($result, 0, 10) - time() > 0 验证数据有效性
            // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
            // 验证数据有效性，请看未加密明文的格式
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            $keystr = $keyc.str_replace('=', '', base64_encode($result));
            //处理GET不能识别的字符
            $ystr = array("+", "/");
            $hstr = array("-", "*");
            $keystr = str_replace($ystr,$hstr,$keystr);
            return $keystr;
        }
    }
}