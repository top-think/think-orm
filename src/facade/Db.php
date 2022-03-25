<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @see \think\DbManager
 * @mixin \think\DbManager
 * @method static mixed transactionXa(callable $callback, array $dbs = [])
 * @method static mixed startTransXa(string $xid)
 * @method static mixed prepareXa(string $xid)
 * @method static mixed commitXa(string $xid)
 * @method static mixed rollbackXa(string $xid)
 * @method static mixed transaction(callable $callback)
 * @method static void startTrans()
 * @method static void commit()
 * @method static void rollback()
 */
class Db extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'think\DbManager';
    }
}
