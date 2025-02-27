<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;
use think\model\View;

/**
 * 视图模型测试
 */
class ModelViewTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_user_view`;',
            'CREATE TABLE `test_user_view` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `status` tinyint NOT NULL DEFAULT 0,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_order_view`;',
            'CREATE TABLE `test_order_view` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `order_no` varchar(50) NOT NULL DEFAULT "",
                `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user_view`;');
        Db::execute('TRUNCATE TABLE `test_order_view`;');

        // 插入测试数据
        $users = [
            ['name' => '张三', 'status' => 1, 'create_time' => '2023-01-01 12:00:00'],
            ['name' => '李四', 'status' => 1, 'create_time' => '2023-01-02 12:00:00'],
        ];
        Db::table('test_user_view')->insertAll($users);

        $orders = [
            ['user_id' => 1, 'order_no' => 'ORDER001', 'amount' => 100.00, 'create_time' => '2023-01-01 12:00:00'],
            ['user_id' => 1, 'order_no' => 'ORDER002', 'amount' => 200.00, 'create_time' => '2023-01-01 13:00:00'],
            ['user_id' => 2, 'order_no' => 'ORDER003', 'amount' => 300.00, 'create_time' => '2023-01-02 12:00:00'],
        ];
        Db::table('test_order_view')->insertAll($orders);
    }

    public function testBasicView()
    {
        // 测试基本查询
        $result = UserOrderView::select()->toArray();
        $this->assertCount(3, $result);
        $this->assertEquals('张三', $result[0]['name']);
        $this->assertEquals('ORDER001', $result[0]['order_no']);
        $this->assertEquals(100.00, $result[0]['amount']);
        $this->assertEquals('2023-01-01 12:00:00', $result[0]['create_time']);
    }

    public function testViewWithCondition()
    {
        // 测试条件查询
        $model = new UserOrderConditionView;
        $result = $model
            ->where('test_user_view.status', 1)
            ->where('test_order_view.amount', '>', 100)
            ->order('test_order_view.amount', 'desc')
            ->select()
            ->toArray();

        $this->assertCount(2, $result);
        $this->assertEquals(300.00, $result[0]['amount']);
        $this->assertEquals('李四', $result[0]['name']);
        $this->assertEquals('2023-01-02 12:00:00', $result[0]['create_time']);
    }

    public function testViewWithJoinType()
    {
        // 测试右连接查询
        $model = new UserOrderJoinTypeView;
        $result = $model->select()->toArray();
        $this->assertCount(3, $result);

        // 验证连接结果
        $orderAmounts = array_column($result, 'amount');
        sort($orderAmounts);
        $this->assertEquals([100.00, 200.00, 300.00], $orderAmounts);
    }    
}

// 定义基本视图模型
class UserOrderView extends View
{
    public function query($query)
    {
        $query->view('test_user_view', 'id as user_id,name,status')
            ->view('test_order_view', 'order_no,amount,create_time', 'test_user_view.id=test_order_view.user_id');
    }
}


// 定义带条件的视图模型
class UserOrderConditionView extends View
{
    public function query($query)
    {
        $query->view('test_user_view', 'id as user_id,name,status,create_time')
            ->view('test_order_view', 'order_no,amount', 'test_user_view.id=test_order_view.user_id');
    }
}

// 定义带连接类型的视图模型
class UserOrderJoinTypeView extends View
{
    public function query($query)
    {
        $query->view('test_user_view', 'id as user_id,name,status')
            ->view('test_order_view', 'order_no,amount', 'test_user_view.id=test_order_view.user_id', 'RIGHT');
    }
}