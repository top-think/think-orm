<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TestUser extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this
            ->table('test_user', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed'   => false,
                'null'     => false,
            ])
            ->addColumn('type', 'integer', [
                'limit'   => 4,        // 对应 MySQL 的 TINYINT(4)
                'default' => 0,
                'null'    => false,
                'signed'  => false,   // 转换为 PostgreSQL 的 SMALLINT
            ])
            ->addColumn('username', 'string', [
                'limit' => 32,
                'null'  => false,
            ])
            ->addColumn('nickname', 'string', [
                'limit' => 32,
                'null'  => false,
            ])
            ->addColumn('password', 'string', [
                'limit' => 64,
                'null'  => false,
            ])
            ->create();
    }
}
