<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\Query;
use think\Model;
use think\model\Collection;
use think\model\LazyCollection;

/**
 * LazyCollection单元测试类
 */
class LazyCollectionTest extends TestCase
{
    /**
     * 测试基本的生成器创建
     */
    public function testCreateFromGenerator()
    {
        $generator = function () {
            for ($i = 1; $i <= 5; $i++) {
                yield $i;
            }
        };

        $lazy = new LazyCollection($generator);
        $this->assertInstanceOf(LazyCollection::class, $lazy);

        $result = [];
        foreach ($lazy as $value) {
            $result[] = $value;
        }

        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    /**
     * 测试从数组创建
     */
    public function testMakeFromArray()
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $lazy = LazyCollection::make($data);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $this->assertEquals($data, $lazy->toArray());
    }

    /**
     * 测试map方法
     */
    public function testMap()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $result = $lazy->map(function ($value) {
            return $value * 2;
        });

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([2, 4, 6, 8, 10], $result->toArray());
    }

    /**
     * 测试filter方法
     */
    public function testFilter()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5, 6]);
        $result = $lazy->filter(function ($value) {
            return $value % 2 == 0;
        });

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([1 => 2, 3 => 4, 5 => 6], $result->toArray());
    }

    /**
     * 测试take方法
     */
    public function testTake()
    {
        $lazy = LazyCollection::make(range(1, 10));
        $result = $lazy->take(5);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([1, 2, 3, 4, 5], $result->toArray());
    }

    /**
     * 测试skip方法
     */
    public function testSkip()
    {
        $lazy = LazyCollection::make(range(1, 10));
        $result = $lazy->skip(5);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([5 => 6, 6 => 7, 7 => 8, 8 => 9, 9 => 10], $result->toArray());
    }

    /**
     * 测试flatten方法
     */
    public function testFlatten()
    {
        $data = [
            [1, 2, 3],
            [4, 5, 6],
            [7, [8, 9]]
        ];

        $lazy = LazyCollection::make($data);
        $result = $lazy->flatten(1);

        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, [8, 9]], $result->toArray());

        // 深度扁平化
        $result = $lazy->flatten();
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9], $result->toArray());
    }

    /**
     * 测试count方法
     */
    public function testCount()
    {
        $lazy = LazyCollection::make(range(1, 100));
        $this->assertEquals(100, $lazy->count());

        // 测试缓存机制
        $this->assertEquals(100, $lazy->count());
    }

    /**
     * 测试isEmpty方法
     */
    public function testIsEmpty()
    {
        $lazy = LazyCollection::make([]);
        $this->assertTrue($lazy->isEmpty());

        $lazy = LazyCollection::make([1, 2, 3]);
        $this->assertFalse($lazy->isEmpty());
    }

    /**
     * 测试first和last方法
     */
    public function testFirstAndLast()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(1, $lazy->first());
        $this->assertEquals(5, $lazy->last());

        // 测试带回调的first
        $result = $lazy->first(function ($value) {
            return $value > 3;
        });
        $this->assertEquals(4, $result);

        // 测试默认值
        $empty = LazyCollection::make([]);
        $this->assertEquals('default', $empty->first(null, 'default'));
    }

    /**
     * 测试group方法
     */
    public function testGroup()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        $lazy = LazyCollection::make($data);
        $grouped = $lazy->group('age');

        $result = [];
        foreach ($grouped as $key => $group) {
            $this->assertInstanceOf(Collection::class, $group);
            $result[$key] = $group->toArray();
        }

        $this->assertArrayHasKey(25, $result);
        $this->assertArrayHasKey(30, $result);
        $this->assertCount(2, $result[25]);
        $this->assertCount(1, $result[30]);
    }

    /**
     * 测试reduce方法
     */
    public function testReduce()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $sum = $lazy->reduce(function ($carry, $item) {
            return $carry + $item;
        }, 0);

        $this->assertEquals(15, $sum);
    }

    /**
     * 测试reverse方法
     */
    public function testReverse()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $result = $lazy->reverse();

        $this->assertEquals([4 => 5, 3 => 4, 2 => 3, 1 => 2, 0 => 1], $result->toArray());
    }


    /**
     * 测试when方法
     */
    public function testWhen()
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $result = $lazy->when(true, function ($collection) {
            return $collection->map(function ($item) {
                return $item * 2;
            });
        });

        $this->assertEquals([2, 4, 6], $result->toArray());

        // 测试条件为false
        $result = $lazy->when(false, function ($collection) {
            return $collection->map(function ($item) {
                return $item * 2;
            });
        });

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    /**
     * 测试JSON序列化
     */
    public function testJsonSerialize()
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => 2]);
        $json = json_encode($lazy);

        $this->assertEquals('{"a":1,"b":2}', $json);
    }

    /**
     * 测试内存效率
     */
    public function testMemoryEfficiency()
    {
        $count = 0;
        $generator = function () use (&$count) {
            for ($i = 1; $i <= 1000000; $i++) {
                $count++;
                yield $i;
            }
        };

        $lazy = new LazyCollection($generator);
        
        // 只获取前10个元素
        $result = $lazy->take(10)->toArray();
        
        // 确保生成器只生成了10个元素，而不是全部
        $this->assertEquals(11, $count);
        $this->assertEquals(range(1, 10), $result);
    }

    /**
     * 测试链式操作
     */
    public function testChaining()
    {
        $lazy = LazyCollection::make(range(1, 20));

        $result = $lazy
            ->filter(function ($value) {
                return $value % 2 == 0;
            })
            ->map(function ($value) {
                return $value * 2;
            })
            ->take(5)
            ->toArray();

        $this->assertEquals([1 => 4, 3 => 8, 5 => 12, 7 => 16, 9 => 20], $result);
    }

    /**
     * 测试异常处理
     */
    public function testExceptions()
    {
        $this->expectException(\InvalidArgumentException::class);
        new LazyCollection('invalid');
    }

    /**
     * 测试take限制验证
     */
    public function testTakeLimitValidation()
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $this->expectException(\InvalidArgumentException::class);
        $lazy->take(-1);
    }

    /**
     * 测试each方法
     */
    public function testEach()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $result = [];
        
        $lazy->each(function ($value, $key) use (&$result) {
            $result[$key] = $value * 2;
        });
        
        $this->assertEquals([2, 4, 6, 8, 10], $result);
        
        // 测试提前终止
        $result = [];
        $lazy->each(function ($value, $key) use (&$result) {
            $result[$key] = $value;
            if ($value >= 3) {
                return false;
            }
        });
        
        $this->assertEquals([1, 2, 3], $result);
    }


    /**
     * 测试sort方法
     */
    public function testSort()
    {
        $lazy = LazyCollection::make([3, 1, 4, 1, 5, 9, 2, 6]);
        $result = $lazy->sort();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals([1, 1, 2, 3, 4, 5, 6, 9], $result->values()->toArray());
        
        // 测试自定义排序
        $lazy = LazyCollection::make(['apple', 'banana', 'cherry']);
        $result = $lazy->sort(function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        
        $this->assertEquals(['banana', 'cherry', 'apple'], $result->values()->toArray());
    }

    /**
     * 测试order方法
     */
    public function testOrder()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = $lazy->order('age');
        
        $this->assertInstanceOf(Collection::class, $result);
        $ordered = array_values($result->toArray());
        $this->assertEquals('Bob', $ordered[0]['name']);
        $this->assertEquals('John', $ordered[1]['name']);
        $this->assertEquals('Jane', $ordered[2]['name']);
        
        // 测试降序
        $result = $lazy->order('age', 'desc');
        $ordered = array_values($result->toArray());
        $this->assertEquals('Jane', $ordered[0]['name']);
        $this->assertEquals('John', $ordered[1]['name']);
        $this->assertEquals('Bob', $ordered[2]['name']);
    }

    /**
     * 测试where方法
     */
    public function testWhere()
    {
        $data = [
            ['name' => 'John', 'age' => 25, 'active' => true],
            ['name' => 'Jane', 'age' => 30, 'active' => false],
            ['name' => 'Bob', 'age' => 25, 'active' => true],
        ];
        
        $lazy = LazyCollection::make($data);
        
        // 测试等于
        $result = array_values($lazy->where('age', 25)->toArray());
        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
        
        // 测试不等于
        $result = array_values($lazy->where('age', '!=', 25)->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
        
        // 测试大于
        $result = array_values($lazy->where('age', '>', 25)->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
        
        // 测试小于等于
        $result = $lazy->where('age', '<=', 25)->toArray();
        $this->assertCount(2, $result);
        
        // 测试严格等于
        $result = $lazy->where('active', '===', true)->toArray();
        $this->assertCount(2, $result);
        
        // 测试IN
        $result = $lazy->where('age', 'in', [25, 30])->toArray();
        $this->assertCount(3, $result);
        
        // 测试NOT IN
        $result = $lazy->where('age', 'not in', [30])->toArray();
        $this->assertCount(2, $result);
        
        // 测试BETWEEN
        $result = $lazy->where('age', 'between', [20, 27])->toArray();
        $this->assertCount(2, $result);
        
        // 测试NOT BETWEEN
        $result = array_values($lazy->where('age', 'not between', [20, 27])->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
        
        // 测试LIKE
        $result = $lazy->where('name', 'like', 'J')->toArray();
        $this->assertCount(2, $result);
        
        // 测试NOT LIKE
        $result = array_values($lazy->where('name', 'not like', 'J')->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('Bob', $result[0]['name']);
        
        // 测试START
        $result = $lazy->where('name', 'start', 'J')->toArray();
        $this->assertCount(2, $result);
        
        // 测试END
        $result = array_values($lazy->where('name', 'end', 'n')->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('John', $result[0]['name']);
    }

    /**
     * 测试whereLike方法
     */
    public function testWhereLike()
    {
        $data = [
            ['name' => 'John', 'city' => 'New York'],
            ['name' => 'Jane', 'city' => 'new jersey'],
            ['name' => 'Bob', 'city' => 'BOSTON'],
        ];
        
        $lazy = LazyCollection::make($data);
        
        // 测试区分大小写
        $result = array_values($lazy->whereLike('city', 'new')->toArray());
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
        
        // 测试不区分大小写
        $result = $lazy->whereLike('city', 'new', false)->toArray();
        $this->assertCount(2, $result);
    }

    /**
     * 测试whereNotLike方法
     */
    public function testWhereNotLike()
    {
        $data = [
            ['name' => 'John', 'city' => 'New York'],
            ['name' => 'Jane', 'city' => 'Los Angeles'],
            ['name' => 'Bob', 'city' => 'New Jersey'],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = array_values($lazy->whereNotLike('city', 'New')->toArray());
        
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
    }

    /**
     * 测试whereIn方法
     */
    public function testWhereIn()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 35],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = array_values($lazy->whereIn('age', [25, 35])->toArray());
        
        $this->assertCount(2, $result);
        $this->assertEquals('John', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    /**
     * 测试whereNotIn方法
     */
    public function testWhereNotIn()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 35],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = array_values($lazy->whereNotIn('age', [25, 35])->toArray());
        
        $this->assertCount(1, $result);
        $this->assertEquals('Jane', $result[0]['name']);
    }

    /**
     * 测试whereBetween方法
     */
    public function testWhereBetween()
    {
        $data = [
            ['name' => 'John', 'score' => 85],
            ['name' => 'Jane', 'score' => 92],
            ['name' => 'Bob', 'score' => 78],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = $lazy->whereBetween('score', [80, 90])->toArray();
        
        $this->assertCount(1, $result);
        $this->assertEquals('John', $result[0]['name']);
    }

    /**
     * 测试whereNotBetween方法
     */
    public function testWhereNotBetween()
    {
        $data = [
            ['name' => 'John', 'score' => 85],
            ['name' => 'Jane', 'score' => 92],
            ['name' => 'Bob', 'score' => 78],
        ];
        
        $lazy = LazyCollection::make($data);
        $result = array_values($lazy->whereNotBetween('score', [80, 90])->toArray());
        
        $this->assertCount(2, $result);
        $this->assertEquals('Jane', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    /**
     * 测试skip参数验证
     */
    public function testSkipValidation()
    {
        $lazy = LazyCollection::make([1, 2, 3]);
        
        $this->expectException(\InvalidArgumentException::class);
        $lazy->skip(-1);
    }

    /**
     * 测试空生成器
     */
    public function testEmptyGenerator()
    {
        $generator = function () {
            if (false) {
                yield;
            }
        };
        
        $lazy = new LazyCollection($generator);
        
        $this->assertTrue($lazy->isEmpty());
        $this->assertEquals(0, $lazy->count());
        $this->assertEquals([], $lazy->toArray());
        $this->assertNull($lazy->first());
        $this->assertNull($lazy->last());
    }

    /**
     * 测试flatten的各种深度
     */
    public function testFlattenVariousDepths()
    {
        $data = [
            1,
            [2, [3, 4]],
            [5, [6, [7, 8]]]
        ];
        
        $lazy = LazyCollection::make($data);
        
        // 深度1
        $result = $lazy->flatten(1)->toArray();
        $this->assertEquals([1, 2, [3, 4], 5, [6, [7, 8]]], $result);
        
        // 深度2
        $result = $lazy->flatten(2)->toArray();
        $this->assertEquals([1, 2, 3, 4, 5, 6, [7, 8]], $result);
        
        // 深度3
        $result = $lazy->flatten(3)->toArray();
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8], $result);
    }

    /**
     * 测试when方法的default回调
     */
    public function testWhenWithDefault()
    {
        $lazy = LazyCollection::make([1, 2, 3]);
        
        $result = $lazy->when(false, function ($collection) {
            return $collection->map(function ($item) {
                return $item * 2;
            });
        }, function ($collection) {
            return $collection->map(function ($item) {
                return $item + 10;
            });
        });
        
        $this->assertEquals([11, 12, 13], $result->toArray());
    }

    /**
     * 测试make方法的边界情况
     */
    public function testMakeEdgeCases()
    {
        // 测试空数组
        $lazy = LazyCollection::make([]);
        $this->assertEquals([], $lazy->toArray());
        
        // 测试已有的LazyCollection实例
        $lazy1 = LazyCollection::make([1, 2, 3]);
        $lazy2 = LazyCollection::make($lazy1);
        $this->assertSame($lazy1, $lazy2);
        
        // 测试生成器
        $generator = function () {
            yield 1;
            yield 2;
        };
        $lazy = LazyCollection::make($generator);
        $this->assertEquals([1, 2], $lazy->toArray());
    }

    /**
     * 测试page方法
     */
    public function testPage()
    {
        $lazy = LazyCollection::make(range(1, 20));
        
        // 测试第一页
        $page1 = $lazy->page(1, 5);
        $this->assertEquals([1, 2, 3, 4, 5], $page1->toArray());
        
        // 测试第二页
        $page2 = $lazy->page(2, 5);
        $this->assertEquals([5 => 6, 6 => 7, 7 => 8, 8 => 9, 9 => 10], $page2->toArray());
        
        // 测试第三页
        $page3 = $lazy->page(3, 5);
        $this->assertEquals([10 => 11, 11 => 12, 12 => 13, 13 => 14, 14 => 15], $page3->toArray());
        
        // 测试最后一页
        $page4 = $lazy->page(4, 5);
        $this->assertEquals([15 => 16, 16 => 17, 17 => 18, 18 => 19, 19 => 20], $page4->toArray());
        
        // 测试超出范围的页码
        $page5 = $lazy->page(5, 5);
        $this->assertEquals([], $page5->toArray());
        
        // 测试默认每页数量
        $pageDefault = $lazy->page(1);
        $this->assertCount(15, $pageDefault->toArray());
    }

    /**
     * 测试page方法的异常情况
     */
    public function testPageValidation()
    {
        $lazy = LazyCollection::make(range(1, 10));
        
        // 测试无效的页码
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page should be at least 1');
        $lazy->page(0, 5);
    }

    /**
     * 测试page方法的每页数量验证
     */
    public function testPagePerPageValidation()
    {
        $lazy = LazyCollection::make(range(1, 10));
        
        // 测试无效的每页数量
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page should be at least 1');
        $lazy->page(1, 0);
    }

    /**
     * 测试page方法与其他方法的链式调用
     */
    public function testPageChaining()
    {
        $lazy = LazyCollection::make(range(1, 30));
        
        // 先过滤再分页
        $result = $lazy
            ->filter(function ($value) {
                return $value % 2 == 0;
            })
            ->page(2, 5)
            ->toArray();
        
        // 第二页应该是 12, 14, 16, 18, 20
        $this->assertEquals([11 => 12, 13 => 14, 15 => 16, 17 => 18, 19 => 20], $result);
        
        // 先分页再映射
        $result = $lazy
            ->page(1, 3)
            ->map(function ($value) {
                return $value * 10;
            })
            ->toArray();
        
        $this->assertEquals([10, 20, 30], $result);
    }

    /**
     * 测试page方法的惰性执行
     */
    public function testPageLaziness()
    {
        $count = 0;
        $generator = function () use (&$count) {
            for ($i = 1; $i <= 100; $i++) {
                $count++;
                yield $i;
            }
        };
        
        $lazy = new LazyCollection($generator);
        $page = $lazy->page(2, 10);
        
        // 还没有执行，不应该生成任何元素
        $this->assertEquals(0, $count);
        
        // 转换为数组时才执行
        $result = $page->toArray();
        
        // 应该只生成到第20个元素（跳过10个，获取10个）
        // 注意：由于skip的实现方式，需要先生成前10个元素才能跳过它们
        $this->assertLessThanOrEqual(21, $count); // 可能会多生成一个用于判断结束
        $this->assertEquals(range(11, 20), array_values($result));
    }

}