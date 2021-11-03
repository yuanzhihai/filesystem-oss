<?php
/**
 * Created by PHP@大海 [三十年河东三十年河西,莫欺少年穷.!]
 * User: yuanzhihai
 * Date: 2021/11/3
 * Time: 11:58 上午
 * Author: PHP@大海 <396751927@qq.com>
 *       江城子 . 程序员之歌
 *
 *  十年生死两茫茫，写程序，到天亮。
 *      千行代码，Bug何处藏。
 *  纵使上线又怎样，朝令改，夕断肠。
 *
 *  领导每天新想法，天天改，日日忙。
 *     相顾无言，惟有泪千行。
 *  每晚灯火阑珊处，夜难寐，加班狂。
 */

namespace yzh52521\Flysystem\Oss;

class Resource
{
    /**
     * TODO: Swoole file hook does not support `php://temp` and `php://memory`.
     */
    public static function from(string $body, string $filename = 'php://temp')
    {
        $resource = fopen($filename, 'rb+');
        if ($body !== '') {
            fwrite($resource, $body);
            fseek($resource, 0);
        }

        return $resource;
    }

    public static function fromMemory(string $body): bool
    {
        return static::from($body, 'php://memory');
    }
}
