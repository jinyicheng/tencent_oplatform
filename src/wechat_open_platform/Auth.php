<?php

namespace jinyicheng\tencent_oplatform\wechat_open_platform;

use jinyicheng\redis\Redis;
use jinyicheng\tencent_oplatform\OpenPlatformException;
use jinyicheng\tencent_oplatform\Request;

/**
 * 登录/用户信息/接口调用凭证
 * Class Auth
 * @package jinyicheng\tencent_oplatform\wechat_open_platform
 */
class Auth
{
    use CommonTrait;

    private $open_id = null;

    private $access_token = null;

    private $auto_create_access_token=false;

    /**
     * 获取access_token
     * @param $open_id
     * @return mixed
     */
    public function getAccessToken($open_id = null)
    {
        if (is_null($open_id)) {
            $open_id = $this->getOpenId();
        }
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $open_id;
        $access_token = $redis->get($access_token_key);
        $this->checkAccessToken($open_id, $access_token);
        return $this->access_token;
    }

    /**
     * @return null
     */
    public function getOpenId()
    {
        return $this->open_id;
    }

    /**
     * 检查access_token是否有效
     * @param $open_id
     * @param $access_token
     * @return bool
     */
    private function checkAccessToken($open_id, $access_token)
    {
        try {
            /**
             * 请求接口
             */
            Request::get(
                "https://api.weixin.qq.com/sns/auth",
                [
                    'open_id' => $open_id,
                    'access_token' => $access_token
                ],
                [],
                2000
            );
        } catch (OpenPlatformException $exception) {
            if ($exception->getCode() == 40003) {
                return false;
            }
        }
        return true;
    }

//    /**
//     * 获取open_id
//     * @return mixed
//     */
//    public function getOpenId()
//    {
//        return $this->open_id;
//    }

    /**
     * 创建access_token
     * @param string $code
     * @return string
     * @throws OpenPlatformException
     * @document https://developers.weixin.qq.com/doc/oplatform/Mobile_App/WeChat_Login/Authorized_API_call_UnionID.html
     */
    public function createAccessToken($code)
    {
        /**
         * 请求接口
         */
        $response = Request::get(
            "https://api.weixin.qq.com/sns/oauth2/access_token",
            [
                'appid' => $this->options['app_id'],
                'secret' => $this->options['app_secret'],
                'grant_type' => 'authorization_code',
                'code' => $code
            ],
            [],
            2000
        );
        /**
         * 缓存access_token，refresh_token
         */
        $this->cacheAccessToken($response['openid'], $response['access_token'], $response['expires_in']);
        $this->cacheRefreshToken($response['openid'], $response['refresh_token']);
        $this->open_id = $response['openid'];
        return $response['access_token'];
    }

    /**
     * 缓存access_token
     * @param $openid
     * @param $access_token
     * @param $expires_in
     */
    private function cacheAccessToken($openid, $access_token, $expires_in)
    {
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $openid;
        $redis->setex($access_token_key, $access_token, $expires_in);
    }

    /**
     * 缓存refresh_token
     * @param $openid
     * @param $refresh_token
     */
    private function cacheRefreshToken($openid, $refresh_token)
    {
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $openid;
        $redis->set($access_token_key, $refresh_token);
    }

    /**
     * 刷新access_token
     * @param $openid
     * @return Auth
     * @throws OpenPlatformException
     */
    public function refreshAccessToken($openid)
    {
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $refresh_token_key = $this->options['app_redis_cache_key_prefix'] . ':refresh_token:' . $this->options['app_id'] . ':' . $openid;
        $refresh_token = $redis->get($refresh_token_key);
        /**
         * 请求接口
         */
        $response = Request::get(
            "https://api.weixin.qq.com/sns/oauth2/refresh_token",
            [
                'appid' => $this->options['app_id'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token
            ],
            [],
            2000
        );
        $this->cacheAccessToken();
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $response['openid'];
        $redis->hMSet($access_token_key, $response);
        $redis->expire($access_token_key, $response['expires_in']);
        return $this;
    }

    /**
     * 设置自动创建access_token
     * @param bool $auto_create_access_token
     * @return Auth
     */
    public function setAutoCreateAccessToken($auto_create_access_token)
    {
        $this->auto_create_access_token = $auto_create_access_token;
        return $this;
    }

    /**
     * 获取refresh_token缓存
     * @param $openid
     * @return mixed
     */
    private function getRefreshToken($openid)
    {
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $refresh_token_key = $this->options['app_redis_cache_key_prefix'] . ':refresh_token:' . $this->options['app_id'] . ':' . $openid;
        return $redis->get($refresh_token_key);
    }

    /**
     * 删除refresh_token缓存
     * @param $openid
     */
    private function removeRefreshToken($openid)
    {
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $refresh_token_key = $this->options['app_redis_cache_key_prefix'] . ':refresh_token:' . $this->options['app_id'] . ':' . $openid;
        $redis->del($refresh_token_key);
    }
}
