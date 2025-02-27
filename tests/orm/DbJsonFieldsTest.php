<?php

namespace tests\orm;

use tests\Base;
use think\Collection;
use think\facade\Db;

class DbJsonFieldsTest extends Base
{
    protected static string $table = 'test_goods';

    protected static array $testGoodsData;

    protected static Collection $testGoodsDataCollect;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `' . self::$table . '`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_goods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extend` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `' . self::$table . '`;');
        $data = [
            ['id' => 1, 'name' => '肥皂', 'extend' => '{"brand": "TP6", "standard": null, "type": "清洁"}'],
            ['id' => 2, 'name' => '牙膏', 'extend' => '{"brand": "TP8", "standard": "大", "type": "清洁"}'],
            ['id' => 3, 'name' => '牙刷', 'extend' => '{"brand": "TP8", "standard": "大", "type": "清洁"}'],
            ['id' => 4, 'name' => '卫生纸', 'extend' => '{"brand": null, "standard": null, "type": "日用品" ,"amount": 20}'],
            ['id' => 5, 'name' => '香肠', 'extend' => '{"brand": null, "weight": 480, "type": "食品" ,"pack": 1}'],
        ];
        self::$testGoodsData = $data;
        foreach ($data as &$item) {
            $item['extend'] = json_decode($item['extend'], true);
        }
        self::$testGoodsDataCollect = collect($data);
        Db::table(self::$table)->insertAll(self::$testGoodsData);
    }

    /**
     * @test 测试当 json 字段的指定成员不存在
     */
    public function testJsonFieldMemberNotExists()
    {
        $data = Db::table(self::$table)->where('extend->weight', null)->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.weight', null)->count());

        $data = Db::table(self::$table)->where('extend->amount', null)->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.amount', null)->count());

        $data = Db::table(self::$table)->where('extend->pack', null)->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.pack', null)->count());
    }

    /**
     * @test 测试当 json 字段的指定成员不存在或为 null
     */
    public function testJsonFieldMemberNotExistsOrNull()
    {
        $data = Db::table(self::$table)->where('extend->brand', null)->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.brand', null)->count());

        $data = Db::table(self::$table)->where('extend->standard', null)->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.standard', null)->count());
    }

    /**
     * @test 测试搜索 json 字段指定成员为指定的值
     */
    public function testJsonFieldMemberEqual()
    {
        $data = Db::table(self::$table)->where('extend->brand', 'TP8')->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.brand', 'TP8')->count());

        $data = Db::table(self::$table)->where('extend->standard', '大')->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.standard', '大')->count());

        $data = Db::table(self::$table)->where('extend->type', '清洁')->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.type', '清洁')->count());
    }

    /**
     * @test 测试搜索 json 字段指定成员不为指定的值
     */
    public function testJsonFieldMemberNotEqual()
    {
        $data = Db::table(self::$table)->where('extend->brand', '<>', 'TP8')->whereNull('extend->brand', "or")->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.brand', '<>', 'TP8')->count());

        $data = Db::table(self::$table)->where('extend->standard', '<>', '大')->whereNull('extend->standard', "or")->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.standard', '<>', '大')->count());

        $data = Db::table(self::$table)->where('extend->type', '<>', '清洁')->whereNull('extend->type', "or")->select();
        $this->assertSame($data->count(), self::$testGoodsDataCollect->where('extend.type', '<>', '清洁')->count());
    }
}
