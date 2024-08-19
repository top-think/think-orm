<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\FieldTypeModel;
use tests\stubs\TestFieldJsonDTO;
use tests\stubs\TestFieldPhpDTO;
use think\facade\Db;

class ModelFieldTypeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_field_type`;');
        Db::execute(
            <<<SQL
CREATE TABLE `test_field_type` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `t_json` json DEFAULT NULL,
     `t_php` varchar(512) DEFAULT NULL,
     `bigint` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function testFieldTypeSelect()
    {
        $data = [
            ['id' => 1, 't_json' => '{"num1": 1, "str1": "a"}', 't_php' => (string) (new TestFieldPhpDTO(1, 'a')), 'bigint' => '0'],
            ['id' => 2, 't_json' => '{"num1": 2, "str1": "b"}', 't_php' => (string) (new TestFieldPhpDTO(2, 'b')), 'bigint' => '244791959321042944'],
            ['id' => 3, 't_json' => '{"num1": 3, "str1": "c"}', 't_php' => (string) (new TestFieldPhpDTO(3, 'c')), 'bigint' => '18374686479671623679'],
        ];

        (new FieldTypeModel())->insertAll($data);

        $result = Db::table('test_field_type')->select();
        $this->assertNotEmpty($result->count());
        foreach ($data as $index => $item) {
            $this->assertEquals($item, $result[$index]);
        }

        $result = FieldTypeModel::select();
        $this->assertNotEmpty($result->count());
        foreach ($result as $index => $item) {
            $this->assertEquals(TestFieldJsonDTO::fromData($data[$index]['t_json']), $item->t_json);
            $this->assertEquals((string) TestFieldPhpDTO::fromData($data[$index]['t_php']), (string) $item->t_php);
            $this->assertSame($data[$index]['bigint'], $item->bigint);
        }
    }

    /**
     * @depends testFieldTypeSelect
     */
    public function testFieldReadAndWrite()
    {
        /** @var FieldTypeModel $result */
        $result = FieldTypeModel::query()->where('id', '=', 3)->find();
        $result->t_json = new TestFieldJsonDTO(30, 'ddd');
        $result->t_php = new TestFieldPhpDTO(40, 'eee');
        $result->save();

        /** @var FieldTypeModel $result */
        $result = FieldTypeModel::query()->where('id', '=', 3)->find();
        $this->assertEquals(new TestFieldJsonDTO(30, 'ddd'), $result->t_json);
        $this->assertEquals((string) new TestFieldPhpDTO(40, 'eee'), (string) $result->t_php);
        $this->assertEquals($result->id, $result->t_php->getId());
    }

    /**
     * @depends testFieldTypeSelect
     */
    public function testFieldReadInvalid()
    {

        $model = new FieldTypeModel([
            'id' => 1,
            't_json' => '???Invalid',
            't_php' => '???Invalid',
        ]);
        $this->assertNull($model->t_json);
        $this->assertNull($model->t_php);
    }
}
