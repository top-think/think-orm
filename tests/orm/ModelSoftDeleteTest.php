<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelSoftDeleteTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_soft_delete`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_soft_delete` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `delete_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_soft_delete`;');
        self::$testData = [
            ['id' => 1, 'name' => 'item1', 'status' => 1],
            ['id' => 2, 'name' => 'item2', 'status' => 0],
            ['id' => 3, 'name' => 'item3', 'status' => 1],
        ];
        Db::table('test_soft_delete')->insertAll(self::$testData);
    }

    public function testBasicSoftDelete()
    {
        $model = new SoftDeleteModel();

        // 测试软删除
        $item = $model::find(1);
        $this->assertNotNull($item);
        $item->delete();

        // 验证软删除后无法通过普通查询获取
        $deletedItem = $model::find(1);
        $this->assertNull($deletedItem);

        // 验证软删除字段已设置
        $rawData = Db::table('test_soft_delete')->where('id', 1)->find();
        $this->assertNotNull($rawData['delete_time']);
    }

    public function testSoftDeleteQuery()
    {
        $model = new SoftDeleteModel();

        // 软删除一些数据
        $model::find(1)->delete();
        $model::find(2)->delete();

        // 测试默认查询不包含软删除数据
        $list = $model::select();
        $this->assertEquals(1, count($list));

        // 测试包含软删除数据的查询
        $listWithTrashed = $model::withTrashed()->select();
        $this->assertEquals(3, count($listWithTrashed));

        // 测试仅查询软删除数据
        $trashedOnly = $model::onlyTrashed()->select();
        $this->assertEquals(2, count($trashedOnly));
    }

    public function testSoftDeleteRestore()
    {
        $model = new SoftDeleteModel();

        // 软删除一条数据
        $item = $model::find(1);
        $item->delete();

        // 恢复软删除的数据
        $trashedItem = $model::onlyTrashed()->find(1);
        $this->assertNotNull($trashedItem);
        $trashedItem->restore();

        // 验证恢复后可以正常查询
        $restoredItem = $model::find(1);
        $this->assertNotNull($restoredItem);
        $this->assertNull($restoredItem->delete_time);
    }

    public function testSoftDeleteScope()
    {
        $model = new SoftDeleteModel();

        // 软删除一条激活状态的数据
        $model::find(1)->delete();

        // 测试查询作用域和软删除的结合
        $activeItems = $model::active()->select();
        $this->assertEquals(1, count($activeItems));

        // 测试包含软删除数据的作用域查询
        $allActiveItems = $model::withTrashed()->active()->select();
        $this->assertEquals(2, count($allActiveItems));
    }

    public function testBatchSoftDelete()
    {
        $model = new SoftDeleteModel();

        // 批量软删除
        $model::where('status', 1)->delete();

        // 验证软删除结果
        $remainingItems = $model::select();
        $this->assertEquals(1, count($remainingItems));
        $this->assertEquals(0, $remainingItems[0]->status);

        // 验证软删除数据仍在数据库中
        $allItems = $model::withTrashed()->select();
        $this->assertEquals(3, count($allItems));
    }
}

class SoftDeleteModel extends Model {
    protected $table = 'test_soft_delete';
    use \think\model\concern\SoftDelete;
    protected $deleteTime = 'delete_time';

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }    
}