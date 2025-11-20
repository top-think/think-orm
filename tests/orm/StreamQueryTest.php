<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\DbManager;
use think\db\LazyCollection;

class StreamQueryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // 确保测试表存在
        try {
            Db::execute('DROP TABLE IF EXISTS `user`;');
            Db::execute("CREATE TABLE `user` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              `email` varchar(255) NOT NULL DEFAULT '',
              `status` tinyint(1) NOT NULL DEFAULT 1,
              `created_time` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 插入测试数据
            $data = [];
            for ($i = 1; $i <= 1000; $i++) {
                $data[] = [
                    'name' => 'User ' . $i,
                    'email' => 'user' . $i . '@example.com',
                    'status' => $i % 2,
                    'created_time' => date('Y-m-d H:i:s')
                ];
            }
            Db::table('user')->insertAll($data);
        } catch (\Exception $e) {
            // 如果表已存在或其他错误，继续执行
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        // 清理测试表
        try {
            Db::execute('DROP TABLE IF EXISTS `user`;');
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 测试基础游标查询
     */
    public function testBasicCursor()
    {
        $cursor = Db::table('user')->cursor();
        
        $this->assertInstanceOf(LazyCollection::class, $cursor);
        
        $count = 0;
        foreach ($cursor as $user) {
            $this->assertIsArray($user);
            $this->assertArrayHasKey('id', $user);
            $count++;
        }
        
        $this->assertGreaterThan(0, $count);
    }
    
    /**
     * 测试无缓冲游标查询
     */
    public function testUnbufferedCursor()
    {
        // 仅MySQL支持无缓冲查询
        $connection = Db::getConnection();
        if (!($connection instanceof \think\db\connector\Mysql)) {
            $this->markTestSkipped('Unbuffered queries are only supported in MySQL');
        }
        
        $cursor = Db::table('user')->cursor(true);
        
        $count = 0;
        $memoryStart = memory_get_usage(true);
        
        foreach ($cursor as $user) {
            $this->assertIsArray($user);
            $count++;
            
            // 验证内存使用保持稳定
            if ($count % 100 === 0) {
                $memoryNow = memory_get_usage(true);
                $memoryIncrease = $memoryNow - $memoryStart;
                
                // 内存增长应该很小（小于1MB）
                $this->assertLessThan(1024 * 1024, $memoryIncrease);
            }
        }
        
        $this->assertGreaterThan(0, $count);
    }
    
    /**
     * 测试流式处理方法
     */
    public function testStreamMethod()
    {
        $processedCount = 0;
        $result = Db::table('user')
            ->where('status', 1)
            ->stream(function($user) use (&$processedCount) {
                $this->assertIsArray($user);
                $this->assertArrayHasKey('id', $user);
                $processedCount++;
            });
        
        $this->assertEquals($processedCount, $result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * 测试MySQL无缓冲查询特性
     */
    public function testMySQLUnbufferedFeatures()
    {
        // 仅在MySQL下测试
        $connection = Db::getConnection();
        if (!($connection instanceof \think\db\connector\Mysql)) {
            $this->markTestSkipped('This test is MySQL specific');
        }
        
        // 测试unbuffered选项
        $count = 0;
        foreach (Db::table('user')->cursor(true) as $user) {
            $this->assertIsArray($user);
            $count++;
            if ($count >= 10) break;
        }
        $this->assertGreaterThan(0, $count);
        
        // 在非MySQL数据库上，unbuffered选项会被忽略
        // 不会抛出异常，只是使用普通游标
    }
    
    /**
     * 测试带条件的游标查询
     */
    public function testCursorWithConditions()
    {
        $cursor = Db::table('user')
            ->where('status', 1)
            ->field('id,name,email')
            ->order('id', 'asc')
            ->limit(10)
            ->cursor();
        
        $users = [];
        foreach ($cursor as $user) {
            $users[] = $user;
        }
        
        $this->assertCount(10, $users);
        $this->assertEquals(['id', 'name', 'email'], array_keys($users[0]));
        
        // 验证排序
        for ($i = 1; $i < count($users); $i++) {
            $this->assertGreaterThan($users[$i-1]['id'], $users[$i]['id']);
        }
    }
}