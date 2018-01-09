# think-orm

基于PHP5.6+ 的ORM实现，主要特性：

- 基于ThinkPHP5.1的ORM独立封装
- 支持Mysql、Pgsql、Sqlite、SqlServer、Oracle和Mongodb
- 支持Db类和查询构造器
- 支持事务
- 支持模型和关联

适用于不使用ThinkPHP框架的开发者。

安装
~~~
composer require topthink/think-orm
~~~

Db类用法：
~~~php
use think\Db;
// 数据库配置信息设置（全局有效）
Db::setConfig(['数据库配置参数（数组）']);
// 进行CURD操作
Db::table('user')
	->data(['name'=>'thinkphp','email'=>'thinkphp@qq.com'])
	->insert();	
Db::table('user')->find();
Db::table('user')
	->where('id','>',10)
	->order('id','desc')
	->limit(10)
	->select();
Db::table('user')
	->where('id',10)
	->update(['name'=>'test']);	
Db::table('user')
	->where('id',10)
	->delete();
~~~

Db类增加的（静态）方法包括：
- `setConfig` 设置全局配置信息
- `getConfig` 获取数据库配置信息
- `setQuery`  设置数据库Query类名称
- `setCacheHandler` 设置缓存对象Handler（必须支持get、set及rm方法）
- `getSqlLog` 用于获取当前请求的SQL日志信息（包含连接信息）

其它操作参考TP5.1的完全开发手册[数据库](https://www.kancloud.cn/manual/thinkphp5_1/353998)章节

定义模型：
~~~php
namespace app\index\model;
use think\Model;
class User extends Model
{
}
~~~

代码调用：

~~~php
use app\index\model\User;

$user = User::get(1);
$user->name = 'thinkphp';
$user->save();
~~~

## Db类和模型对比使用
####   :white_check_mark:   创建Create
* Db用法

    ```php
    Db::table('user')
        ->insert([
            'name'  => 'thinkphp',
            'email' => 'thinkphp@qq.com',
        ]);
    ```
* 模型用法

    ```php
   $user        = new User;
   $user->name  = 'thinkphp';
   $user->email = 'thinkphp@qq.com';
   $user->save();
    ```
* 或者批量设置

    ```php
    $user = new User;
    $user->save([
        'name'  => 'thinkphp',
        'email' => 'thinkphp@qq.com',
    ]);
    ```
####  :white_check_mark:  读取Read
* Db用法

    ```php
    $user = Db::table('user')
        ->where('id', 1)
        ->find();
    //  或者
    $user = Db::table('user')
        ->find(1);
    echo $user['id'];
    echo $user['name'];
    ```
* 模型用法

    ```php
    $user = User::get(1);
    echo $user->id;
    echo $user->name;
    ```
* 模型实现读取多个记录

    ```php
    // 查询用户数据集
    $users = User::where('id', '>', 1)
        ->limit(5)
        ->select();
    
    // 遍历读取用户数据
    foreach ($users as $user) {
        echo $user->id;
        echo $user->name;
    }
    ```
####  :white_check_mark:  更新Update
* Db用法

    ```php
    Db::table('user')
        ->where('id', 1)
        ->update([
            'name'  => 'topthink',
            'email' => 'topthink@qq.com',
        ]);
    ```
* 模型用法

    ```php
    $user        = User::get(1);
    $user->name  = 'topthink';
    $user->email = 'topthink@qq.com';
    $user->save();
    ```
* 或者使用

    ```php
    $user = User::get(1);
    $user->save([
        'name'  => 'topthink',
        'email' => 'topthink@qq.com',
    ]);
    ```
* 静态调用

    ```php
    User::update([
        'name'  => 'topthink',
        'email' => 'topthink@qq.com',
    ], ['id' => 1]);
    ```
####  :white_check_mark:  删除Delete
* Db用法

    ```php
    Db::table('user')->delete(1);
    ```
* 模型用法

    ```php
    $user = User::get(1);
    $user->delete();
    ```
* 或者静态实现

    ```php
   User::destroy(1);
    ```
* 静态调用

    ```php
    User::update([
        'name'  => 'topthink',
        'email' => 'topthink@qq.com',
    ], ['id' => 1]);
    ```
* destroy方法支持删除指定主键或者查询条件的数据

    ```php
    // 根据主键删除多个数据
    User::destroy([1, 2, 3]);
    // 指定条件删除数据
    User::destroy([
        'status' => 0,
    ]);
    // 使用闭包条件
    User::destroy(function ($query) {
        $query->where('id', '>', 0)
            ->where('status', 0);
    });
    ```
更多模型用法可以参考5.1完全开发手册的[模型](https://www.kancloud.cn/manual/thinkphp5_1/354041)章节
