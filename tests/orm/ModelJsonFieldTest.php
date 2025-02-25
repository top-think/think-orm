<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelJsonFieldTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_json_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_json_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `info` json DEFAULT NULL,
     `tags` json DEFAULT NULL,
     `create_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_json_model`;');
        self::$testData = [
            [
                'id' => 1,
                'name' => 'test1',
                'info' => json_encode(['age' => 18, 'city' => 'beijing']),
                'tags' => json_encode(['php', 'mysql']),
            ],
            [
                'id' => 2,
                'name' => 'test2',
                'info' => json_encode(['age' => 20, 'city' => 'shanghai']),
                'tags' => json_encode(['java', 'redis']),
            ],
        ];
    }

    public function testJsonField()
    {
        $model = new class extends Model {
            protected $table = 'test_json_model';
            protected $autoWriteTimestamp = true;
        };

        // 测试JSON字段写入
        $data = [
            'name' => 'test3',
            'info' => ['age' => 25, 'city' => 'guangzhou'],
            'tags' => ['python', 'mongodb'],
        ];
        $result = $model::create($data);

        $this->assertInstanceOf(Model::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['info']['age'], $result->info->age);
        $this->assertEquals($data['tags'], $result->tags);

        // 测试JSON字段查询
        $found = $model::where('info->age', 25)->find();
        $this->assertNotNull($found);
        $this->assertEquals($data['info']['age'], $found->info->age);

        // 测试JSON数组字段
        $withTag = $model::where('tags', 'like', '%python%')->find();
        $this->assertNotNull($withTag);
        $this->assertContains('python', $withTag->tags);

        // 测试JSON字段更新
        $updateData = ['info->age' => 26];
        $result = $model::where('id', $found->id)->update($updateData);
        $this->assertTrue($result > 0);

        $updated = $model::find($found->id);
        $this->assertEquals(26, $updated->info->age);
    }

    public function testJsonArrayOperations()
    {
        Db::table('test_json_model')->insertAll(self::$testData);

        $model = new class extends Model {
            protected $table     = 'test_json_model';
            protected $jsonAssoc = true;
        };

        // 测试whereJsonContains方法 - 简单值
        $result = $model::whereJsonContains('tags', 'php')->find();
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->id);
        $this->assertContains('php', $result->tags);

        // 测试whereJsonContains方法 - 不存在的值
        $result = $model::whereJsonContains('tags', 'python')->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 对象值
        $result = $model::whereJsonContains('info', ['city' => 'beijing'])->find();
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('beijing', $result->info['city']);

        // 测试whereJsonContains方法 - 多个条件
        $result = $model::whereJsonContains('info', ['age' => 20])
            ->whereJsonContains('tags', 'redis')
            ->find();
        $this->assertNotNull($result);
        $this->assertEquals(2, $result->id);
        $this->assertEquals(20, $result->info['age']);
        $this->assertContains('redis', $result->tags);

        // 测试whereJsonContains方法 - 无效的JSON字段
        $result = $model::whereJsonContains('name', 'test1')->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 空值
        $result = $model::whereJsonContains('tags', null)->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 复杂对象
        $model::create([
            'name' => 'test3',
            'info' => ['address' => ['city' => 'guangzhou', 'street' => 'test']],
            'tags' => ['vue', 'react']
        ]);

        $result = $model::whereJsonContains('info', ['address' => ['city' => 'guangzhou']])->find();
        $this->assertNotNull($result);
        $this->assertEquals('test3', $result->name);
        $this->assertEquals('guangzhou', $result->info['address']['city']);

        $model = new class extends Model {
            protected $table = 'test_json_model';
        };

        // 测试JSON数组追加
        $record = $model::find(1);
        $tags = $record->tags;
        $tags[] = 'nginx';
        $record->tags = $tags;
        $record->save();

        $updated = $model::find(1);
        $this->assertContains('nginx', $updated->tags);

        // 测试JSON数组条件查询
        $results = $model::where('tags', 'like', '%mysql%')->select();
        $this->assertCount(1, $results);
        $this->assertContains('mysql', $results[0]->tags);
    }

    public function testJsonFieldValidation()
    {
        $model = new class extends Model {
            protected $table = 'test_json_model';
            protected $jsonAssoc = true; // 设置JSON反序列化为数组
        };

        // 测试无效JSON数据处理
        $data = [
            'name' => 'test4',
            'info' => 'invalid json',
            'tags' => ['valid', 'array'],
        ];

        $result = $model::create($data);
        $this->assertIsArray($result->info); // 应该被转换为空数组
        $this->assertIsArray($result->tags);
        $this->assertEquals($data['tags'], $result->tags);
    }
}