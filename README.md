# think-orm

基于PHP5.6+ 的ORM实现，主要特性：

- 基于ThinkPHP5.1的ORM独立封装；
- 保留了绝大部分的ThinkPHP ORM特性
- 支持Db类和模型操作

用法

composer require topthink/think-orm

Db类用法：
~~~
use think\Db;
// 数据库配置信息设置
Db::setConfig(['数据库配置参数（数组）']);
// 进行CURD操作
Db::table('user')->find();
~~~
其它操作参考TP5.1的完全开发手册数据库章节

定义模型：
~~~
<?php
namespace app\index\model;
use think\Model;
class User extends Model
{
}
~~~
代码调用：
~~~
use app\index\model\User;

$user = User::get(1);
$user->name = 'thinkphp';
$user->save();
~~~