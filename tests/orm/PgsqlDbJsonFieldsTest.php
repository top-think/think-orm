<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlDbJsonFieldsTest extends DbJsonFieldsBase
{
    protected static string $dbName = 'pgsql';

    public function testInitGoods(): array
    {
        $this->db->execute('TRUNCATE TABLE test_goods;');

        $userData = $this->provideTestData();
        self::compatibleInsertAll($this->db->table('test_goods')->json(['extend']), $userData);

        return $userData;
    }
}
