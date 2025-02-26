<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\FieldTypeModel;
use tests\stubs\TestFieldJsonDTO;
use tests\stubs\TestFieldPhpDTO;
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
     `t_json` json DEFAULT NULL,
     `t_php` varchar(512) DEFAULT NULL,
     `bigint` bigint UNSIGNED DEFAULT NULL,
     `int_field` int NOT NULL DEFAULT 0,
     `float_field` float NOT NULL DEFAULT 0,
     `bool_field` tinyint(1) NOT NULL DEFAULT 0,
     `string_field` varchar(255) DEFAULT NULL,
     `array_field` json DEFAULT NULL,
     `object_field` json DEFAULT NULL,
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
        $result = FieldTypeModel::where('id', '=', 3)->find();
        $result->t_json = new TestFieldJsonDTO(30, 'ddd');
        $result->t_php = new TestFieldPhpDTO(40, 'eee');
        $result->save();

        /** @var FieldTypeModel $result */
        $result = FieldTypeModel::where('id', '=', 3)->find();
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

    public function testEnumTypeConversion()
    {
        // 测试写入时的枚举类型转换
        $testData = [
            'status' => UserStatus::Active,
        ];
        $result = FieldTypeModel::create($testData);
        $this->assertInstanceOf(UserStatus::class, $result->status);
        $this->assertEquals(UserStatus::Active, $result->status);

        // 测试数据库实际存储值
        $dbResult = Db::table('test_field_type')->where('id', $result->id)->find();
        $this->assertEquals('active', $dbResult['status']);

        // 测试从数据库读取时的枚举类型转换
        $model = FieldTypeModel::find($result->id);
        $this->assertInstanceOf(UserStatus::class, $model->status);
        $this->assertEquals(UserStatus::Active, $model->status);

        // 测试更新枚举类型
        $model->status = UserStatus::Inactive;
        $model->save();
        $dbResult = Db::table('test_field_type')->where('id', $result->id)->find();
        $this->assertEquals('inactive', $dbResult['status']);

        // 测试无效的枚举值
        $model = new FieldTypeModel(['status' => 'invalid_status']);
        $this->assertNull($model->status);
    }

    public function testBasicTypeConversion()
    {
        $testData = [
            'int_field' => '123',
            'float_field' => '123.45',
            'bool_field' => '1',
            'string_field' => 123,
            'array_field' => ['a' => 1, 'b' => 2],
            'object_field' => ['name' => 'test', 'value' => 100],
            'date_field' => '2023-12-25',
            'datetime_field' => '2023-12-25 12:34:56',
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
        
        $this->assertInstanceOf(Date::class, $result->date_field);
        $this->assertEquals('2023-12-25', $result->date_field->format('Y-m-d'));
        $this->assertEquals('2023-12-25', $array['date_field']);
        
        $this->assertInstanceOf(DateTime::class, $result->datetime_field);
        $this->assertEquals('2023-12-25 12:34:56', $result->datetime_field->format('Y-m-d H:i:s'));
        $this->assertEquals('2023-12-25 12:34:56', $array['datetime_field']);
        
        $this->assertInstanceOf(DateTime::class, $result->timestamp_field);
        $this->assertEquals('2023-12-25 12:34:56', $result->timestamp_field->format('Y-m-d H:i:s'));
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
            'int_field' => 123,
            'float_field' => 123.45,
            'bool_field' => true,
            'string_field' => 'test',
            'array_field' => ['a' => 1, 'b' => 2],
            'object_field' => ['name' => 'test'],
            'date_field' => '2023-12-25',
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
        $decodedJson = json_decode($json, true);
        $this->assertEquals($array, $decodedJson);

        // 测试hidden属性
        $result->hidden(['int_field', 'float_field']);
        $hiddenArray = $result->toArray();
        $this->assertArrayNotHasKey('int_field', $hiddenArray);
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
