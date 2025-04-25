<?php
declare (strict_types = 1);

namespace tests\orm;

use DateTime;
use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class TimeFieldTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        // 创建测试表
        Db::execute('DROP TABLE IF EXISTS `test_time_field`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_time_field` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(32) NOT NULL,
    `status` tinyint(4) NOT NULL DEFAULT '0',
    `create_time` datetime DEFAULT NULL,
    `update_time` datetime DEFAULT NULL,
    `delete_time` datetime DEFAULT NULL,
    `custom_time` datetime DEFAULT NULL,
    `date_field` date DEFAULT NULL,
    `timestamp_field` int(11) DEFAULT NULL,
    `time_with_format` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );

        // 创建自定义时间字段名称的测试表
        Db::execute('DROP TABLE IF EXISTS `test_custom_time_field`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_custom_time_field` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(32) NOT NULL,
    `created_at` datetime DEFAULT NULL,
    `updated_at` datetime DEFAULT NULL,
    `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_time_field`;');
        Db::execute('TRUNCATE TABLE `test_custom_time_field`;');
    }

    /**
     * 测试自动时间戳
     */
    public function testAutoTimestamp()
    {
        // 测试创建时自动写入时间
        $model       = new TimeFieldModel();
        $model->name = 'test1';
        $model->save();

        $this->assertTrue(! empty($model->create_time), '创建时间应该存在');
        $this->assertTrue(! empty($model->update_time), '更新时间应该存在');

        // 测试更新时自动更新时间
        $oldUpdateTime = $model->update_time;
        sleep(1); // 确保时间有变化

        $model->name = 'test1_updated';
        $model->save();

        $this->assertTrue($oldUpdateTime != $model->update_time, '更新后的时间应该不同');

        // 确保数据库中的更新时间也已更新
        $data = Db::table('test_time_field')->where('id', $model->id)->find();
        $this->assertTrue($oldUpdateTime != $data['update_time'], '数据库中的更新时间应该已更新');
    }

    /**
     * 测试禁用自动时间戳
     */
    public function testDisableAutoTimestamp()
    {
        // 创建禁用自动时间戳的模型
        $model       = new DisabledTimeModel();
        $model->name = 'test_no_auto';
        $model->save();

        $this->assertNull($model->create_time);
        $this->assertNull($model->update_time);

        // 确保数据库中也没有时间戳
        $data = Db::table('test_time_field')->where('id', $model->id)->find();
        $this->assertNull($data['create_time']);
        $this->assertNull($data['update_time']);
    }

    /**
     * 测试自定义时间字段名称
     */
    public function testCustomTimeFieldNames()
    {
        $model       = new CustomTimeFieldModel();
        $model->name = 'custom_time_test';
        $model->save();

        $this->assertNotEmpty($model->created_at);
        $this->assertNotEmpty($model->updated_at);

        // 确保数据库中也有时间戳
        $data = Db::table('test_custom_time_field')->where('id', $model->id)->find();
        $this->assertNotEmpty($data['created_at']);
        $this->assertNotEmpty($data['updated_at']);

        // 测试更新时自动更新时间
        $oldUpdatedAt = $model->updated_at;
        sleep(1); // 确保时间有变化

        $model->name = 'custom_time_updated';
        $model->save();

        $this->assertNotEquals($oldUpdatedAt, $model->updated_at);
    }

    /**
     * 测试不同的时间格式
     */
    public function testDifferentTimeFormats()
    {
        // 测试日期字段
        $model                   = new TimeFieldModel();
        $model->name             = 'format_test';
        $model->date_field       = '2023-05-15';
        $model->timestamp_field  = time();
        $model->time_with_format = '2023-05-15 10:30:45';
        $model->save();

        // 验证日期字段格式
        $this->assertEquals('2023-05-15', $model->date_field);

        // 验证时间戳字段
        $this->assertIsInt($model->timestamp_field);

        // 验证自定义格式时间字段
        $this->assertEquals('2023-05-15 10:30:45', $model->time_with_format);

        // 从数据库重新获取并验证
        $model = TimeFieldModel::find($model->id);
        $this->assertEquals('2023-05-15', $model->date_field);
        $this->assertIsInt($model->timestamp_field);
        $this->assertEquals('2023-05-15 10:30:45', $model->time_with_format);
    }

    /**
     * 测试时间字段的不同输入类型
     */
    public function testDifferentTimeInputTypes()
    {
        // 测试字符串时间
        $model1              = new TimeFieldModel();
        $model1->name        = 'string_time';
        $model1->custom_time = '2023-06-20 15:30:00';
        $model1->save();

        // 测试时间戳
        $model2              = new TimeFieldModel();
        $model2->name        = 'timestamp_time';
        $model2->custom_time = time();
        $model2->save();

        // 测试DateTime对象
        $model3              = new TimeFieldModel();
        $model3->name        = 'datetime_object';
        $model3->custom_time = new DateTime('2023-06-20 16:45:00');
        $model3->save();

        // 验证所有时间都被正确存储和格式化
        $this->assertStringContainsString('2023-06-20', $model1->custom_time);
        $this->assertNotEmpty($model2->custom_time);
        $this->assertStringContainsString('2023-06-20', $model3->custom_time);

        // 从数据库重新获取并验证
        $model1 = TimeFieldModel::find($model1->id);
        $model2 = TimeFieldModel::find($model2->id);
        $model3 = TimeFieldModel::find($model3->id);

        $this->assertStringContainsString('2023-06-20', $model1->custom_time);
        $this->assertNotEmpty($model2->custom_time);
        $this->assertStringContainsString('2023-06-20', $model3->custom_time);
    }

    /**
     * 测试时间查询
     */
    public function testTimeFieldQuery()
    {
        // 准备测试数据
        $dates = [
            ['name' => 'yesterday', 'custom_time' => date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['name' => 'today', 'custom_time' => date('Y-m-d H:i:s')],
            ['name' => 'tomorrow', 'custom_time' => date('Y-m-d H:i:s', strtotime('+1 day'))],
            ['name' => 'last_week', 'custom_time' => date('Y-m-d H:i:s', strtotime('-1 week'))],
            ['name' => 'next_week', 'custom_time' => date('Y-m-d H:i:s', strtotime('+1 week'))],
        ];

        foreach ($dates as $date) {
            $model              = new TimeFieldModel();
            $model->name        = $date['name'];
            $model->custom_time = $date['custom_time'];
            $model->save();
        }

        // 测试今天的记录
        $todayCount = TimeFieldModel::whereDay('custom_time')->count();
        $this->assertTrue($todayCount > 0, '应该有今天的记录');

        // 测试昨天的记录
        $yesterdayCount = TimeFieldModel::whereDay('custom_time', 'yesterday')->count();
        $this->assertTrue($yesterdayCount > 0, '应该有昨天的记录');

        // 测试本周的记录
        $thisWeekCount = TimeFieldModel::whereWeek('custom_time')->count();
        $this->assertTrue($thisWeekCount > 0, '应该有本周的记录');

        // 测试大于某个时间的记录
        $futureCount = TimeFieldModel::whereTime('custom_time', '>', date('Y-m-d'))->count();
        $this->assertTrue($futureCount > 0, '应该有未来的记录');

        // 测试在某个时间范围内的记录
        $rangeCount = TimeFieldModel::whereBetweenTime('custom_time', date('Y-m-d', strtotime('-2 day')), date('Y-m-d', strtotime('+2 day')))->count();
        $this->assertTrue($rangeCount > 0, '应该有时间范围内的记录');
    }

    /**
     * 测试软删除
     */
    public function testSoftDelete()
    {
        // 创建支持软删除的模型
        $model       = new SoftDeleteTimeModel();
        $model->name = 'soft_delete_test';
        $model->save();

        $id = $model->id;

        // 执行软删除
        $model->delete();

        // 验证删除时间已设置
        $this->assertNull($model->delete_time);

        // 确认在数据库中已标记为删除
        $data = Db::table('test_time_field')->where('id', $id)->find();
        $this->assertNotEmpty($data['delete_time']);

        // 验证默认查询不会返回已删除的记录
        $this->assertNull(SoftDeleteTimeModel::find($id));

        // 验证包含软删除的查询可以找到记录
        $model = SoftDeleteTimeModel::withTrashed()->find($id);
        $this->assertNotNull($model);
        $this->assertEquals('soft_delete_test', $model->name);

        // 测试恢复软删除
        $model->restore();

        // 确认在数据库中已恢复
        $data = Db::table('test_time_field')->where('id', $id)->find();
        $this->assertNull($data['delete_time']);

        // 验证现在可以正常查询到
        $this->assertNotNull(SoftDeleteTimeModel::find($id));
    }

    /**
     * 测试自定义时间格式
     */
    public function testCustomTimeFormat()
    {
        // 创建使用自定义时间格式的模型
        $model       = new CustomFormatTimeModel();
        $model->name = 'format_test';
        $model->save();

        // 验证时间格式是否符合预期
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $model->create_time);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $model->update_time);

        // 从数据库重新获取并验证
        $model = CustomFormatTimeModel::find($model->id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $model->create_time);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $model->update_time);
    }

    /**
     * 测试时间戳类型为整数
     */
    public function testIntegerTimestamp()
    {
        // 创建使用整数时间戳的模型
        $model       = new IntegerTimeModel();
        $model->name = 'integer_time_test';
        $model->timestamp_field = time();
        $model->save();

        // 从数据库重新获取并验证
        $model = IntegerTimeModel::find($model->id);
        $this->assertTrue(is_string($model->timestamp_field));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $model->timestamp_field);
    }

    /**
     * 测试 DateTime 类与时间字段的交互
     */
    public function testDateTimeField()
    {
        // 1. 测试将 DateTime 对象写入数据库
        $model       = new TimeFieldModel();
        $model->name = 'datetime_test';

        // 创建不同的 DateTime 对象并设置到模型字段
        $now          = new DateTime();
        $yesterday    = new DateTime('-1 day');
        $specificDate = '2023-05-20 15:30:45';

        $model->custom_time      = $now;
        $model->date_field       = $yesterday;
        $model->time_with_format = '2023-05-20 15:30:45';
        $model->save();

        // 验证存储到数据库的值
        $data = Db::table('test_time_field')->where('id', $model->id)->find();
        $this->assertTrue(! empty($data['custom_time']), 'DateTime 对象应该被正确存储');
        $this->assertTrue(! empty($data['date_field']), '日期字段应该被正确存储');

        // 3. 测试 DateTime 对象的比较查询
        // 准备测试数据
        $dates = [
            ['name' => 'past_date', 'custom_time' => new DateTime('-1 month')],
            ['name' => 'current_date', 'custom_time' => new DateTime()],
            ['name' => 'future_date', 'custom_time' => new DateTime('+1 month')],
        ];

        foreach ($dates as $date) {
            $model              = new DateTimeFieldModel();
            $model->name        = $date['name'];
            $model->custom_time = $date['custom_time'];
            $model->save();
        }


        // 4. 测试 DateTime 对象的格式化输出
        $model              = new DateTimeFormatModel();
        $model->name        = 'datetime_format_test';
        $model->custom_time = new DateTime('2023-12-25 18:30:00');
        $model->save();

        // 重新获取模型
        $model = DateTimeFormatModel::find($model->id);

        // 验证格式化输出
        $this->assertEquals('2023-12-25', $model->custom_time, '应该按照指定格式输出');
    }

}

/**
 * 基本时间字段测试模型
 */
class TimeFieldModel extends Model
{
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = true;
}

/**
 * 自定义时间字段名称的模型
 */
class CustomTimeFieldModel extends Model
{
    protected $table              = 'test_custom_time_field';
    protected $autoWriteTimestamp = true;
    protected $createTime         = 'created_at';
    protected $updateTime         = 'updated_at';
    protected $deleteTime         = 'deleted_at';
}

/**
 * 支持软删除的模型
 */
class SoftDeleteTimeModel extends Model
{
    use \think\model\concern\SoftDelete;
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = true;
    protected $deleteTime         = 'delete_time';
}

/**
 * 自定义时间格式的模型
 */
class CustomFormatTimeModel extends Model
{
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = true;
    protected $dateFormat         = 'Y-m-d';
}

/**
 * 整数时间戳模型
 */
class IntegerTimeModel extends Model
{
    protected $table              = 'test_time_field';
    protected $timestampField    = ['timestamp_field'];
}

/**
 * 禁用自动时间戳的模型
 */
class DisabledTimeModel extends Model
{
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = false;
}

/**
 * 将时间字段转换为 DateTime 对象的模型
 */
class DateTimeFieldModel extends Model
{
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = true;
    protected $dateFormat         = DateTime::class;

}

/**
 * 自定义时间格式的模型
 */
class DateTimeFormatModel extends Model
{
    protected $table              = 'test_time_field';
    protected $autoWriteTimestamp = true;
    protected $dateFormat         = 'Y-m-d';

    protected $type = [
        'custom_time' => 'datetime',
    ];
}
