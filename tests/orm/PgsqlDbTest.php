<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlDbTest extends DbTestBase
{
    protected string $dbName = 'pgsql';

    public function testInitUsers(): array
    {
        $this->db->execute('TRUNCATE TABLE "test_user";');

        // 当前驱动批量插入不兼容，会产生类型错误
        $userData = $this->provideTestData();
        foreach ($userData as $datum) {
            $this->db->table('test_user')->insert($datum);
        }

        return $userData;
    }
}
