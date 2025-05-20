# ThinkORM

基于PHP8.0+ 和PDO实现的ORM，支持多数据库，3.0版本主要特性包括：

* 基于PDO和PHP强类型实现
* 支持原生查询和查询构造器
* 自动参数绑定和预查询
* 简洁易用的查询功能
* 强大灵活的模型用法
* 支持预载入关联查询和延迟关联查询
* 支持多数据库及动态切换
* 支持`MongoDb`
* 支持分布式及事务
* 支持断点重连
* 支持`JSON`查询
* 支持数据库日志
* 支持`PSR-16`缓存及`PSR-3`日志规范


## 安装
~~~
composer require topthink/think-orm
~~~

## 文档

详细参考 [ThinkORM开发指南](https://doc.thinkphp.cn/@think-orm)

## 参与开发

### 单元测试编写

创建创建一个名为 UserInfo 的迁移文件（以测试单元为单位来创建迁移）  

```bash
./vendor/bin/phinx create UserInfo
```

### 迁移命令

下面相关命令都是 mysql 与 pgsql 同时执行，如果环境不完整可以通过 phinx 手动执行独立的迁移命令。  

#### 执行迁移（mysql、pgsql）

```bash
composer run db-migrate
```

#### 重建，先回滚在迁移（mysql、pgsql）

```bash
composer run db-rebuild
```

#### 回滚迁移（mysql、pgsql）

```bash
composer run db-rollback
```

#### 迁移状态（mysql、pgsql）

```bash
composer run db-status
```

### 环境问题

1. 如果提示 phinx 不存在，尝试手动执行`composer bin phinx install`安装。  
