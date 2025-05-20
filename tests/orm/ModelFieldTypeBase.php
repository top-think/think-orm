<?php
declare(strict_types=1);

namespace tests\orm;

use tests\stubs\FieldTypeModel;
use tests\stubs\TestFieldJsonDTO;
use tests\stubs\TestFieldPhpDTO;
use tests\TestCaseBase;

class ModelFieldTypeBase extends TestCaseBase
{
    protected function provideTestData(): array
    {
        return [
            ['id' => 1, 't_json' => '{"num1": 1, "str1": "a"}', 't_php' => (string) (new TestFieldPhpDTO(1, 'a')), 'bigint' => '0'],
            ['id' => 2, 't_json' => '{"num1": 2, "str1": "b"}', 't_php' => (string) (new TestFieldPhpDTO(2, 'b')), 'bigint' => '244791959321042944'],
            ['id' => 3, 't_json' => '{"num1": 3, "str1": "c"}', 't_php' => (string) (new TestFieldPhpDTO(3, 'c')), 'bigint' => '18374686479671623679'],
        ];
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::initModelSupport();
    }

    public function testInitData(): array
    {
        $this->db->execute('TRUNCATE TABLE test_field_type;');

        $data = $this->provideTestData();
        self::compatibleModelInsertAll(new FieldTypeModel(), $data);

        return $data;
    }

    /**
     * @depends testInitData
     */
    public function testFieldTypeSelect(array $data)
    {
        $result = $this->db->table('test_field_type')->setFieldType(['bigint' => 'string'])->select();
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
     * @depends testInitData
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
