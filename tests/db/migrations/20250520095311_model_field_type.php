<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ModelFieldType extends AbstractMigration
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
        $adapterType = $this->getAdapter()->getAdapterType();

        $this
            ->table('test_field_type', ['id' => false, 'primary_key' => ['id']])
            ->addColumn(
                'id',
                'integer',
                [
                    'identity' => true,
                    'signed'   => false, // MySQL用UNSIGNED, PostgreSQL需要容错
                    'null'     => false,
                ]
            )
            ->addColumn(
                't_json',
                'json',
                [
                    'null'    => true,
                    'default' => null,
                ])
            ->addColumn(
                't_php',
                'text',
                [  // 改用text类型更通用
                   'limit'   => 512,
                   'null'    => true,
                   'default' => null,
                ]
            )
            ->addColumn(
                'bigint',
                $adapterType === 'pgsql' ? 'decimal' : 'biginteger',
                [
                    'signed'  => false,
                    'null'    => true,
                    'default' => null,
                    'after'   => 't_php', // 可选字段排序
                    'precision' => $adapterType === 'pgsql' ? 20 : null, // PG BIGINT 最大19位
                ]
            )
            ->create();
    }
}
