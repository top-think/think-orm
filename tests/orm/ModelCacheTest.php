<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelCacheTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_cache_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_cache_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_cache_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'status' => 1],
            ['id' => 2, 'name' => 'test2', 'status' => 0],
            ['id' => 3, 'name' => 'test3', 'status' => 1],
        ];
        Db::table('test_cache_model')->insertAll(self::$testData);
    }

    public function testBasicCache()
    {
        $model = new CacheModel();

        // 测试单条数据缓存
        $result1 = $model::cache(true)->find(1);
        $this->assertEquals('test1', $result1->name);

        // 通过模型更新数据，验证缓存是否自动更新
        $result1->setCache(true)->save(['name' => 'modified']);

        // 验证缓存是否已自动更新
        $result2 = $model::cache(true)->find(1);
        $this->assertEquals('modified', $result2->name);

        // 验证数据库中的实际值
        $dbResult = Db::table('test_cache_model')->where('id', 1)->find();
        $this->assertEquals('modified', $dbResult['name']);
    }

    public function testCacheTag()
    {
        $model = new CacheModel();

        // 使用标签缓存数据
        $result1 = $model::cache(true, 'test_tag')->select();
        $this->assertCount(3, $result1);

        // 添加新数据
        $model::create(['name' => 'test4', 'status' => 1]);

        // 验证缓存数据
        $result2 = $model::cache(true, 'test_tag')->select();
        $this->assertCount(3, $result2);
    }

    public function testCacheWithQuery()
    {
        $model = new CacheModel();

        // 测试复杂查询缓存
        $result1 = $model::cache(true)
            ->where('status', 1)
            ->order('id', 'desc')
            ->select();
        $this->assertCount(2, $result1);

        // 添加新的状态为1的数据
        $model::create(['name' => 'test4', 'status' => 1]);

        // 验证缓存数据
        $result2 = $model::cache(true)
            ->where('status', 1)
            ->order('id', 'desc')
            ->select();
        $this->assertCount(2, $result2);
    }

    public function testCacheTime()
    {
        $model = new CacheModel();

        // 设置缓存时间为1秒
        $result1 = $model::cache(1)->find(1);
        $this->assertEquals('test1', $result1->name);

        // 修改数据
        Db::table('test_cache_model')->where('id', 1)->update(['name' => 'modified']);

        // 立即查询，应该返回缓存数据
        $result2 = $model::cache(1)->find(1);
        $this->assertEquals('test1', $result2->name);

        // 等待缓存过期
        sleep(2);

        // 再次查询，应该返回新数据
        $result3 = $model::cache(1)->find(1);
        $this->assertEquals('modified', $result3->name);
    }

    public function testCacheKey()
    {
        $model = new CacheModel();

        // 使用自定义缓存标识查询数据
        $result1 = $model::cache('custom_key_1')->where('status', 1)->select();
        $this->assertCount(2, $result1);

        // 使用相同的缓存标识，即使查询条件不同也应该返回缓存的数据
        $result2 = $model::cache('custom_key_1')->where('status', 0)->select();
        $this->assertCount(2, $result2);

        // 使用不同的缓存标识应该返回新的查询结果
        $result3 = $model::cache('custom_key_2')->where('status', 0)->select();
        $this->assertCount(1, $result3);

        // 添加新数据后，使用原缓存标识查询应该返回缓存数据
        $model::create(['name' => 'test4', 'status' => 1]);
        $result4 = $model::cache('custom_key_1')->where('status', 1)->select();
        $this->assertCount(2, $result4);

        // 清除指定标识的缓存后，查询应该返回最新数据
        $model::getCache()->delete('custom_key_1');
        $result5 = $model::cache('custom_key_1')->where('status', 1)->select();
        $this->assertCount(3, $result5);
    }
}

class CacheModel extends Model
{
    protected $table = 'test_cache_model';
}