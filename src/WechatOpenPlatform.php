<?php

namespace jinyicheng\tencent_oplatform;

use jinyicheng\tencent_oplatform\wechat_open_platform\Auth;
use jinyicheng\tencent_oplatform\wechat_open_platform\CommonTrait;

class WechatOpenPlatform
{
    use CommonTrait;

    /**
     * 登录/用户信息/接口调用凭证
     * @return Auth
     */
    public function auth()
    {
        return Auth::getInstance($this->options);
    }
}
