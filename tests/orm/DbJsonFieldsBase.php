<?php

namespace tests\orm;

use tests\Base;
use think\Collection;
use think\facade\Db;

abstract class DbJsonFieldsBase extends Base
{
    protected function provideTestData(): array
    {
        $data = [
            ['id' => 1, 'name' => '肥皂', 'extend' => '{"brand": "TP6", "standard": null, "type": "清洁"}'],
            ['id' => 2, 'name' => '牙膏', 'extend' => '{"brand": "TP8", "standard": "大", "type": "清洁"}'],
            ['id' => 3, 'name' => '牙刷', 'extend' => '{"brand": "TP8", "standard": "大", "type": "清洁"}'],
            ['id' => 4, 'name' => '卫生纸', 'extend' => '{"brand": null, "standard": null, "type": "日用品" ,"amount": 20}'],
            ['id' => 5, 'name' => '香肠', 'extend' => '{"brand": null, "weight": 480, "type": "食品" ,"pack": 1}'],
        ];
        return array_map(fn ($item) => ['extend' => json_decode($item['extend'], true)] + $item, $data);
    }

    public function testInitGoods(): array
    {
        $this->db->execute('TRUNCATE TABLE test_goods;');

        $userData = $this->provideTestData();
        $this->db->table('test_goods')->json(['extend'])->insertAll($userData);

        return $userData;
    }

    /**
     * @test 测试当 json 字段的指定成员不存在
     * @depends testInitGoods
     */
    public function testJsonFieldMemberNotExists(array $goods)
    {
        $collect = collect($goods);

        $data = $this->db->table('test_goods')->where('extend->weight', null)->select();
        $this->assertSame($data->count(), $collect->where('extend.weight', null)->count());

        $data = $this->db->table('test_goods')->where('extend->amount', null)->select();
        $this->assertSame($data->count(), $collect->where('extend.amount', null)->count());

        $data = $this->db->table('test_goods')->where('extend->pack', null)->select();
        $this->assertSame($data->count(), $collect->where('extend.pack', null)->count());
    }

    /**
     * @test 测试当 json 字段的指定成员不存在或为 null
     * @depends testInitGoods
     */
    public function testJsonFieldMemberNotExistsOrNull(array $goods)
    {
        $collect = collect($this->provideTestData());

        $data = $this->db->table('test_goods')->where('extend->brand', null)->select();
        $this->assertSame($data->count(), $collect->where('extend.brand', null)->count());

        $data = $this->db->table('test_goods')->where('extend->standard', null)->select();
        $this->assertSame($data->count(), $collect->where('extend.standard', null)->count());
    }

    /**
     * @test 测试搜索 json 字段指定成员为指定的值
     * @depends testInitGoods
     */
    public function testJsonFieldMemberEqual(array $goods)
    {
        $collect = collect($goods);

        $data = $this->db->table('test_goods')->where('extend->brand', 'TP8')->select();
        $this->assertSame($data->count(), $collect->where('extend.brand', 'TP8')->count());

        $data = $this->db->table('test_goods')->where('extend->standard', '大')->select();
        $this->assertSame($data->count(), $collect->where('extend.standard', '大')->count());

        $data = $this->db->table('test_goods')->where('extend->type', '清洁')->select();
        $this->assertSame($data->count(), $collect->where('extend.type', '清洁')->count());
    }

    /**
     * @test 测试搜索 json 字段指定成员不为指定的值
     * @depends testInitGoods
     */
    public function testJsonFieldMemberNotEqual(array $goods)
    {
        $collect = collect($goods);

        $data = $this->db->table('test_goods')->where('extend->brand', '<>', 'TP8')->whereNull('extend->brand', "or")->select();
        $this->assertSame($data->count(), $collect->where('extend.brand', '<>', 'TP8')->count());

        $data = $this->db->table('test_goods')->where('extend->standard', '<>', '大')->whereNull('extend->standard', "or")->select();
        $this->assertSame($data->count(), $collect->where('extend.standard', '<>', '大')->count());

        $data = $this->db->table('test_goods')->where('extend->type', '<>', '清洁')->whereNull('extend->type', "or")->select();
        $this->assertSame($data->count(), $collect->where('extend.type', '<>', '清洁')->count());
    }
}
