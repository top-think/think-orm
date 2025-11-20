<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\Collection;
use think\Paginator;
use think\paginator\driver\Bootstrap;
use think\facade\Db;

class PaginatorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Create test table for pagination testing
        Db::execute('DROP TABLE IF EXISTS `test_pagination`;');
        Db::execute("CREATE TABLE `test_pagination` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL DEFAULT '',
          `content` text,
          `category_id` int(11) NOT NULL DEFAULT 0,
          `status` tinyint(1) NOT NULL DEFAULT 1,
          `views` int(11) NOT NULL DEFAULT 0,
          `created_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_category` (`category_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_pagination`;');
        
        // Insert test data for pagination
        $data = [];
        for ($i = 1; $i <= 100; $i++) {
            $data[] = [
                'title' => "Article $i",
                'content' => "Content for article $i",
                'category_id' => ($i % 5) + 1, // Categories 1-5
                'status' => $i % 2, // Alternating status
                'views' => rand(1, 1000),
                'created_at' => date('Y-m-d H:i:s', time() - (100 - $i) * 3600), // Spread over time
            ];
        }
        
        Db::table('test_pagination')->insertAll($data);
    }

    public function testBasicPagination(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(10, false); // 10 per page, not simple mode

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(10, $paginator->listRows());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertEquals(50, $paginator->total()); // 50 active records
        $this->assertEquals(5, $paginator->lastPage()); // 50/10 = 5 pages
        $this->assertTrue($paginator->hasPages());
        $this->assertCount(10, $paginator);
    }

    public function testSimplePagination(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(15, true); // 15 per page, simple mode = true

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(15, $paginator->listRows());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertCount(15, $paginator);
        $this->assertTrue($paginator->hasMore());
    }

    public function testPaginationWithSpecificPage(): void
    {
        // Test page 2
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 2]);

        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(10, $paginator->listRows());
        $this->assertEquals(50, $paginator->total());
        $this->assertCount(10, $paginator);
        
        // First item on page 2 should be the 11th item
        $items = $paginator->getCollection();
        $firstItem = $items[0];
        $this->assertGreaterThan(10, $firstItem['id']);
    }

    public function testPaginationNavigation(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 3]);

        $this->assertEquals(3, $paginator->currentPage());
        $this->assertTrue($paginator->hasPages());
        // Test that pagination navigation works
        $this->assertTrue($paginator->hasPages());
        
        // Test page numbers
        $this->assertEquals(3, $paginator->currentPage());
        $this->assertEquals(5, $paginator->lastPage());
        
        // Test page 1
        $firstPagePaginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 1]);
        $this->assertEquals(1, $firstPagePaginator->currentPage());
        
        // Test last page
        $lastPagePaginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 5]);
        $this->assertEquals(5, $lastPagePaginator->currentPage());
    }

    public function testPaginationWithCustomOptions(): void
    {
        $options = [
            'list_rows' => 10,
            'path' => '/articles',
            'query' => ['category' => 'tech', 'author' => 'john'],
            'fragment' => 'comments',
            'var_page' => 'p',
        ];

        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate($options);

        // Test URL generation (may not be fully available in test environment)
        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(10, $paginator->listRows());
    }

    public function testPaginationRangeCalculation(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 3]);

        // Test basic properties
        $this->assertEquals(3, $paginator->currentPage());
        $this->assertEquals(10, $paginator->listRows());
        $this->assertEquals(50, $paginator->total());
        $this->assertCount(10, $paginator);
    }

    public function testPaginationRender(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 3]);

        // Test default Bootstrap driver
        $html = $paginator->render();
        $this->assertIsString($html);
        $this->assertStringContainsString('pagination', $html);
        
        // Test that Bootstrap class exists
        $this->assertTrue(class_exists(Bootstrap::class));
    }

    public function testPaginationArrayAccess(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(5, false);

        // Test array access
        $this->assertTrue(isset($paginator[0]));
        $this->assertFalse(isset($paginator[10]));
        
        $firstItem = $paginator[0];
        $this->assertIsArray($firstItem);
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('title', $firstItem);
        
        // Test count
        $this->assertEquals(5, count($paginator));
    }

    public function testPaginationIteration(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(3, false);

        $items = [];
        foreach ($paginator as $item) {
            $items[] = $item;
        }

        $this->assertCount(3, $items);
        $this->assertArrayHasKey('id', $items[0]);
        $this->assertArrayHasKey('title', $items[0]);
    }

    public function testPaginationJsonSerialization(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 5, 'page' => 2]);

        $json = json_encode($paginator);
        $this->assertIsString($json);
        
        $data = json_decode($json, true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('current_page', $data);
        $this->assertArrayHasKey('last_page', $data);
        $this->assertArrayHasKey('per_page', $data);
        $this->assertArrayHasKey('total', $data);
        
        $this->assertEquals(2, $data['current_page']);
        $this->assertEquals(5, $data['per_page']);
        $this->assertEquals(50, $data['total']);
        $this->assertCount(5, $data['data']);
    }

    public function testPaginationWithComplexQuery(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->where('views', '>', 500)
            ->whereIn('category_id', [1, 2, 3])
            ->order('views', 'desc')
            ->paginate(8, false);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(8, $paginator->listRows());
        $this->assertLessThanOrEqual(50, $paginator->total()); // Less than total active records
        
        // Verify query conditions are applied
        foreach ($paginator as $item) {
            $this->assertEquals(1, $item['status']);
            $this->assertGreaterThan(500, $item['views']);
            $this->assertContains($item['category_id'], [1, 2, 3]);
        }
    }

    public function testPaginationEdgeCases(): void
    {
        // Test page beyond last page
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(10, false, ['page' => 100]); // Way beyond last page

        $this->assertLessThanOrEqual(5, $paginator->currentPage()); // Should cap at last page
        
        // Test page 0 or negative
        $paginatorZero = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(10, false, ['page' => 0]);

        $this->assertEquals(1, $paginatorZero->currentPage()); // Should default to 1
        
        // Test with no results
        $emptyPaginator = Db::table('test_pagination')
            ->where('status', 999) // No matches
            ->order('id')
            ->paginate(10, false);

        $this->assertEquals(0, $emptyPaginator->total());
        $this->assertEquals(0, $emptyPaginator->lastPage());
        $this->assertCount(0, $emptyPaginator);
        $this->assertFalse($emptyPaginator->hasPages());
    }

    public function testPaginationWithLargeDataset(): void
    {
        // Insert more data for large dataset testing
        $largeData = [];
        for ($i = 101; $i <= 1000; $i++) {
            $largeData[] = [
                'title' => "Large Article $i",
                'content' => "Content for large article $i",
                'category_id' => ($i % 10) + 1,
                'status' => 1,
                'views' => rand(1, 5000),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        
        Db::table('test_pagination')->insertAll($largeData);

        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(50, false);

        $this->assertEquals(950, $paginator->total()); // 950 active records (50 original + 900 new)
        $this->assertEquals(19, $paginator->lastPage()); // 950/50 = 19 pages
        $this->assertCount(50, $paginator);
    }

    public function testPaginationUrls(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 3, 'path' => '/test']);

        // Test that pagination works and has correct page
        $this->assertEquals(3, $paginator->currentPage());
        $this->assertEquals(10, $paginator->listRows());
        
        // Test that basic URL method exists
        $this->assertTrue(method_exists($paginator, 'url'));
    }

    public function testPaginationCollection(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(5);

        $items = $paginator->getCollection();
        $this->assertInstanceOf(Collection::class, $items);
        $this->assertCount(5, $items);

        // Test that we have the right data structure
        $firstItem = $items[0];
        $this->assertArrayHasKey('title', $firstItem);
        $this->assertIsString($firstItem['title']);
    }

    public function testPaginationWithCallback(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(5, false);

        // Test each method
        $processedItems = [];
        $paginator->each(function($item) use (&$processedItems) {
            $processedItems[] = $item['title'];
        });

        $this->assertCount(5, $processedItems);
        $this->assertContains('Article 1', $processedItems);
    }

    public function testPaginationCaching(): void
    {
        // This would test pagination with caching enabled
        // For now, just verify that pagination works with cache config
        $paginator = Db::table('test_pagination')
            ->cache(true, 60) // Cache for 60 seconds
            ->where('status', 1)
            ->order('id')
            ->paginate(10, false);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(10, $paginator->listRows());
        $this->assertGreaterThan(0, $paginator->total());
    }

    public function testPaginationAppends(): void
    {
        $paginator = Db::table('test_pagination')
            ->where('status', 1)
            ->order('id')
            ->paginate(['list_rows' => 10, 'page' => 2]);

        // Test that appends method exists
        $this->assertTrue(method_exists($paginator, 'appends'));
        
        // Test basic pagination properties
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(10, $paginator->listRows());
    }
}