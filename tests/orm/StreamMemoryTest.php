<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;

class StreamMemoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            Db::execute('DROP TABLE IF EXISTS `memory_test`;');
            Db::execute("CREATE TABLE `memory_test` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              `content` text,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 插入较大的数据以便观察内存差异
            $data = [];
            for ($i = 1; $i <= 1000; $i++) {
                $data[] = [
                    'name' => 'Record ' . $i,
                    'content' => str_repeat('This is test content ' . $i . '. ', 100) // 约2KB per record
                ];
                
                // 每100条插入一次
                if ($i % 100 === 0) {
                    Db::table('memory_test')->insertAll($data);
                    $data = [];
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        try {
            Db::execute('DROP TABLE IF EXISTS `memory_test`;');
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 测试不同查询方法的内存使用模式
     */
    public function testMemoryUsagePatterns()
    {
        $results = [];
        
        // 1. Select - 一次性加载所有数据
        gc_collect_cycles();
        $memStart = memory_get_usage();
        $selectData = Db::table('memory_test')->limit(200)->select();
        $selectMemory = memory_get_usage() - $memStart;
        $results['select'] = [
            'memory' => $selectMemory,
            'count' => count($selectData)
        ];
        unset($selectData);
        gc_collect_cycles();
        
        // 2. Cursor - 逐条处理
        $memStart = memory_get_usage();
        $cursorCount = 0;
        $cursorPeakMemory = 0;
        foreach (Db::table('memory_test')->limit(200)->cursor() as $row) {
            $cursorCount++;
            // 模拟处理但不保存数据
            $processed = strlen($row['content']);
            // 记录峰值内存
            $currentMem = memory_get_usage() - $memStart;
            $cursorPeakMemory = max($cursorPeakMemory, $currentMem);
        }
        $results['cursor'] = [
            'memory' => $cursorPeakMemory,
            'count' => $cursorCount
        ];
        gc_collect_cycles();
        
        // 3. Lazy - 分批处理
        $memStart = memory_get_usage();
        $lazyCount = 0;
        $lazyPeakMemory = 0;
        $batchCount = 0;
        foreach (Db::table('memory_test')->lazy(50) as $row) {
            $lazyCount++;
            // 模拟处理
            $processed = strlen($row['content']);
            // 每批结束时记录内存
            if ($lazyCount % 50 === 0) {
                $batchCount++;
            }
            $currentMem = memory_get_usage() - $memStart;
            $lazyPeakMemory = max($lazyPeakMemory, $currentMem);
            if ($lazyCount >= 200) break;
        }
        $results['lazy'] = [
            'memory' => $lazyPeakMemory,
            'count' => $lazyCount,
            'batches' => $batchCount
        ];
        
        // 输出结果
        echo "\n=== Memory Usage Comparison ===\n";
        echo sprintf("Select (all at once): %.2f MB for %d records\n", 
            $results['select']['memory'] / 1024 / 1024,
            $results['select']['count']
        );
        echo sprintf("Cursor (one by one): %.2f MB peak for %d records\n", 
            $results['cursor']['memory'] / 1024 / 1024,
            $results['cursor']['count']
        );
        echo sprintf("Lazy (batch of 50): %.2f MB peak for %d records in %d batches\n", 
            $results['lazy']['memory'] / 1024 / 1024,
            $results['lazy']['count'],
            $results['lazy']['batches']
        );
        
        // 验证
        $this->assertEquals(200, $results['select']['count']);
        $this->assertEquals(200, $results['cursor']['count']);
        $this->assertEquals(200, $results['lazy']['count']);
    }
    
    /**
     * 测试处理大数据时的内存差异
     */
    public function testLargeDataProcessing()
    {
        $processedData = [];
        
        // 使用 cursor 处理，只保存必要的数据
        $cursorMemStart = memory_get_usage();
        foreach (Db::table('memory_test')->cursor() as $row) {
            // 只保存处理后的结果，不保存原始数据
            $processedData[$row['id']] = substr($row['name'], 0, 10);
            if (count($processedData) >= 500) break;
        }
        $cursorMemUsed = memory_get_usage() - $cursorMemStart;
        
        $processedData = []; // 清空
        
        // 使用 lazy 处理
        $lazyMemStart = memory_get_usage();
        foreach (Db::table('memory_test')->lazy(100) as $row) {
            $processedData[$row['id']] = substr($row['name'], 0, 10);
            if (count($processedData) >= 500) break;
        }
        $lazyMemUsed = memory_get_usage() - $lazyMemStart;
        
        echo sprintf(
            "\nProcessing 500 records with data extraction:\n" .
            "- Cursor: %.2f MB\n" .
            "- Lazy (batch 100): %.2f MB\n",
            $cursorMemUsed / 1024 / 1024,
            $lazyMemUsed / 1024 / 1024
        );
        
        $this->assertCount(500, $processedData);
    }
}