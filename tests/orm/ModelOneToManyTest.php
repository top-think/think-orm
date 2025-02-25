<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelOneToManyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_author`;',
            'CREATE TABLE `test_author` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_post`;',
            'CREATE TABLE `test_post` (
                `id` int NOT NULL AUTO_INCREMENT,
                `author_id` int NOT NULL,
                `title` varchar(255) NOT NULL DEFAULT "",
                `content` text,
                `status` tinyint NOT NULL DEFAULT 0,
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
        Db::execute('TRUNCATE TABLE `test_author`;');
        Db::execute('TRUNCATE TABLE `test_post`;');

        // 创建测试数据
        $author1 = AuthorModel::create([
            'name' => 'author1',
            'email' => 'author1@example.com'
        ]);

        $author2 = AuthorModel::create([
            'name' => 'author2',
            'email' => 'author2@example.com'
        ]);

        // 为作者1创建文章
        PostModel::create([
            'author_id' => $author1->id,
            'title' => 'Post 1 by author1',
            'content' => 'Content of post 1',
            'status' => 1
        ]);

        PostModel::create([
            'author_id' => $author1->id,
            'title' => 'Post 2 by author1',
            'content' => 'Content of post 2',
            'status' => 1
        ]);

        // 为作者2创建文章
        PostModel::create([
            'author_id' => $author2->id,
            'title' => 'Post 1 by author2',
            'content' => 'Content of post 1',
            'status' => 0
        ]);
    }

    public function testHasManyRelation()
    {
        // 测试关联获取
        $author = AuthorModel::find(1);
        $this->assertNotNull($author);
        
        $posts = $author->posts;
        $this->assertCount(2, $posts);
        $this->assertEquals('Post 1 by author1', $posts[0]->title);

        // 测试预加载
        $author = AuthorModel::with(['posts'])->find(1);
        $this->assertTrue($author->isRelationLoaded('posts'));
        $this->assertCount(2, $author->posts);

        // 测试关联条件
        $author = AuthorModel::with(['posts' => function($query) {
            $query->where('status', 1);
        }])->find(2);
        $this->assertCount(0, $author->posts);

        // 测试关联统计
        $author = AuthorModel::withCount('posts')->find(1);
        $this->assertEquals(2, $author->posts_count);

        // 测试关联写入
        $author = AuthorModel::find(2);
        $result = $author->posts()->save([
            'title' => 'New post by author2',
            'content' => 'New content',
            'status' => 1
        ]);
        $this->assertNotNull($result);
        $this->assertEquals($author->id, $result->author_id);
    }
}

class AuthorModel extends Model
{
    protected $table = 'test_author';
    protected $autoWriteTimestamp = true;

    public function posts()
    {
        return $this->hasMany(PostModel::class, 'author_id', 'id');
    }
}

class PostModel extends Model
{
    protected $table = 'test_post';
    protected $autoWriteTimestamp = true;

    public function author()
    {
        return $this->belongsTo(AuthorModel::class, 'author_id', 'id');
    }
}