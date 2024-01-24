<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Redis;
use Predis\Command\Argument\Geospatial\ByRadius;
use Predis\Command\Argument\Geospatial\FromLonLat;
use Tests\TestCase;

class RedisTest extends TestCase
{
    public function testPing()
    {
        $response = Redis::command('ping');
        self::assertEquals("PONG", $response);
        $response = Redis::ping();
        self::assertEquals("PONG", $response);
    }

    public function testString()
    {
        Redis::setEx("name", 2, "Eko");
        $response = Redis::get("name");
        self::assertEquals("Eko", $response);

        sleep(10);

        $response = Redis::get("name");
        self::assertNull($response);
    }

    public function testList()
    {
        Redis::del("names");
        Redis::rpush("names", "Syauqi");
        Redis::rpush("names", "Kurniawan");
        Redis::rpush("names", "Khannedy");
        $response = Redis::lrange("names", 0, -1);
        self::assertEquals(["Syauqi", "Damario", "Djohan"], $response);
        self::assertEquals("Syauqi", Redis::lpop("names"));
        self::assertEquals("Damario", Redis::lpop("names"));
        self::assertEquals("Djohan", Redis::lpop("names"));
    }

    public function testSet()
    {
        Redis::del("names");
        Redis::sadd("names", "Kunio");
        Redis::sadd("names", "Kunio");
        Redis::sadd("names", "Miyauchi");
        Redis::sadd("names", "Miyauchi");
        Redis::sadd("names", "Fuyuki");
        Redis::sadd("names", "Fuyuki");

        $response = Redis::smembers("names");
        self::assertEquals(["Kunio", "Miyauchi", "Fuyuki"], $response);
    }

    public function testSortedSet()
    {
        Redis::del("names");

        Redis::zadd("names", 100, "Toru");
        Redis::zadd("names", 90, "Fuyuki");
        Redis::zadd("names", 92, "Tetsuo");

        $response = Redis::zrange("names", 0, -1);
        self::assertEquals(["Toru", "Fuyuki", "Tetsuo"], $response);
    }

    public function testHash()
    {
        Redis::del("user:1");
        Redis::hset("user:1", "name", "Tetsuo");
        Redis::hset("user:1", "email", "tetsuo@gmail.com");
        Redis::hset("user:1", "age", 29);

        $response = Redis::hgetall("user:1");
        self::assertEquals([
            "name" => "Tetsuo",
            "email" => "tetsuo@gmail.com",
            "age" => "29"
        ], $response);
    }

    public function testGeoPoint()
    {
        Redis::del("sellers");
        Redis::geoadd("sellers", 106.822702, -6.177590, "Store A");
        Redis::geoadd("sellers", 106.820889, -6.174964, "Store B");

        $result = Redis::geodist("sellers", "Store A", "Store B", "m");
        self::assertEquals(0.3543, $result);

        $result = Redis::geodist("sellers", new FromLonLat(106.821825, -6.175105), new ByRadius(5, "m"));
        self::assertEquals(["Store A", "Store B"], $result);
    }

    public function testHyperLogLog()
    {
        Redis::pfadd("visitors", "anri", "minami");
        Redis::pfadd("visitors", "anri", "minami", "konomi");
        Redis::pfadd("visitors", "teruaki", "nagura", "konomi");

        $total = Redis::pfcount("visitors");
        self::assertEquals(7, $total);
    }

    public function testPipeline()
    {
        Redis::pipeline(function ($pipeline) {
            $pipeline->setex("name", 3, "Syauqi");
            $pipeline->setex("address", 2, "Indonesia");
        });

        $response = Redis::get("name");
        self::assertEquals("Eko", $response);
        $response = Redis::get("address");
        self::assertEquals("Indonesia", $response);
    }

    public function testTransaction()
    {
        Redis::transaction(function ($transaction) {
            $transaction->setex("name", 3, "Syauqi");
            $transaction->setex("address", 2, "Indonesia");
        });

        $response = Redis::get("name");
        self::assertEquals("Eko", $response);
        $response = Redis::get("address");
        self::assertEquals("Indonesia", $response);
    }

    public function testPublish()
    {
        for ($i = 0; $i < 10; $i++) {
            Redis::publish("channel-1", "Hello World $i");
            Redis::publish("channel-2", "Good Bye $i");
        }
        self::assertTrue(true);
    }

    public function testPublishStream()
    {
        for ($i = 0; $i < 10; $i++) {
            Redis::xadd("members", "*", [
                "name" => "Eko $i",
                "address" => "Indonesia"
            ]);
        }
        self::assertTrue(true);
    }

    public function testCreateConsumer()
    {
        Redis::xgroup("create", "members", "group1", "0");
        Redis::xgroup("createconsumer", "members", "group1", "consumer-1");
        Redis::xgroup("createconsumer", "members", "group1", "consumer-2");
        self::assertTrue(true);
    }

    public function testConsumerStream()
    {
        $result = Redis::xreadgroup("group1", "consumer-1", ["members" => ">"], 3, 3000);

        self::assertNotNull($result);
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
}
