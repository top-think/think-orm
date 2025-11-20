<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\db\LazyCollection;

class LazyQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initTable();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Db::execute("DROP TABLE IF EXISTS test_lazy");
    }

    protected function initTable()
    {
        Db::execute("DROP TABLE IF EXISTS test_lazy");
        $sql = <<<SQL
CREATE TABLE test_lazy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    status TINYINT DEFAULT 1,
    score INT DEFAULT 0
)
SQL;
        Db::execute($sql);

        // 插入测试数据
        $data = [];
        for ($i = 1; $i <= 100; $i++) {
            $data[] = [
                'name' => 'User ' . $i,
                'status' => $i % 3 == 0 ? 0 : 1,
                'score' => rand(50, 100)
            ];
        }
        Db::table('test_lazy')->insertAll($data);
    }

    public function testLazyReturnsLazyCollection()
    {
        $result = Db::table('test_lazy')->lazy();
        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function testLazyIterableAsGenerator()
    {
        $count = 0;
        $result = Db::table('test_lazy')->lazy(10);
        
        foreach ($result as $row) {
            $count++;
            if ($count >= 20) break;
        }
        
        $this->assertEquals(20, $count);
    }

    public function testLazyCollectionMap()
    {
        $names = Db::table('test_lazy')
            ->order('id')
            ->lazy(10)
            ->map(function ($row) {
                return strtoupper($row['name']);
            })
            ->take(5)
            ->toArray();

        $this->assertCount(5, $names);
        $this->assertTrue(in_array($names[0], ['USER 1', 'USER 2', 'USER 3', 'USER 4', 'USER 5']));
    }

    public function testLazyCollectionFilter()
    {
        $activeCount = 0;
        Db::table('test_lazy')
            ->lazy(10)
            ->filter(function ($row) {
                return $row['status'] == 1;
            })
            ->each(function ($row) use (&$activeCount) {
                $activeCount++;
                if ($activeCount >= 10) {
                    return false;
                }
            });

        $this->assertEquals(10, $activeCount);
    }

    public function testLazyWithLimit()
    {
        $result = Db::table('test_lazy')
            ->limit(15)
            ->lazy(5);

        $count = 0;
        foreach ($result as $row) {
            $count++;
        }

        $this->assertEquals(15, $count);
    }

    public function testLazyWithCustomColumn()
    {
        $ids = [];
        $result = Db::table('test_lazy')
            ->order('score', 'desc')
            ->lazy(10, 'score', 'desc');

        foreach ($result as $row) {
            $ids[] = $row['id'];
            if (count($ids) >= 20) break;
        }

        $this->assertCount(20, $ids);
    }
}