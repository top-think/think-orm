<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelManyToManyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_student`;',
            'CREATE TABLE `test_student` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `email` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_course`;',
            'CREATE TABLE `test_course` (
                `id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL DEFAULT "",
                `credit` int NOT NULL DEFAULT 0,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_student_course`;',
            'CREATE TABLE `test_student_course` (
                `id` int NOT NULL AUTO_INCREMENT,
                `student_id` int NOT NULL,
                `course_id` int NOT NULL,
                `score` decimal(5,2) DEFAULT NULL,
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_student_id` (`student_id`),
                KEY `idx_course_id` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_student`;');
        Db::execute('TRUNCATE TABLE `test_course`;');
        Db::execute('TRUNCATE TABLE `test_student_course`;');

        // 创建测试数据
        $student1 = StudentModel::create([
            'name' => 'student1',
            'email' => 'student1@example.com'
        ]);

        $student2 = StudentModel::create([
            'name' => 'student2',
            'email' => 'student2@example.com'
        ]);

        $course1 = CourseModel::create([
            'title' => 'Math',
            'credit' => 3
        ]);

        $course2 = CourseModel::create([
            'title' => 'English',
            'credit' => 2
        ]);

        $course3 = CourseModel::create([
            'title' => 'Physics',
            'credit' => 4
        ]);

        // 建立关联关系
        $student1->courses()->attach($course1->id, ['score' => 85.5]);
        $student1->courses()->attach($course2->id, ['score' => 92.0]);
        $student2->courses()->attach($course2->id, ['score' => 88.5]);
        $student2->courses()->attach($course3->id, ['score' => 90.0]);
    }

    public function testManyToManySync()
    {
        // 测试基本同步功能
        $student = StudentModel::find(1);
        $result = $student->courses()->sync([2, 3]); // 同步为English和Physics课程

        $this->assertTrue($result['attached'] === [3]); // 新增Physics
        $this->assertTrue($result['detached'] === [1]); // 移除Math
        $this->assertTrue($result['updated'] === [2]); // 保持English

        $courses = $student->courses()->select();
        $this->assertCount(2, $courses);
        $this->assertEquals(['English', 'Physics'], $courses->column('title'));

        // 测试带额外数据的同步
        $syncData = [
            2 => ['score' => 95.0], // 更新English成绩
            3 => ['score' => 88.0]  // 更新Physics成绩
        ];
        $result = $student->courses()->sync($syncData);

        $this->assertTrue($result['updated'] === [2, 3]); // 更新了两门课的成绩

        $courses = $student->courses()->select();
        foreach ($courses as $course) {
            if ($course->title === 'English') {
                $this->assertEquals(95.0, $course->pivot->score);
            } elseif ($course->title === 'Physics') {
                $this->assertEquals(88.0, $course->pivot->score);
            }
        }

        // 测试清空后重新同步
        $result = $student->courses()->sync([]);
        $this->assertTrue($result['detached'] === [2, 3]); // 移除所有课程
        $this->assertCount(0, $student->courses()->select());

        // 测试同步单个ID
        $result = $student->courses()->sync(1, ['score' => 91.0]);
        $this->assertTrue($result['attached'] === [1]);
        
        $course = $student->courses()->find();
        $this->assertEquals('Math', $course->title);
        $this->assertEquals(91.0, $course->pivot->score);
    }

    public function testManyToManyRelation()
    {
        // 测试关联获取
        $student = StudentModel::find(1);
        $this->assertNotNull($student);
        
        $courses = $student->courses;
        $this->assertCount(2, $courses);
        $this->assertEquals('Math', $courses[0]->title);

        // 测试预加载
        $student = StudentModel::with(['courses'])->find(1);
        $this->assertTrue($student->isRelationLoaded('courses'));
        $this->assertCount(2, $student->courses);

        // 测试中间表数据
        $student = StudentModel::find(1);
        $course = $student->courses()->where('test_course.title', 'Math')->find();
        $this->assertEquals(85.5, $course->pivot->score);

        // 测试关联统计
        $student = StudentModel::withCount('courses')->find(1);
        $this->assertEquals(2, $student->courses_count);

        // 测试新增关联
        $student = StudentModel::find(2);
        $result = $student->courses()->attach(1, ['score' => 87.5]);
        $this->assertTrue($result);

        // 测试解除关联
        $result = $student->courses()->detach(1);
        $this->assertTrue($result);
    }
}

class StudentModel extends Model
{
    protected $table = 'test_student';
    protected $autoWriteTimestamp = true;

    public function courses()
    {
        return $this->belongsToMany(CourseModel::class, 'test_student_course', 'course_id', 'student_id')
            ->withPivot(['score'])
            ->withTimestamp();
    }
}

class CourseModel extends Model
{
    protected $table = 'test_course';
    protected $autoWriteTimestamp = true;

    public function students()
    {
        return $this->belongsToMany(StudentModel::class, 'test_student_course', 'student_id', 'course_id')
            ->withPivot(['score'])
            ->withTimestamp();
    }
}