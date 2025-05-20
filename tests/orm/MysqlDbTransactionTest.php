<?php
declare(strict_types=1);

namespace tests\orm;

class MysqlDbTransactionTest extends DbTransactionTestBase
{
    protected static string $connectName = 'mysql';
}
