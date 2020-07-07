<?php

namespace jinyicheng\tencent_oplatform\wechat_open_platform;

use BadFunctionCallException;
use InvalidArgumentException;

trait CommonTrait
{
    private static $instance = [];
    private $options;

    /**
     * @param array $options
     */
    private function __construct($options = [])
    {
        $this->options = $options;
        if (!extension_loaded('redis')) throw new BadFunctionCallException('Redis扩展不支持');
    }

    /**
     * @param array $options
     * @return self
     */
    public static function getInstance($options = [])
    {
        if ($options === []) $options = config('wechat_open_platform');
        if ($options === false || $options === []) throw new InvalidArgumentException('配置不存在');
        if (!isset($options['app_id'])) throw new InvalidArgumentException('配置下没有找到app_id设置');
        if (!isset($options['app_secret'])) throw new InvalidArgumentException('配置下没有找到app_secret设置');
        if (!isset($options['app_token'])) throw new InvalidArgumentException('配置下没有找到app_token设置');
        if (!isset($options['app_redis_cache_db_number'])) throw new InvalidArgumentException('配置下没有找到app_redis_cache_db_number设置');
        if (!isset($options['app_redis_cache_key_prefix'])) throw new InvalidArgumentException('配置下没有找到app_redis_cache_key_prefix设置');
        $hash = md5(json_encode($options));
        if (!isset(self::$instance[$hash])) {
            self::$instance[$hash] = new self($options);
        }
        return self::$instance[$hash];
    }
}
