<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelHasManyThroughTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_country`;',
            'CREATE TABLE `test_country` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_through_author`;',
            'CREATE TABLE `test_through_author` (
                `id` int NOT NULL AUTO_INCREMENT,
                `country_id` int NOT NULL,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_country_id` (`country_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_through_post`;',
            'CREATE TABLE `test_through_post` (
                `id` int NOT NULL AUTO_INCREMENT,
                `author_id` int NOT NULL,
                `title` varchar(255) NOT NULL DEFAULT "",
                `content` text,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_author_id` (`author_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_country`;');
        Db::execute('TRUNCATE TABLE `test_through_author`;');
        Db::execute('TRUNCATE TABLE `test_through_post`;');

        // 创建测试数据
        $country1 = CountryModel::create([
            'name' => 'China'
        ]);

        $country2 = CountryModel::create([
            'name' => 'USA'
        ]);

        $author1 = ThroughAuthorModel::create([
            'country_id' => $country1->id,
            'name' => 'author1',
            'email' => 'author1@example.com'
        ]);

        $author2 = ThroughAuthorModel::create([
            'country_id' => $country1->id,
            'name' => 'author2',
            'email' => 'author2@example.com'
        ]);

        $author3 = ThroughAuthorModel::create([
            'country_id' => $country2->id,
            'name' => 'author3',
            'email' => 'author3@example.com'
        ]);

        ThroughPostModel::create([
            'author_id' => $author1->id,
            'title' => 'Post1',
            'content' => 'Content1'
        ]);

        ThroughPostModel::create([
            'author_id' => $author1->id,
            'title' => 'Post2',
            'content' => 'Content2'
        ]);

        ThroughPostModel::create([
            'author_id' => $author2->id,
            'title' => 'Post3',
            'content' => 'Content3'
        ]);

        ThroughPostModel::create([
            'author_id' => $author3->id,
            'title' => 'Post4',
            'content' => 'Content4'
        ]);
    }

    public function testHasManyThrough()
    {
        // 测试关联获取
        $country = CountryModel::find(1);
        $this->assertNotNull($country);
        
        $posts = $country->posts;
        $this->assertCount(3, $posts);
        $this->assertEquals('Post1', $posts[0]->title);

        // 测试预加载
        $country = CountryModel::with(['posts'])->find(1);
        $this->assertCount(3, $country->posts);

        // 测试关联统计
        $country = CountryModel::withCount('posts')->find(1);
        $this->assertEquals(3, $country->posts_count);

        // 测试条件查询
        $posts = $country->posts()->where('test_through_post.title', 'like', '%1%')->select();
        $this->assertCount(1, $posts);
        $this->assertEquals('Post1', $posts[0]->title);

        // 测试排序
        $posts = $country->posts()->order('test_through_post.title', 'desc')->select();
        $this->assertEquals('Post3', $posts[0]->title);

        // 测试字段查询
        $posts = $country->posts()->field('test_through_post.title')->select();
        $this->assertArrayNotHasKey('content', $posts[0]->toArray());
    }
}

class CountryModel extends Model
{
    protected $table = 'test_country';
    protected $autoWriteTimestamp = true;

    public function posts()
    {
        return $this->hasManyThrough(
            ThroughPostModel::class,
            ThroughAuthorModel::class,
            'country_id',
            'author_id',
            'id',
            'id'
        );
    }
}

class ThroughAuthorModel extends Model
{
    protected $table = 'test_through_author';
    protected $autoWriteTimestamp = true;
}

class ThroughPostModel extends Model
{
    protected $table = 'test_through_post';
    protected $autoWriteTimestamp = true;
}