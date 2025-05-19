<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Tran extends AbstractMigration
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
            ->table('test_tran_a', [
                'id'          => false,
                'primary_key' => ['id'],
            ])
            ->addColumn('id', 'integer', [
                'signed'   => false,
                'identity' => true,
                'null'     => false,
            ])
            ->addColumn('type', 'integer', [
                'limit'   => 2, // MySQL:TINYINT(4)，PG:SMALLINT
                'default' => 0,
                'signed'  => false,
                'null'    => false,
            ])
            ->addColumn('username', 'string', [
                'limit' => 32,
                'null'  => false,
            ])
            ->create();
    }
}
