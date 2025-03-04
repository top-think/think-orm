<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\FieldTypeModel;
use tests\stubs\UserStatus;
use think\facade\Db;
use think\model\type\Date;
use think\model\type\DateTime;

class ModelFieldTypeTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_field_type`;');
        Db::execute(
            <<<SQL
CREATE TABLE `test_field_type` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `bigint` bigint UNSIGNED DEFAULT NULL,
     `int_field` int NOT NULL DEFAULT 0,
     `float_field` float NOT NULL DEFAULT 0,
     `bool_field` tinyint(1) NOT NULL DEFAULT 0,
     `string_field` varchar(255) DEFAULT NULL,
     `array_field` json DEFAULT NULL,
     `object_field` json DEFAULT NULL,
     `json_field` json DEFAULT NULL,
     `date_field` date DEFAULT NULL,
     `datetime_field` datetime DEFAULT NULL,
     `timestamp_field` timestamp NULL DEFAULT NULL,
     `status` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function testFieldTypeSelect()
    {
        $data = [
            [
                'id'              => 1,
                'bigint'          => '0',
                'int_field'       => 100,
                'float_field'     => 1.23,
                'bool_field'      => true,
                'string_field'    => 'test1',
                'array_field'     => ['x' => 1, 'y' => 2],
                'object_field'    => json_decode(json_encode(['name' => 'obj1'])),
                'json_field'      => json_decode('{"num1": 1, "str1": "a"}'),
                'date_field'      => '2023-12-01',
                'datetime_field'  => '2023-12-01 10:00:00',
                'timestamp_field' => '2023-12-01 10:00:00',
                'status'          => 'active',
            ],
            [
                'id'              => 2,
                'bigint'          => '244791959321042944',
                'int_field'       => 200,
                'float_field'     => 2.34,
                'bool_field'      => false,
                'string_field'    => 'test2',
                'array_field'     => ['x' => 3, 'y' => 4],
                'object_field'    => json_decode(json_encode(['name' => 'obj2'])),
                'json_field'      => json_decode('{"num1": 2, "str1": "b"}'),
                'date_field'      => '2023-12-02',
                'datetime_field'  => '2023-12-02 11:00:00',
                'timestamp_field' => '2023-12-02 11:00:00',
                'status'          => 'inactive',
            ],
        ];

        foreach ($data as $index => $item) {
            $model = FieldTypeModel::create($item);
            $this->assertEquals($item, $model->toArray());
        }
    }

    public function testEnumTypeConversion()
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enum types are only supported in PHP 8.1+');
        }

        // 测试写入时的枚举类型转换
        $testData = [
            'status' => UserStatus::Active,
        ];
        $result = FieldTypeModel::create($testData);
        $this->assertEquals('active', $result->status);

        // 测试数据库实际存储值
        $dbResult = Db::table('test_field_type')->where('id', $result->id)->find();
        $this->assertEquals('active', $dbResult['status']);

        // 测试从数据库读取时的枚举类型转换
        $model = FieldTypeModel::find($result->id);
        $this->assertEquals('active', $model->status);

        // 测试更新枚举类型
        $model->status = UserStatus::Inactive;
        $model->save();
        $dbResult = Db::table('test_field_type')->where('id', $result->id)->find();
        $this->assertEquals('inactive', $dbResult['status']);
    }

    public function testBasicTypeConversion()
    {
        $testData = [
            'int_field'       => '123',
            'float_field'     => '123.45',
            'bool_field'      => '1',
            'string_field'    => 123,
            'array_field'     => ['a' => 1, 'b' => 2],
            'object_field'    => ['name' => 'test', 'value' => 100],
            'date_field'      => '2023-12-25',
            'datetime_field'  => '2023-12-25 12:34:56',
            'timestamp_field' => '2023-12-25 12:34:56',
        ];

        // 测试写入时的类型转换
        $result = FieldTypeModel::create($testData);
        $array  = $result->toArray();
        $this->assertIsInt($result->int_field);
        $this->assertEquals(123, $result->int_field);

        $this->assertIsFloat($result->float_field);
        $this->assertEquals(123.45, $result->float_field);

        $this->assertIsBool($result->bool_field);
        $this->assertTrue($result->bool_field);

        $this->assertIsString($result->string_field);
        $this->assertEquals('123', $result->string_field);

        $this->assertIsArray($result->array_field);
        $this->assertEquals(['a' => 1, 'b' => 2], $result->array_field);

        $this->assertIsObject($result->object_field);
        $this->assertEquals('test', $result->object_field->name);

        $this->assertEquals('2023-12-25', $result->date_field);
        $this->assertEquals('2023-12-25', $array['date_field']);

        $this->assertEquals('2023-12-25 12:34:56', $result->datetime_field);
        $this->assertEquals('2023-12-25 12:34:56', $array['datetime_field']);

        $this->assertEquals('2023-12-25 12:34:56', $result->timestamp_field);
        $this->assertEquals('2023-12-25 12:34:56', $array['timestamp_field']);

        // 测试数据库实际存储值
        $dbResult = Db::table('test_field_type')->where('id', $result->id)->find();
        $this->assertEquals(123, $dbResult['int_field']);
        $this->assertEquals(123.45, $dbResult['float_field']);
        $this->assertEquals(1, $dbResult['bool_field']);
        $this->assertEquals('123', $dbResult['string_field']);
        $this->assertEquals(['a' => 1, 'b' => 2], json_decode($dbResult['array_field'], true));
        $this->assertEquals(['name' => 'test', 'value' => 100], json_decode($dbResult['object_field'], true));
        $this->assertEquals('2023-12-25', $dbResult['date_field']);
        $this->assertEquals('2023-12-25 12:34:56', $dbResult['datetime_field']);
        $this->assertEquals('2023-12-25 12:34:56', $dbResult['timestamp_field']);
    }

    public function testModelOutput()
    {
        $testData = [
            'int_field'    => 123,
            'float_field'  => 123.45,
            'bool_field'   => true,
            'string_field' => 'test',
            'array_field'  => ['a' => 1, 'b' => 2],
            'object_field' => ['name' => 'test'],
            'date_field'   => '2023-12-25',
        ];

        $result = FieldTypeModel::create($testData);

        // 测试toArray输出
        $array = $result->toArray();
        $this->assertIsArray($array);
        $this->assertEquals($testData['int_field'], $array['int_field']);
        $this->assertEquals($testData['float_field'], $array['float_field']);
        $this->assertEquals($testData['bool_field'], $array['bool_field']);
        $this->assertEquals($testData['string_field'], $array['string_field']);
        $this->assertEquals($testData['array_field'], $array['array_field']);

        // 测试toJson输出
        $json = $result->toJson();
        $this->assertJson($json);

        // 测试hidden属性
        $result->hidden(['bool_field', 'float_field']);
        $hiddenArray = $result->toArray();
        $this->assertArrayNotHasKey('bool_field', $hiddenArray);
        $this->assertArrayNotHasKey('float_field', $hiddenArray);

        // 测试visible属性
        $result->visible(['int_field', 'string_field']);
        $visibleArray = $result->toArray();
        $this->assertCount(2, $visibleArray);
        $this->assertArrayHasKey('int_field', $visibleArray);
        $this->assertArrayHasKey('string_field', $visibleArray);

        // 测试append属性
        $result->append(['full_name']);
        $appendArray = $result->toArray();
        $this->assertArrayHasKey('full_name', $appendArray);
        $this->assertEquals('test_' . $testData['string_field'], $appendArray['full_name']);
    }
}
