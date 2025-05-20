<?php
declare(strict_types=1);

namespace tests\orm;

class PgsqlModelOneToOneTest extends ModelOneToOneBase
{
    protected static string $dbName = 'pgsql';
}
