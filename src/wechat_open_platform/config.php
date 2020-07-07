<?php

return [
    // +----------------------------------------------------------------------
    // | 腾讯对接配置信息
    // +----------------------------------------------------------------------
    'app_name' => env('app_name','腾讯开放平台'),//填写应用名称，一旦上线谨慎修改，曾经调取过此参数的记录将不做变更，仅对更新后版本有效
    'app_id' => env('app_id','xxx'),//请从官方获取
    'app_secret' => env('app_secret','xxx'),//请从官方获取
    'app_redis_cache_db_number' => env('app_redis_cache_db_number',1),//缓存到redis的DB编号
    'app_redis_cache_key_prefix' => env('app_redis_cache_key_prefix','wechat:oplatform'),//缓存到redis时所有key的前缀
];
