<?php

namespace tests\orm;

use tests\Base;
use think\facade\Db;
use think\Model;

class ModelMorphManyRelationshipTest extends Base
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_article`;');
        Db::execute(<<<SQL
CREATE TABLE `test_article` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `title` varchar(32) NOT NULL,
     `content` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
        Db::execute('DROP TABLE IF EXISTS `test_comment`;');
        Db::execute(<<<SQL
CREATE TABLE `test_comment` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `content` text NOT NULL,
     `commentable_id` int(10) NOT NULL,
     `commentable_type` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_article`');
        Db::execute('TRUNCATE TABLE `test_comment`');
        Db::table('test_article')->insertAll([
            ['id' => 1, 'title' => 'a title', 'content' => 'a content'],
        ]);
        Db::table('test_comment')->insertAll([
            ['id' => 1, 'content' => 'a comment', 'commentable_id' => 1, 'commentable_type' => MorphManyArticle::class],
        ]);
    }

    public function testAttachDetachRelations()
    {
        $a = MorphManyArticle::find(1);
        $this->assertContains('a comment', $a->comments->column('content'));
        $comment = MorphManyComment::find(1);
        $this->assertEquals($a->id, $comment->commentable->id);
    }
}

class MorphManyArticle extends Model
{
    protected $table = 'test_article';

    public function comments()
    {
        return $this->morphMany(MorphManyComment::class, 'commentable');
    }
}

class MorphManyComment extends Model
{
    protected $table = 'test_comment';

    public function commentable()
    {
        return $this->morphTo();
    }
}
