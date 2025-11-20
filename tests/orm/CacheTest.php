<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\CacheItem;
use think\db\exception\InvalidArgumentException;
use DateTime;
use DateInterval;
use DateTimeInterface;

class CacheTest extends TestCase
{
    public function testCacheItemBasicOperations(): void
    {
        $cache = new CacheItem('test_key');
        
        // Test key operations
        $this->assertEquals('test_key', $cache->getKey());
        
        $cache->setKey('new_key');
        $this->assertEquals('new_key', $cache->getKey());
        
        // Test value operations
        $this->assertFalse($cache->isHit());
        
        $cache->set('test_value');
        $this->assertTrue($cache->isHit());
        $this->assertEquals('test_value', $cache->get());
    }

    public function testCacheItemWithoutKey(): void
    {
        $cache = new CacheItem();
        $this->assertNull($cache->getKey());
        
        $cache->setKey('dynamic_key');
        $this->assertEquals('dynamic_key', $cache->getKey());
    }

    public function testCacheItemTagOperations(): void
    {
        $cache = new CacheItem('tagged_key');
        
        // Test single tag
        $cache->tag('user');
        $this->assertEquals('user', $cache->getTag());
        
        // Test array tags
        $tags = ['user', 'profile', 'settings'];
        $cache->tag($tags);
        $this->assertEquals($tags, $cache->getTag());
        
        // Test null tag
        $cache->tag(null);
        $this->assertNull($cache->getTag());
    }

    public function testCacheItemExpirationWithInteger(): void
    {
        $cache = new CacheItem('expiring_key');
        
        // Set expiration with integer (seconds from now)
        $expireSeconds = 3600; // 1 hour
        $cache->expire($expireSeconds);
        
        // Get expire should return seconds remaining (approximately)
        $remaining = $cache->getExpire();
        $this->assertIsInt($remaining);
        $this->assertLessThanOrEqual($expireSeconds, $remaining);
        $this->assertGreaterThan($expireSeconds - 10, $remaining); // Allow some margin for execution time
    }

    public function testCacheItemExpirationWithDateTime(): void
    {
        $cache = new CacheItem('datetime_key');
        
        // Set expiration with DateTime
        $futureDate = new DateTime('+2 hours');
        $cache->expire($futureDate);
        
        $expiration = $cache->getExpire();
        $this->assertInstanceOf(DateTimeInterface::class, $expiration);
        $this->assertEquals($futureDate, $expiration);
    }

    public function testCacheItemExpirationWithDateInterval(): void
    {
        $cache = new CacheItem('interval_key');
        
        // Set expiration with DateInterval
        $interval = new DateInterval('PT30M'); // 30 minutes
        $beforeTime = time();
        $cache->expire($interval);
        $afterTime = time();
        
        $remaining = $cache->getExpire();
        $this->assertIsInt($remaining);
        // Should be approximately 30 minutes (1800 seconds)
        $this->assertGreaterThan(1790, $remaining);
        $this->assertLessThan(1810, $remaining);
    }

    public function testCacheItemExpirationWithNull(): void
    {
        $cache = new CacheItem('null_expire_key');
        
        // Set no expiration
        $cache->expire(null);
        $this->assertNull($cache->getExpire());
    }

    public function testCacheItemExpiresAt(): void
    {
        $cache = new CacheItem('expires_at_key');
        
        $futureDate = new DateTime('+1 day');
        $cache->expiresAt($futureDate);
        
        $expiration = $cache->getExpire();
        $this->assertInstanceOf(DateTimeInterface::class, $expiration);
        $this->assertEquals($futureDate, $expiration);
    }

    public function testCacheItemExpiresAfterWithInteger(): void
    {
        $cache = new CacheItem('expires_after_key');
        
        $seconds = 1800; // 30 minutes
        $beforeTime = time();
        $cache->expiresAfter($seconds);
        $afterTime = time();
        
        $remaining = $cache->getExpire();
        $this->assertIsInt($remaining);
        $this->assertGreaterThanOrEqual($seconds - 2, $remaining);
        $this->assertLessThanOrEqual($seconds, $remaining);
    }

    public function testCacheItemExpiresAfterWithDateInterval(): void
    {
        $cache = new CacheItem('expires_after_interval_key');
        
        $interval = new DateInterval('PT45M'); // 45 minutes
        $cache->expiresAfter($interval);
        
        $remaining = $cache->getExpire();
        $this->assertIsInt($remaining);
        // Should be approximately 45 minutes (2700 seconds)
        $this->assertGreaterThan(2690, $remaining);
        $this->assertLessThan(2710, $remaining);
    }

    public function testCacheItemInvalidExpiration(): void
    {
        $cache = new CacheItem('invalid_expire_key');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not support datetime');
        
        $cache->expire('invalid_date_string');
    }

    public function testCacheItemInvalidExpiresAfter(): void
    {
        $cache = new CacheItem('invalid_expires_after_key');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not support datetime');
        
        $cache->expiresAfter('invalid_interval');
    }

    public function testCacheItemComplexValues(): void
    {
        $cache = new CacheItem('complex_key');
        
        // Test with array
        $arrayValue = ['name' => 'John', 'age' => 30, 'roles' => ['admin', 'user']];
        $cache->set($arrayValue);
        $this->assertEquals($arrayValue, $cache->get());
        $this->assertTrue($cache->isHit());
        
        // Test with object
        $objectValue = (object) ['property' => 'value', 'nested' => ['data' => 123]];
        $cache->set($objectValue);
        $this->assertEquals($objectValue, $cache->get());
    }

    public function testCacheItemChaining(): void
    {
        $cache = new CacheItem();
        
        // Test method chaining
        $result = $cache->setKey('chained_key')
                       ->set('chained_value')
                       ->tag('chained_tag')
                       ->expire(3600);
        
        $this->assertSame($cache, $result);
        $this->assertEquals('chained_key', $cache->getKey());
        $this->assertEquals('chained_value', $cache->get());
        $this->assertEquals('chained_tag', $cache->getTag());
        $this->assertIsInt($cache->getExpire());
    }

    public function testCacheItemDefaultState(): void
    {
        $cache = new CacheItem('default_key');
        
        // Test default values
        $this->assertNull($cache->get());
        $this->assertFalse($cache->isHit());
        $this->assertNull($cache->getTag());
        $this->assertNull($cache->getExpire());
    }

    public function testCacheItemOverwriteValues(): void
    {
        $cache = new CacheItem('overwrite_key');
        
        // Set initial values
        $cache->set('initial_value');
        $cache->tag('initial_tag');
        $cache->expire(1800);
        
        $this->assertEquals('initial_value', $cache->get());
        $this->assertEquals('initial_tag', $cache->getTag());
        
        // Overwrite values
        $cache->set('new_value');
        $cache->tag('new_tag');
        $cache->expire(3600);
        
        $this->assertEquals('new_value', $cache->get());
        $this->assertEquals('new_tag', $cache->getTag());
        
        // Verify expire changed
        $remaining = $cache->getExpire();
        $this->assertGreaterThan(3500, $remaining);
    }

    public function testCacheItemBooleanAndNullValues(): void
    {
        $cache = new CacheItem('boolean_key');
        
        // Test boolean true
        $cache->set(true);
        $this->assertTrue($cache->get());
        $this->assertTrue($cache->isHit());
        
        // Test boolean false
        $cache->set(false);
        $this->assertFalse($cache->get());
        $this->assertTrue($cache->isHit());
        
        // Test null value
        $cache->set(null);
        $this->assertNull($cache->get());
        $this->assertTrue($cache->isHit());
        
        // Test zero
        $cache->set(0);
        $this->assertEquals(0, $cache->get());
        $this->assertTrue($cache->isHit());
        
        // Test empty string
        $cache->set('');
        $this->assertEquals('', $cache->get());
        $this->assertTrue($cache->isHit());
    }
}