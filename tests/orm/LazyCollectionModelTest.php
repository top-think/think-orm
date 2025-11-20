<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\Model;
use think\model\LazyCollection;

/**
 * LazyCollection 模型方法测试
 */
class LazyCollectionModelTest extends TestCase
{
    /**
     * 测试 hidden 方法是否生效
     */
    public function testHidden()
    {
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestLazyModel();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->set('password', 'secret' . $i);
            $user->set('email', 'user' . $i . '@test.com');
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 应用 hidden
        $result = $lazy->hidden(['password', 'email']);

        // 验证结果
        $data = $result->toArray();
        $this->assertCount(3, $data);
        
        foreach ($data as $item) {
            $this->assertArrayNotHasKey('password', $item);
            $this->assertArrayNotHasKey('email', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
        }
    }

    /**
     * 测试 visible 方法是否生效
     */
    public function testVisible()
    {
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestLazyModel();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->set('password', 'secret' . $i);
            $user->set('email', 'user' . $i . '@test.com');
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 应用 visible
        $result = $lazy->visible(['id', 'name']);

        // 验证结果
        $data = $result->toArray();
        $this->assertCount(3, $data);
        
        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayNotHasKey('password', $item);
            $this->assertArrayNotHasKey('email', $item);
        }
    }

    /**
     * 测试链式调用
     */
    public function testChaining()
    {
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestLazyModel();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->set('password', 'secret' . $i);
            $user->set('email', 'user' . $i . '@test.com');
            $user->set('status', $i % 2);
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 链式调用：过滤 -> 隐藏字段 -> 取前3个
        $result = $lazy
            ->filter(function ($model) {
                return $model->getAttr('status') == 1;
            })
            ->hidden(['password'])
            ->take(2);

        // 验证结果
        $data = $result->toArray();
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            $this->assertArrayNotHasKey('password', $item);
            $this->assertEquals(1, $item['status']);
        }
    }

    /**
     * 测试 append 方法
     */
    public function testAppend()
    {
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 2; $i++) {
            $user = new TestLazyModel();
            $user->set('id', $i);
            $user->set('first_name', 'First' . $i);
            $user->set('last_name', 'Last' . $i);
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 应用 append
        $result = $lazy->append(['full_name']);

        // 验证结果
        $data = $result->toArray();
        $this->assertCount(2, $data);
        
        foreach ($data as $key => $item) {
            $this->assertArrayHasKey('full_name', $item);
            $this->assertEquals('First' . ($key + 1) . ' Last' . ($key + 1), $item['full_name']);
        }
    }

    /**
     * 测试 load 方法（延迟加载关联）
     */
    public function testLoad()
    {
        // 模拟关联加载被调用的情况
        $eagerlyResultSetCalled = false;
        $loadedRelations = [];
        
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new TestLazyModelWithRelation();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->setEagerlyCallback(function($resultSet, $relation) use (&$eagerlyResultSetCalled, &$loadedRelations) {
                $eagerlyResultSetCalled = true;
                $loadedRelations = $relation;
                
                // 模拟添加关联数据
                foreach ($resultSet as $model) {
                    $model->setRelation('profile', [
                        'user_id' => $model->id,
                        'avatar' => 'avatar' . $model->id . '.jpg'
                    ]);
                }
            });
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 测试 load 方法
        $result = $lazy->load(['profile', 'posts']);
        
        // 验证返回的是 LazyCollection
        $this->assertInstanceOf(LazyCollection::class, $result);
        
        // 遍历结果，触发延迟加载
        $loaded = [];
        foreach ($result as $key => $model) {
            $loaded[$key] = $model;
        }
        
        // 验证加载被调用
        $this->assertTrue($eagerlyResultSetCalled, '关联加载方法应该被调用');
        $this->assertEquals(['profile', 'posts'], $loadedRelations, '传递的关联名称应该正确');
        
        // 验证所有模型都有关联数据
        foreach ($loaded as $model) {
            $relation = $model->getRelation('profile');
            $this->assertIsArray($relation);
            $this->assertEquals($model->id, $relation['user_id']);
            $this->assertEquals('avatar' . $model->id . '.jpg', $relation['avatar']);
        }
    }

    /**
     * 测试 load 方法的缓存参数
     */
    public function testLoadWithCache()
    {
        $cacheParam = null;
        
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 2; $i++) {
            $user = new TestLazyModelWithRelation();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->setEagerlyCallback(function($resultSet, $relation, $withRelationAttr, $join, $cache) use (&$cacheParam) {
                $cacheParam = $cache;
            });
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 测试带缓存参数的 load
        $result = $lazy->load(['profile'], true);
        
        // 触发加载
        iterator_to_array($result);
        
        // 验证缓存参数被正确传递
        $this->assertTrue($cacheParam, '缓存参数应该被正确传递');
    }

    /**
     * 测试 load 方法与其他方法的链式调用
     */
    public function testLoadChaining()
    {
        // 创建测试模型
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestLazyModelWithRelation();
            $user->set('id', $i);
            $user->set('name', 'User ' . $i);
            $user->set('status', $i % 2);
            $user->setEagerlyCallback(function($resultSet, $relation) {
                foreach ($resultSet as $model) {
                    $model->setRelation('profile', ['bio' => 'Bio for ' . $model->name]);
                }
            });
            $users[] = $user;
        }

        // 创建 LazyCollection
        $lazy = new LazyCollection(function () use ($users) {
            foreach ($users as $user) {
                yield $user;
            }
        });

        // 链式调用：过滤 -> 加载关联 -> 隐藏字段 -> 取前2个
        $result = $lazy
            ->filter(function ($model) {
                return $model->status == 1;
            })
            ->load(['profile'])
            ->hidden(['status'])
            ->take(2);

        // 验证结果
        $data = $result->toArray();
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            // 验证关联数据存在
            $this->assertArrayHasKey('profile', $item);
            $this->assertStringContainsString('Bio for User', $item['profile']['bio']);
            
            // 验证 status 被隐藏
            $this->assertArrayNotHasKey('status', $item);
        }
    }
}

/**
 * 测试用模型
 */
class TestLazyModel extends Model
{
    protected $table = 'test_lazy_users';
    
    /**
     * 禁用自动读取字段信息
     */
    protected $autoFieldInfo = false;
    
    /**
     * 获取器 - 全名
     */
    public function getFullNameAttr($value, $data)
    {
        return $data['first_name'] . ' ' . $data['last_name'];
    }
    
    /**
     * 重写初始化方法，避免数据库连接
     */
    protected function initialize(): void
    {
        // 不执行父类初始化，避免数据库连接
    }
    
    /**
     * 重写获取字段信息方法
     */
    public function getFields(?string $field = null)
    {
        return [];
    }
}

/**
 * 带关联功能的测试模型
 */
class TestLazyModelWithRelation extends TestLazyModel
{
    /**
     * 关联数据
     */
    protected $relation = [];
    
    /**
     * eagerlyResultSet 回调
     */
    private $eagerlyCallback;
    
    /**
     * 设置 eagerly 回调
     */
    public function setEagerlyCallback($callback)
    {
        $this->eagerlyCallback = $callback;
    }
    
    /**
     * 模拟 eagerlyResultSet 方法
     */
    public function eagerlyResultSet(array $resultSet, array $relations, array $withRelationAttr = [], bool $join = false, $cache = false): void
    {
        if ($this->eagerlyCallback) {
            call_user_func($this->eagerlyCallback, $resultSet, $relations, $withRelationAttr, $join, $cache);
        }
    }
    
    /**
     * 获取关联数据
     */
    public function getRelation(string $relation): array
    {
        return $this->relation[$relation] ?? [];
    }
    
    /**
     * 获取所有关联数据
     */
    public function getAllRelations(): array
    {
        return $this->relation;
    }
    
    /**
     * 设置关联数据
     */
    public function setRelation($name, $data)
    {
        $this->relation[$name] = $data;
        return $this;
    }
    
    /**
     * 重写 toArray 方法以包含关联数据
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        
        // 添加关联数据
        foreach ($this->relation as $key => $relation) {
            $data[$key] = $relation;
        }
        
        return $data;
    }
}