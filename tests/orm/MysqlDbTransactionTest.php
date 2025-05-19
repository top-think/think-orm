<?php
declare(strict_types=1);

namespace tests\orm;

class MysqlDbTransactionTest extends BaseDbTransactionTest
{
    protected string $dbName = 'mysql';
}
