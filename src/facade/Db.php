<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\facade;

use think\Facade;

/**
 * @method static string getQueryClass()
 * @method static string getBuilderClass()
 * @method static array getFields(string $tableName)
 * @method static array getTables(string $dbName = '')
 * @method static int getFieldBindType(string $type)
 * @method static array getSchemaInfo(string $tableName, bool $force = false)
 * @method static mixed getTableInfo(array|string $tableName, string $fetch = '')
 * @method static array getTableFieldsInfo(string $tableName)
 * @method static string|array getPk(mixed $tableName)
 * @method static string getAutoInc(mixed $tableName)
 * @method static array getTableFields(mixed $tableName)
 * @method static array|string getFieldsType($tableName, string $field = null)
 * @method static array getFieldsBind(mixed $tableName)
 * @method static PDO connect(array $config = [], $linkNum = 0, $autoConnection = false)
 * @method static \think\db\BaseQuery view(array $args)
 * @method static PDO|bool getPdo()
 * @method static array query(string $sql, array $bind = [], bool $master = false)
 * @method static int execute(string $sql, array $bind = [])
 * @method static mixed transaction(callable $callback)
 * @method static void startTrans()
 * @method static void commit()
 * @method static void rollback()
 * @method static string getLastSql()
 * @method static string getError()
 * @method static \think\db\Builder getBuilder()
 * @method static \think\db\BaseQuery newQuery()
 * @method static \think\db\BaseQuery table(string $table)
 * @method static \think\db\BaseQuery name(string $name)
 * @method static int getNumRows()
 * @method static string getRealSql(string $sql, array $bind = [])
 * 
 * @see \think\DbManager
 * @mixin \think\DbManager
 */
class Db extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）.
     *
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'think\DbManager';
    }
}
