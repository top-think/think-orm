<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TestGoods extends AbstractMigration
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
            ->table('test_goods', [
                'id'          => false,
                'primary_key' => ['id'],
            ])
            ->addColumn('id', 'integer', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('name', 'string', [
                'limit'   => 32,
                'default' => '',
                'null'    => false,
            ])
            ->addColumn('extend', 'json', [
                'null'    => true,
                'default' => null,
            ])
            ->create();
    }
}
