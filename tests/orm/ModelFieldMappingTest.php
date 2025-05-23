<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelFieldMappingTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_field_mapping`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_field_mapping` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `user_name` varchar(32) NOT NULL,
     `user_age` int(11) NOT NULL DEFAULT '0',
     `is_active` tinyint(1) NOT NULL DEFAULT '0',
     `user_info` json DEFAULT NULL,
     `create_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_field_mapping`;');
        self::$testData = [
            ['id' => 1, 'user_name' => 'user1', 'user_age' => 25, 'is_active' => 1, 'user_info' => json_encode(['city' => 'beijing']), 'create_at' => '2023-01-01 10:00:00'],
            ['id' => 2, 'user_name' => 'user2', 'user_age' => 30, 'is_active' => 0, 'user_info' => json_encode(['city' => 'shanghai']), 'create_at' => '2023-01-02 11:00:00'],
        ];
        Db::table('test_field_mapping')->insertAll(self::$testData);
    }

    public function testBasicFieldMapping()
    {
        // 测试读取映射字段
        $item = FieldMappingModel::find(1);
        $this->assertEquals('user1', $item->name);
        $this->assertEquals(25, $item->age);
        $this->assertEquals(1, $item->active);
        $this->assertEquals(['city' => 'beijing'], $item->info);
        $this->assertEquals('2023-01-01 10:00:00', $item->createdAt);
        $this->assertEquals($item->age, $item->user_age);

        // 测试使用映射字段写入
        $newItem = FieldMappingModel::create([
            'name'      => 'user3',
            'age'       => 35,
            'active'    => 1,
            'info'      => ['city' => 'guangzhou'],
            'createdAt' => '2023-01-03 12:00:00',
        ]);

        // 验证数据库中的实际字段
        $dbItem = Db::table('test_field_mapping')->where('id', $newItem->id)->find();
        $this->assertEquals('user3', $dbItem['user_name']);
        $this->assertEquals(35, $dbItem['user_age']);
        $this->assertEquals(true, $dbItem['is_active']);
        $this->assertEquals(['city' => 'guangzhou'], json_decode($dbItem['user_info'], true));
        $this->assertEquals('2023-01-03 12:00:00', $dbItem['create_at']);
    }
}

class FieldMappingModel extends Model {
    protected $table = 'test_field_mapping';
    protected $jsonAssoc = true;
    
    // 定义字段映射，仅包含字段名称映射
    protected $mapping = [
        'user_name' => 'name',
        'user_age'  => 'age',
        'is_active' => 'active',
        'user_info' => 'info',
        'create_at' => 'createdAt',
    ];

    // 定义自定义类型转换
    protected $type = [
        'user_age'    => 'integer',
        'is_active'   => 'bool',
        'user_info'   => 'array',
        'create_at'   => 'datetime',
    ];
}