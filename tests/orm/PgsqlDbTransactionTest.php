<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlDbTransactionTest extends DbTransactionTestBase
{
    protected string $dbName = 'pgsql';
}
