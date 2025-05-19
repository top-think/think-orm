<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlDbTransactionTest extends BaseDbTransactionTest
{
    protected string $dbName = 'pgsql';
}
