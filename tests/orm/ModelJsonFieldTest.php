<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\JsonModel;
use tests\stubs\JsonObjectModel;
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
        // 测试JSON字段写入
        $data = [
            'name' => 'test3',
            'info' => ['age' => 25, 'city' => 'guangzhou'],
            'tags' => ['python', 'mongodb'],
        ];
        $result = JsonModel::create($data);

        $this->assertInstanceOf(Model::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['info'], $result->info);
        $this->assertEquals($data['tags'], $result->tags);

        // 测试JSON字段查询
        $found = JsonModel::where('info->age', 25)->find();
        $this->assertNotNull($found);
        $this->assertEquals($data['info']['age'], $found->info['age']);

        // 测试JSON数组字段
        $withTag = JsonModel::where('tags', 'like', '%python%')->find();
        $this->assertNotNull($withTag);
        $this->assertContains('python', $withTag->tags);

        // 测试JSON字段更新
        $updateData = ['info->age' => 26];
        $result = JsonModel::where('id', $found->id)->update($updateData);
        $this->assertTrue($result > 0);

        $updated = JsonModel::find($found->id);
        $this->assertEquals(26, $updated->info['age']);
    }

    public function testJsonArrayOperations()
    {
        Db::table('test_json_model')->insertAll(self::$testData);

        // 测试whereJsonContains方法 - 简单值
        $result = JsonModel::whereJsonContains('tags', 'php')->find();
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->id);
        $this->assertContains('php', $result->tags);

        // 测试whereJsonContains方法 - 不存在的值
        $result = JsonModel::whereJsonContains('tags', 'java')->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 对象值
        $result = JsonModel::whereJsonContains('info', ['city' => 'beijing'])->find();
        $this->assertNotNull($result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('beijing', $result->info['city']);

        // 测试whereJsonContains方法 - 多个条件
        $result = JsonModel::whereJsonContains('info', ['age' => 20])
            ->whereJsonContains('tags', 'redis')
            ->find();
        $this->assertNotNull($result);
        $this->assertEquals(2, $result->id);
        $this->assertEquals(20, $result->info['age']);
        $this->assertContains('redis', $result->tags);

        // 测试whereJsonContains方法 - 无效的JSON字段
        $result = JsonModel::whereJsonContains('name', 'test1')->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 空值
        $result = JsonModel::whereJsonContains('tags', null)->find();
        $this->assertNull($result);

        // 测试whereJsonContains方法 - 复杂对象
        JsonModel::create([
            'name' => 'test3',
            'info' => ['address' => ['city' => 'guangzhou', 'street' => 'test']],
            'tags' => ['vue', 'react']
        ]);

        $result = JsonModel::whereJsonContains('info', ['address' => ['city' => 'guangzhou']])->find();
        $this->assertNotNull($result);
        $this->assertEquals('test3', $result->name);
        $this->assertEquals('guangzhou', $result->info['address']['city']);

        // 测试JSON数组追加
        $record = JsonModel::find(1);
        $tags = $record->tags;
        $tags[] = 'nginx';
        $record->tags = $tags;
        $record->save();

        $updated = JsonModel::find(1);
        $this->assertContains('nginx', $updated->tags);

        // 测试JSON数组条件查询
        $results = JsonModel::where('tags', 'like', '%mysql%')->select();
        $this->assertCount(1, $results);
        $this->assertContains('mysql', $results[0]->tags);
    }
}