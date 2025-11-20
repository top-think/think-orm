<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;

class StreamAdvancedTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // 确保测试表存在
        try {
            Db::execute('DROP TABLE IF EXISTS `test_stream`;');
            Db::execute("CREATE TABLE `test_stream` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              `email` varchar(255) NOT NULL DEFAULT '',
              `status` tinyint(1) NOT NULL DEFAULT 1,
              `category_id` int(11) DEFAULT 0,
              `created_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_status` (`status`),
              KEY `idx_category` (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 创建分类表
            Db::execute('DROP TABLE IF EXISTS `test_category`;');
            Db::execute("CREATE TABLE `test_category` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 插入分类数据
            Db::table('test_category')->insertAll([
                ['id' => 1, 'name' => 'Category A'],
                ['id' => 2, 'name' => 'Category B'],
                ['id' => 3, 'name' => 'Category C']
            ]);
            
            // 插入测试数据
            $data = [];
            for ($i = 1; $i <= 100; $i++) {
                $data[] = [
                    'name' => 'User ' . $i,
                    'email' => 'user' . $i . '@example.com',
                    'status' => $i % 2,
                    'category_id' => ($i % 3) + 1,
                    'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))
                ];
            }
            Db::table('test_stream')->insertAll($data);
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        try {
            Db::execute('DROP TABLE IF EXISTS `test_stream`;');
            Db::execute('DROP TABLE IF EXISTS `test_category`;');
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 测试stream方法支持排序
     */
    public function testStreamWithOrder()
    {
        $results = [];
        $count = Db::table('test_stream')
            ->where('status', 1)
            ->order('id', 'desc')
            ->limit(5)
            ->stream(function($record) use (&$results) {
                $results[] = $record['id'];
            });
        
        $this->assertEquals(5, $count);
        $this->assertEquals(5, count($results));
        
        // 验证是否按降序排列
        for ($i = 1; $i < count($results); $i++) {
            $this->assertGreaterThan($results[$i], $results[$i-1]);
        }
    }
    
    /**
     * 测试stream方法支持字段选择
     */
    public function testStreamWithField()
    {
        $results = [];
        Db::table('test_stream')
            ->field('id,name')
            ->limit(5)
            ->stream(function($record) use (&$results) {
                $results[] = $record;
            });
        
        $this->assertCount(5, $results);
        foreach ($results as $record) {
            $this->assertArrayHasKey('id', $record);
            $this->assertArrayHasKey('name', $record);
            $this->assertArrayNotHasKey('email', $record);
            $this->assertArrayNotHasKey('status', $record);
        }
    }
    
    /**
     * 测试stream方法支持连表查询
     */
    public function testStreamWithJoin()
    {
        $results = [];
        $count = Db::table('test_stream')
            ->alias('s')
            ->join('test_category c', 's.category_id = c.id')
            ->field('s.id,s.name as user_name,c.name as category_name')
            ->where('s.status', 1)
            ->limit(10)
            ->stream(function($record) use (&$results) {
                $results[] = $record;
            });
        
        $this->assertEquals(10, $count);
        $this->assertCount(10, $results);
        
        foreach ($results as $record) {
            $this->assertArrayHasKey('id', $record);
            $this->assertArrayHasKey('user_name', $record);
            $this->assertArrayHasKey('category_name', $record);
            $this->assertContains($record['category_name'], ['Category A', 'Category B', 'Category C']);
        }
    }
    
    /**
     * 测试cursor方法的相同功能
     */
    public function testCursorWithComplexQuery()
    {
        $results = [];
        $cursor = Db::table('test_stream')
            ->alias('s')
            ->join('test_category c', 's.category_id = c.id')
            ->field('s.id,s.name as user_name,c.name as category_name')
            ->where('s.status', 1)
            ->order('s.id', 'desc')
            ->limit(10)
            ->cursor();
        
        foreach ($cursor as $record) {
            $results[] = $record;
        }
        
        $this->assertCount(10, $results);
        
        // 验证排序
        for ($i = 1; $i < count($results); $i++) {
            $this->assertGreaterThan($results[$i]['id'], $results[$i-1]['id']);
        }
    }
    
    /**
     * 测试stream和cursor的一致性
     */
    public function testStreamVsCursorConsistency()
    {
        $streamResults = [];
        $cursorResults = [];
        
        // 使用stream
        Db::table('test_stream')
            ->where('status', 1)
            ->order('id', 'asc')
            ->limit(20)
            ->stream(function($record) use (&$streamResults) {
                $streamResults[] = $record['id'];
            });
        
        // 使用cursor
        $cursor = Db::table('test_stream')
            ->where('status', 1)
            ->order('id', 'asc')
            ->limit(20)
            ->cursor();
        
        foreach ($cursor as $record) {
            $cursorResults[] = $record['id'];
        }
        
        // 验证结果一致
        $this->assertEquals($streamResults, $cursorResults);
    }
}