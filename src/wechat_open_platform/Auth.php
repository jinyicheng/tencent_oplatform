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

    private $open_id = '';

    private $access_token = '';

    /**
     * 检查access_token是否有效
     * @param $open_id
     * @param $access_token
     * @throws OpenPlatformException
     */
    private function checkAccessToken($open_id, $access_token)
    {
        /**
         * 请求接口检查access_token，如果无效将被异常拦截，此时将会尝试刷新access_token
         */
        try {
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
            $this->refreshAccessToken($open_id);
        }

    }

    /**
     * 获取access_token
     * @param $open_id
     * @return mixed
     * @throws OpenPlatformException
     */
    public function getAccessToken($open_id)
    {
        if (!is_null($open_id)) {
            $this->open_id = $open_id;
        }
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $open_id;
        $this->access_token = $redis->get($access_token_key);
        /**
         * 检查access_token是否有效
         */
        $this->checkAccessToken($this->open_id, $this->access_token);
        /**
         * 返回access_token
         */
        return $this->access_token;
    }

    /**
     * @return string
     */
    public function getOpenId()
    {
        return $this->open_id;
    }

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
        return $this;
    }

    /**
     * 缓存access_token
     * @param $openid
     * @param $access_token
     * @param $expires_in
     */
    private function cacheAccessToken($openid, $access_token, $expires_in)
    {
        $this->access_token = $access_token;
        /**
         * 尝试从redis中获取access_token
         */
        $redis = Redis::db($this->options['app_redis_cache_db_number']);
        $access_token_key = $this->options['app_redis_cache_key_prefix'] . ':access_token:' . $this->options['app_id'] . ':' . $openid;
        $redis->setex($access_token_key, $expires_in, $access_token);
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
        $refresh_token_key = $this->options['app_redis_cache_key_prefix'] . ':refresh_token:' . $this->options['app_id'] . ':' . $openid;
        $redis->set($refresh_token_key, $refresh_token);
    }

    /**
     * 刷新access_token
     * @param $openid
     * @return Auth
     * @throws OpenPlatformException
     */
    public function refreshAccessToken($openid)
    {
        $refresh_token = $this->getRefreshToken($openid);
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
        $this->cacheAccessToken($openid, $response['access_token'], $response['expires_in']);
        $this->removeRefreshToken($openid);
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

    /**
     * 获取用户信息
     * @param $open_id
     * @return array
     * @throws OpenPlatformException
     */
    public function getUserInfo($open_id = null)
    {
        if (!is_null($open_id)) {
            $this->open_id = $open_id;
        }
        /**
         * 根据用户open_id获取access_token
         */
        $access_token = $this->getAccessToken($this->open_id);
        /**
         * 请求接口
         */
        return Request::get(
            "https://api.weixin.qq.com/sns/userinfo",
            [
                'access_token' => $access_token,
                'openid' => $this->open_id,
                'lang' => 'zh_CN'
            ],
            [],
            2000
        );
    }
}
