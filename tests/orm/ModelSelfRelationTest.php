<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelSelfRelationTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_category`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_category` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `pid` int(10) UNSIGNED NOT NULL DEFAULT '0',
     `name` varchar(32) NOT NULL,
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_category`;');
        self::$testData = [
            ['id' => 1, 'pid' => 0, 'name' => '电子产品'],
            ['id' => 2, 'pid' => 1, 'name' => '手机'],
            ['id' => 3, 'pid' => 1, 'name' => '电脑'],
            ['id' => 4, 'pid' => 2, 'name' => '智能手机'],
            ['id' => 5, 'pid' => 2, 'name' => '功能手机'],
            ['id' => 6, 'pid' => 3, 'name' => '笔记本'],
            ['id' => 7, 'pid' => 3, 'name' => '台式机'],
        ];
    }

    public function testHasChildren()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试获取子分类
        $category = Category::find(1);
        $this->assertNotEmpty($category->children);
        $this->assertCount(2, $category->children);
        $this->assertEquals(['手机', '电脑'], array_column($category->children->toArray(), 'name'));

        // 测试获取二级子分类
        $phones = Category::find(2);
        $this->assertNotEmpty($phones->children);
        $this->assertCount(2, $phones->children);
        $this->assertEquals(['智能手机', '功能手机'], array_column($phones->children->toArray(), 'name'));
    }

    public function testBelongsToParent()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试获取父分类
        $smartphone = Category::find(4);
        $this->assertNotEmpty($smartphone->parent);
        $this->assertEquals('手机', $smartphone->parent->name);

        // 测试获取祖父分类
        $this->assertNotEmpty($smartphone->parent->parent);
        $this->assertEquals('电子产品', $smartphone->parent->parent->name);
    }

    public function testDeleteWithChildren()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试删除分类及其子分类
        $category = Category::find(2);
        $category->delete();

        // 验证删除结果
        $this->assertNull(Category::find(2));
        $this->assertNull(Category::find(4));
        $this->assertNull(Category::find(5));

        // 验证其他分类未受影响
        $this->assertNotNull(Category::find(1));
        $this->assertNotNull(Category::find(3));
    }

    public function testWithRelation()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试基础的with关联预加载
        $categories = Category::with('children')->where('pid', 0)->select();
        $this->assertCount(1, $categories);
        $this->assertNotEmpty($categories[0]->children);
        $this->assertCount(2, $categories[0]->children);

        // 测试带条件的with关联预加载
        $categories = Category::with(['children' => function ($query) {
            $query->where('name', 'like', '%手机%');
        }])->where('pid', 0)->select();
        $this->assertCount(1, $categories);
        $this->assertCount(1, $categories[0]->children);
        $this->assertEquals('手机', $categories[0]->children[0]->name);

        // 测试多级嵌套的with关联预加载
        $categories = Category::with(['children.children'])->where('pid', 0)->select();
        $this->assertCount(1, $categories);
        $this->assertCount(2, $categories[0]->children);
        $this->assertCount(2, $categories[0]->children[0]->children);
        $this->assertCount(2, $categories[0]->children[1]->children);
    }

    public function testWithCountRelation()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试基础的withCount统计
        $categories = Category::withCount('children')->where('pid', 0)->select();
        $this->assertCount(1, $categories);
        $this->assertEquals(2, $categories[0]->children_count);

        // 测试带条件的withCount统计
        $categories = Category::withCount(['children' => function ($query) {
            $query->where('name', 'like', '%手机%');
        }])->where('pid', 0)->select();
        $this->assertCount(1, $categories);
        $this->assertEquals(1, $categories[0]->children_count);

        // 测试多个分类的withCount统计
        $categories = Category::withCount('children')->where('pid', '<>', 0)->select();
        $this->assertCount(6, $categories);
        foreach ($categories as $category) {
            if (2 == $category->id || 3 == $category->id) {
                $this->assertEquals(2, $category->children_count);
            } else {
                $this->assertEquals(0, $category->children_count);
            }
        }
    }

    public function testHasQuery()
    {
        Db::table('test_category')->insertAll(self::$testData);

        Category::create(['id' => 8, 'pid' => 3, 'name' => '一体机']);
        // 测试基础的has查询
        $categories = Category::has('children')->select();
        $this->assertCount(3, $categories);
        $this->assertEquals(['电子产品', '手机', '电脑'], array_column($categories->toArray(), 'name'));

        // 测试has查询带数量条件
        $categories = Category::has('children', '>=', 3)->select();
        $this->assertCount(1, $categories);
        $this->assertEquals('电脑', $categories[0]->name);
    }

    public function testHasWhereQuery()
    {
        Db::table('test_category')->insertAll(self::$testData);

        // 测试基础的hasWhere查询
        $categories = Category::hasWhere('children', ['name' => '手机'])->select();
        $this->assertCount(1, $categories);
        $this->assertEquals('电子产品', $categories[0]->name);

        // 测试hasWhere查询带闭包条件
        $categories = Category::hasWhere('children', function ($query) {
            $query->where('name', 'like', '%手机%');
        })->select();
        $this->assertCount(2, $categories);
        $this->assertEquals(['电子产品', '手机'], array_column($categories->toArray(), 'name'));
    }    
}

class Category extends Model
{
    protected $table              = 'test_category';
    protected $autoWriteTimestamp = true;

    /**
     * 定义与子分类的关联
     */
    public function children()
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }

    /**
     * 定义与父分类的关联
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    /**
     * 删除时同时删除子分类
     */
    public function delete():bool
    {
        $this->children()->delete();
        return parent::delete();
    }

}
