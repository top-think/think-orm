<?php

namespace tests\orm;

use tests\Base;
use think\facade\Db;
use think\Model;

class ModelBelongsToManyRelationshipTest extends Base
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_user`;');
        Db::execute(<<<SQL
CREATE TABLE `test_user` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
        Db::execute('DROP TABLE IF EXISTS `test_role`;');
        Db::execute(<<<SQL
CREATE TABLE `test_role` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
        Db::execute('DROP TABLE IF EXISTS `test_user_role`;');
        Db::execute(<<<SQL
CREATE TABLE `test_user_role` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `user_id` int(10) NOT NULL,
     `role_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user`');
        Db::execute('TRUNCATE TABLE `test_role`');
        Db::execute('TRUNCATE TABLE `test_user_role`');
        Db::table('test_user')->insertAll([
            ['id' => 1, 'name' => 'user1'],
        ]);
        Db::table('test_role')->insertAll([
            ['id' => 1, 'name' => 'role1'],
        ]);
    }

    public function testAttachDetachRelations()
    {
        $user = BelongsToManyUser::find(1);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach(1);
        $this->assertEquals(['role1'], $user->roles()->column('name'));
        $user->roles()->detach(1);
        $this->assertEquals(0, $user->roles()->count());
    }
}

class BelongsToManyUser extends Model
{
    protected $table = 'test_user';

    public function roles()
    {
        return $this->belongsToMany(BelongsToManyRole::class, 'user_role', 'role_id', 'user_id', 'id', 'id');
    }
}

class BelongsToManyRole extends Model
{
    protected $table = 'test_role';
}
