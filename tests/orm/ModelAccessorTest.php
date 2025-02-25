<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelAccessorTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_accessor_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_accessor_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `price` decimal(10,2) NOT NULL DEFAULT '0.00',
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `extra` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_accessor_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'price' => '99.99', 'status' => 1, 'extra' => 'info1'],
            ['id' => 2, 'name' => 'test2', 'price' => '199.99', 'status' => 0, 'extra' => 'info2'],
        ];
    }

    public function testBasicAccessor()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义name字段获取器
            public function getNameAttr($value)
            {
                return strtoupper($value);
            }

            // 定义status字段获取器
            public function getStatusTextAttr($value, $data)
            {
                $status = [0 => '禁用', 1 => '启用'];
                return $status[$data['status']];
            }
        };

        $data = ['name' => 'test3', 'price' => 299.99, 'status' => 1];
        $result = $model::create($data);

        $this->assertEquals('TEST3', $result->name);
        $this->assertEquals('启用', $result->status_text);
    }

    public function testBasicMutator()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义name字段修改器
            public function setNameAttr($value)
            {
                return ucfirst($value);
            }

            // 定义price字段修改器
            public function setPriceAttr($value)
            {
                return number_format($value, 2, '.', '');
            }
        };

        $data = ['name' => 'test4', 'price' => 399];
        $result = $model::create($data);

        $this->assertEquals('Test4', $result->name);
        $this->assertEquals('399.00', $result->price);
    }

    public function testCombinedAccessorMutator()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义extra字段的组合获取器和修改器
            public function getExtraAttr($value)
            {
                return json_decode($value, true);
            }

            public function setExtraAttr($value)
            {
                return json_encode($value);
            }
        };

        $extraData = ['key1' => 'value1', 'key2' => 'value2'];
        $data = ['name' => 'test5', 'extra' => $extraData];
        $result = $model::create($data);

        $this->assertIsArray($result->extra);
        $this->assertEquals($extraData, $result->extra);

        // 测试数据库中实际存储的值
        $rawData = Db::table('test_accessor_model')->where('id', $result->id)->find();
        $this->assertJson($rawData['extra']);
    }

    public function testJsonSerialization()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义price字段获取器，在序列化时转换为整数分
            public function getPriceCentAttr($value, $data)
            {
                return intval($data['price'] * 100);
            }

            // 定义追加的属性
            protected $append = ['price_cent'];
        };

        $data = ['name' => 'test6', 'price' => '599.99'];
        $result = $model::create($data);

        $jsonData = json_decode(json_encode($result), true);
        $this->assertEquals(59999, $jsonData['price_cent']);
    }

    public function testBasicSearcher()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义name字段搜索器
            public function searchNameAttr($query, $value)
            {
                $query->where('name', 'like', '%' . $value . '%');
            }

            // 定义status字段搜索器
            public function searchStatusAttr($query, $value)
            {
                $query->where('status', '=', $value);
            }
        };

        // 插入测试数据
        $model::create(['name' => 'test_search1', 'price' => '99.99', 'status' => 1]);
        $model::create(['name' => 'test_search2', 'price' => '199.99', 'status' => 0]);
        $model::create(['name' => 'other_name', 'price' => '299.99', 'status' => 1]);

        // 测试name搜索器
        $result = $model::withSearch(['name'], ['name' => 'test'])->select();
        $this->assertEquals(2, count($result));

        // 测试status搜索器
        $result = $model::withSearch(['status'], ['status' => 1])->select();
        $this->assertEquals(2, count($result));
    }

    public function testSearcherWithParams()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义带参数的price搜索器
            public function searchPriceAttr($query, $value, $data)
            {
                if (isset($data['min_price']) && isset($data['max_price'])) {
                    $query->whereBetween('price', [$data['min_price'], $data['max_price']]);
                }
            }
        };

        // 插入测试数据
        $model::create(['name' => 'product1', 'price' => '50.00', 'status' => 1]);
        $model::create(['name' => 'product2', 'price' => '150.00', 'status' => 1]);
        $model::create(['name' => 'product3', 'price' => '250.00', 'status' => 1]);

        // 测试价格范围搜索
        $result = $model::withSearch(['price'], [
            'min_price' => 100,
            'max_price' => 200
        ])->select();

        $this->assertEquals(1, count($result));
        $this->assertEquals('150.00', $result[0]->price);
    }

    public function testCombinedSearcher()
    {
        $model = new class extends Model {
            protected $table = 'test_accessor_model';

            // 定义组合搜索器
            public function searchComplexAttr($query, $value, $data)
            {
                if (!empty($data['keyword'])) {
                    $query->where('name', 'like', '%' . $data['keyword'] . '%');
                }

                if (isset($data['status'])) {
                    $query->where('status', '=', $data['status']);
                }

                if (isset($data['min_price'])) {
                    $query->where('price', '>=', $data['min_price']);
                }
            }
        };

        // 插入测试数据
        $model::create(['name' => 'test_item1', 'price' => '100.00', 'status' => 1]);
        $model::create(['name' => 'test_item2', 'price' => '200.00', 'status' => 0]);
        $model::create(['name' => 'other_item', 'price' => '150.00', 'status' => 1]);

        // 测试组合搜索
        $result = $model::withSearch(['complex'], [
            'keyword' => 'test',
            'status' => 1,
            'min_price' => 150
        ])->select();

        $this->assertEquals(0, count($result));

        $result = $model::withSearch(['complex'], [
            'keyword' => 'test',
            'status' => 1,
            'min_price' => 50
        ])->select();

        $this->assertEquals(1, count($result));
        $this->assertEquals('test_item1', $result[0]->name);
    }
}