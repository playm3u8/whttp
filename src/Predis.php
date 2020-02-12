<?php
namespace PL;
/**
 *  Redis原始操作类
 */
class Predis {

    private $redis;

    /**
     * @param string $host
     * @param int $post
     */
    public function __construct($config) {
        $this->redis = new \Redis();
        $state  = $this->redis->connect($config['host'], 6379);
        if ($state == false) {
            die('redis connect failure');
        }
        if(!empty($config['pass'])){
            $this->redis->auth($config['pass']);
        }
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value  设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public function set($key, $value, $timeOut=0) {
        $retRes = $this->redis->set($key, $value);
        if ($timeOut > 0)
            $this->redis->expire($key, $timeOut);
        return $retRes;
    }

    // 设置到期时间
    public function expire($key, $timeOut=0){
        return $this->redis->expire($key, $timeOut);
    }

    /**
     * 通过key获取数据
     * @param string $key KEY名称
     */
    public function get($key) {
        $result = $this->redis->get($key);
        return $result;
    }

    /**
     * 删除一条数据key
     * @param string $key 删除KEY的名称
     */
    public function del($key) {
        return $this->redis->delete($key);
    }

    /**
     * 判断key是否存在
     * @param string $key KEY名称
     */
    public function has($key){
        return $this->redis->exists($key);
    }

}