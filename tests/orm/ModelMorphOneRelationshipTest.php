<?php

namespace tests\orm;

use tests\Base;
use think\facade\Db;
use think\Model;

class ModelMorphOneRelationshipTest extends Base
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_member`;');
        Db::execute(<<<SQL
CREATE TABLE `test_member` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
        Db::execute('DROP TABLE IF EXISTS `test_avatar`;');
        Db::execute(<<<SQL
CREATE TABLE `test_avatar` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `avatar` varchar(128) NOT NULL,
     `imageable_id` int(10) NOT NULL,
     `imageable_type` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_member`');
        Db::execute('TRUNCATE TABLE `test_avatar`');
        Db::table('test_member')->insertAll([
            ['id' => 1, 'name' => 'member1'],
        ]);
        Db::table('test_avatar')->insertAll([
            ['id' => 1, 'avatar' => 'http://example.com/avatar.jpg', 'imageable_id' => 1, 'imageable_type' => MorphOneMember::class],
        ]);
    }

    public function testAttachDetachRelations()
    {
        $m = MorphOneMember::find(1);
        $this->assertEquals('http://example.com/avatar.jpg', $m->avatar->avatar);
        $avatar = MorphOneAvatar::find(1);
        $this->assertEquals($m->id, $avatar->imageable->id);
    }
}

class MorphOneMember extends Model
{
    protected $table = 'test_member';

    public function avatar()
    {
        return $this->morphOne(MorphOneAvatar::class, 'imageable');
    }
}

class MorphOneAvatar extends Model
{
    protected $table = 'test_avatar';

    public function imageable()
    {
        return $this->morphTo();
    }
}
