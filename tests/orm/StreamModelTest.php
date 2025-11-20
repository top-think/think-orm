<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\Model;
use think\facade\Db;
use think\db\LazyCollection;
use think\model\LazyCollection as ModelLazyCollection;

/**
 * 测试User模型
 */
class StreamTestUser extends Model
{
    protected $table = 'stream_test_user';
    
    public function profile()
    {
        return $this->hasOne(StreamTestProfile::class, 'user_id');
    }
    
    public function articles()
    {
        return $this->hasMany(StreamTestArticle::class, 'user_id');
    }
}

/**
 * 测试Profile模型
 */
class StreamTestProfile extends Model
{
    protected $table = 'stream_test_profile';
}

/**
 * 测试Article模型
 */
class StreamTestArticle extends Model
{
    protected $table = 'stream_test_article';
}

class StreamModelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        try {
            // 创建用户表
            Db::execute('DROP TABLE IF EXISTS `stream_test_user`;');
            Db::execute("CREATE TABLE `stream_test_user` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT '',
              `email` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 创建资料表
            Db::execute('DROP TABLE IF EXISTS `stream_test_profile`;');
            Db::execute("CREATE TABLE `stream_test_profile` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `bio` text,
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 创建文章表
            Db::execute('DROP TABLE IF EXISTS `stream_test_article`;');
            Db::execute("CREATE TABLE `stream_test_article` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `title` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            
            // 插入测试数据
            for ($i = 1; $i <= 50; $i++) {
                $userId = Db::table('stream_test_user')->insertGetId([
                    'name' => 'User ' . $i,
                    'email' => 'user' . $i . '@example.com'
                ]);
                
                Db::table('stream_test_profile')->insert([
                    'user_id' => $userId,
                    'bio' => 'Bio for user ' . $i
                ]);
                
                // 每个用户2-3篇文章
                for ($j = 1; $j <= rand(2, 3); $j++) {
                    Db::table('stream_test_article')->insert([
                        'user_id' => $userId,
                        'title' => 'Article ' . $j . ' by User ' . $i
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    public static function tearDownAfterClass(): void
    {
        try {
            Db::execute('DROP TABLE IF EXISTS `stream_test_user`;');
            Db::execute('DROP TABLE IF EXISTS `stream_test_profile`;');
            Db::execute('DROP TABLE IF EXISTS `stream_test_article`;');
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 测试模型的stream方法
     */
    public function testModelStream()
    {
        $count = 0;
        $result = StreamTestUser::where('id', '>', 0)
            ->limit(10)
            ->stream(function($user) use (&$count) {
                $this->assertInstanceOf(StreamTestUser::class, $user);
                $this->assertIsString($user->name);
                $this->assertIsString($user->email);
                $count++;
            });
        
        $this->assertEquals(10, $result);
        $this->assertEquals(10, $count);
    }
    
    /**
     * 测试模型的cursor方法
     */
    public function testModelCursor()
    {
        $count = 0;
        foreach (StreamTestUser::cursor() as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            $count++;
            if ($count >= 10) break;
        }
        
        $this->assertEquals(10, $count);
    }
    
    /**
     * 测试关联预载入与stream的结合
     */
    public function testStreamWithWith()
    {
        $count = 0;
        $hasProfile = true;
        
        StreamTestUser::with(['profile'])
            ->limit(5)
            ->stream(function($user) use (&$count, &$hasProfile) {
                $this->assertInstanceOf(StreamTestUser::class, $user);
                // 访问关联数据
                if ($user->profile === null) {
                    $hasProfile = false;
                } else {
                    $this->assertInstanceOf(StreamTestProfile::class, $user->profile);
                }
                $count++;
            });
        
        $this->assertEquals(5, $count);
        $this->assertTrue($hasProfile, 'All users should have profiles loaded');
    }
    
    /**
     * 测试延迟加载与stream的结合
     */
    public function testStreamWithLazyLoad()
    {
        $count = 0;
        StreamTestUser::limit(5)
            ->stream(function($user) use (&$count) {
                // 延迟加载 - 每次访问时才查询
                $profile = $user->profile;
                $this->assertInstanceOf(StreamTestProfile::class, $profile);
                $count++;
            });
        
        $this->assertEquals(5, $count);
    }

    /**
     * 测试cursor方法的返回类型
     */
    public function testCursorReturnType()
    {
        $result = StreamTestUser::cursor();
        
        // 测试返回的是LazyCollection实例
        $this->assertInstanceOf(LazyCollection::class, $result);
        
        // 测试可以遍历并返回Model实例
        $count = 0;
        foreach ($result->take(5) as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            $this->assertIsInt($user->id);
            $this->assertIsString($user->name);
            $count++;
        }
        
        $this->assertEquals(5, $count);
    }

    /**
     * 测试lazy方法的返回类型
     */
    public function testLazyReturnType()
    {
        $result = StreamTestUser::lazy(10);
        
        // 测试返回的是LazyCollection实例
        $this->assertInstanceOf(LazyCollection::class, $result);
        
        // 测试可以遍历并返回Model实例
        $count = 0;
        foreach ($result as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            $this->assertIsInt($user->id);
            $this->assertIsString($user->name);
            $count++;
            if ($count >= 10) break;
        }
        
        $this->assertEquals(10, $count);
    }

    /**
     * 测试lazy方法的分页功能
     */
    public function testLazyWithPaging()
    {
        // 测试使用ID列进行分页
        $result = StreamTestUser::lazy(5, 'id', 'asc');
        $this->assertInstanceOf(LazyCollection::class, $result);
        
        $count = 0;
        $lastId = 0;
        foreach ($result as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            $this->assertGreaterThan($lastId, $user->id);
            $lastId = $user->id;
            $count++;
            if ($count >= 15) break; // 获取3页数据
        }
        
        $this->assertEquals(15, $count);
    }

    /**
     * 测试LazyCollection的load方法
     */
    public function testLazyCollectionLoad()
    {
        // 创建包含用户数据的LazyCollection
        $lazy = StreamTestUser::limit(10)->cursor();
        
        // 测试load方法返回新的LazyCollection
        $loaded = $lazy->load(['profile']);
        $this->assertInstanceOf(LazyCollection::class, $loaded);
        
        // 测试预载入的关联数据
        $count = 0;
        foreach ($loaded as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            // 访问profile应该已经预载入
            $this->assertInstanceOf(StreamTestProfile::class, $user->profile);
            $this->assertEquals($user->id, $user->profile->user_id);
            $count++;
        }
        
        $this->assertEquals(10, $count);
    }

    /**
     * 测试LazyCollection的load方法带缓存
     */
    public function testLazyCollectionLoadWithCache()
    {
        $lazy = StreamTestUser::limit(5)->cursor();
        
        // 测试带缓存的load方法
        $loaded = $lazy->load(['articles'], true);
        $this->assertInstanceOf(LazyCollection::class, $loaded);
        
        // 测试预载入的articles关联
        $count = 0;
        foreach ($loaded as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            // 访问articles应该已经预载入
            $this->assertInstanceOf(\think\model\Collection::class, $user->articles);
            $this->assertGreaterThan(0, count($user->articles));
            $count++;
        }
        
        $this->assertEquals(5, $count);
    }

    /**
     * 测试LazyCollection的page方法基本功能
     */
    public function testLazyCollectionPage()
    {
        $lazy = StreamTestUser::cursor();
        
        // 测试第一页
        $page1 = $lazy->page(1, 10);
        $this->assertInstanceOf(LazyCollection::class, $page1);
        
        $users1 = [];
        foreach ($page1 as $user) {
            $users1[] = $user;
        }
        $this->assertCount(10, $users1);
        
        // 获取第一个和最后一个用户的ID
        $firstUser = $users1[0];
        $lastUser = $users1[9];
        $this->assertEquals(1, $firstUser->id);
        $this->assertEquals(10, $lastUser->id);
        
        // 测试第二页
        $page2 = $lazy->page(2, 10);
        $users2 = [];
        foreach ($page2 as $user) {
            $users2[] = $user;
        }
        $this->assertCount(10, $users2);
        
        $firstUserPage2 = $users2[0];
        $lastUserPage2 = $users2[9];
        $this->assertEquals(11, $firstUserPage2->id);
        $this->assertEquals(20, $lastUserPage2->id);
    }

    /**
     * 测试page方法与关联预载入的结合
     */
    public function testPageWithRelations()
    {
        $lazy = StreamTestUser::with(['profile'])->cursor();
        
        // 获取第一页数据
        $page1 = $lazy->page(1, 5);
        
        $count = 0;
        foreach ($page1 as $user) {
            $this->assertInstanceOf(StreamTestUser::class, $user);
            // 验证profile已经预载入
            $this->assertInstanceOf(StreamTestProfile::class, $user->profile);
            $this->assertEquals($user->id, $user->profile->user_id);
            $count++;
        }
        
        $this->assertEquals(5, $count);
    }

    /**
     * 测试page方法的边界情况
     */
    public function testPageBoundaries()
    {
        $lazy = StreamTestUser::cursor();
        
        // 测试超出范围的页码
        $page100 = $lazy->page(100, 10);
        $users = [];
        foreach ($page100 as $user) {
            $users[] = $user;
        }
        $this->assertCount(0, $users); // 应该返回空数组
        
        // 测试最后一页（总共50条数据）
        $lastPage = $lazy->page(5, 10);
        $lastPageUsers = [];
        foreach ($lastPage as $user) {
            $lastPageUsers[] = $user;
        }
        $this->assertCount(10, $lastPageUsers);
        
        // 测试部分填充的最后一页
        $partialPage = $lazy->page(6, 10);
        $partialUsers = [];
        foreach ($partialPage as $user) {
            $partialUsers[] = $user;
        }
        $this->assertCount(0, $partialUsers); // 第6页应该没有数据
        
        // 测试恰好完整的最后一页
        $exactLastPage = $lazy->page(10, 5);
        $exactUsers = [];
        foreach ($exactLastPage as $user) {
            $exactUsers[] = $user;
        }
        $this->assertCount(5, $exactUsers);
        $firstUser = $exactUsers[0];
        $lastUser = $exactUsers[4];
        $this->assertEquals(46, $firstUser->id);
        $this->assertEquals(50, $lastUser->id);
    }

    /**
     * 测试page方法的链式调用
     */
    public function testPageChaining()
    {
        $result = [];
        $paged = StreamTestUser::cursor()
            ->filter(function($user) {
                // 只保留偶数ID的用户
                return $user->id % 2 == 0;
            })
            ->page(2, 5);
            
        foreach ($paged as $user) {
            $result[] = $user;
        }
        
        $this->assertCount(5, $result);
        
        // 偶数ID: 2,4,6,8,10,12,14,16,18,20...
        // 第二页应该是: 12,14,16,18,20
        $ids = array_map(function($user) {
            return $user->id;
        }, $result);
        
        $this->assertEquals([12, 14, 16, 18, 20], array_values($ids));
    }

    /**
     * 测试page方法的惰性求值特性
     */
    public function testPageLazyEvaluation()
    {
        // 使用一个标志来检测查询是否执行
        $queryExecuted = false;
        
        // 创建一个生成器来模拟惰性加载
        $generator = function() use (&$queryExecuted) {
            $queryExecuted = true;
            $users = StreamTestUser::select();
            foreach ($users as $user) {
                yield $user;
            }
        };
        
        // 创建LazyCollection但不立即执行
        $lazy = new ModelLazyCollection($generator);
        $paged = $lazy->page(3, 10);
        
        // 此时生成器还没有执行
        $this->assertFalse($queryExecuted);
        
        // 遍历时才执行生成器
        $users = [];
        foreach ($paged as $user) {
            $users[] = $user;
        }
        $this->assertTrue($queryExecuted);
        $this->assertCount(10, $users);
    }

    /**
     * 测试page方法与map的结合使用
     */
    public function testPageWithMap()
    {
        $result = [];
        $mapped = StreamTestUser::cursor()
            ->page(1, 5)
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => strtoupper($user->name),
                    'email' => $user->email
                ];
            });
            
        foreach ($mapped as $item) {
            $result[] = $item;
        }
        
        $this->assertCount(5, $result);
        
        // 验证第一个用户的数据格式
        $firstUser = $result[0];
        $this->assertIsArray($firstUser);
        $this->assertArrayHasKey('id', $firstUser);
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayHasKey('email', $firstUser);
        $this->assertEquals('USER 1', $firstUser['name']); // 应该是大写的
    }

}