<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlDbTest extends DbTestBase
{
    protected static string $connectName = 'pgsql';
}
