<?php


namespace Prometheus\Storage;


class RedisTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @expectedException \Prometheus\Exception\StorageException
     * @expectedExceptionMessage Can't connect to Redis server
     */
    public function itShouldThrowAnExceptionOnConnectionFailure()
    {
        $redis = new Redis(array('host' => 'doesntexist.test'));
        $redis->flushRedis();
    }

}
