<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ModelOneToOne extends AbstractMigration
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
            ->table('orm_test_user', [
                'id'          => false,
                'primary_key' => ['id'],
            ])
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed'   => true,
                'null'     => false,
            ])
            ->addColumn('account', 'string', [
                'limit'   => 255,
                'null'    => false,
                'default' => '',
            ])->create();

        $this
            ->table('orm_test_profile', [
                'id'          => false,
                'primary_key' => ['id'],
            ])
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed'   => true,
                'null'     => false,
            ])
            ->addColumn('uid', 'integer', [
                'signed' => true,
                'null'   => false,
            ])
            ->addColumn('email', 'string', [
                'limit'   => 255,
                'null'    => false,
                'default' => '',
            ])
            ->addColumn('nickname', 'string', [
                'limit'   => 255,
                'null'    => false,
                'default' => '',
            ])
            ->addColumn('update_time', 'datetime', [
                'null' => false,
            ])
            ->addColumn('delete_time', 'datetime', [
                'null'    => true,
                'default' => null,
            ])
            ->addColumn('create_time', 'datetime', [
                'null' => false,
            ])
            ->create();
    }
}
