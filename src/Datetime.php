<?php

namespace jinyicheng\tencent_oplatform;

class Datetime
{
    /**
     * 格式化时间
     * @param $datetime
     * @param string $format
     * @return false|string
     */
    public static function format($datetime, $format = 'Ymd')
    {
        return date_format(date_create($datetime), $format);
    }
}
