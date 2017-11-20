<?php


namespace Resqu;

use Exception;
use Resqu\Client\Exception\UniqueException;
use Resqu\Client\JobDescriptor;
use Resqu\Client\Redis;

class Client {

    const PROTOCOL_VERSION = 'resqu-v4';

    /** @var string */
    private static $redisServer = null;
    /** @var string */
    private static $redisDatabase = null;
    /** @var Redis */
    private static $redis = null;

    /**
     * @param JobDescriptor $job
     *
     * @return string Job ID when the job was created
     * @throws UniqueException
     */
    public static function enqueue(JobDescriptor $job) {
        // TODO
    }

    /**
     * @param int $delay Number of seconds from now when the job should be executed.
     * @param JobDescriptor $job
     */
    public static function enqueueDelayed($delay, JobDescriptor $job) {
        // TODO
    }

    /**
     * @param \DateTime $startDate
     * @param \DateInterval $recurrencePeriod
     * @param JobDescriptor $job
     *
     * @return string Plan identifier
     */
    public static function planCreate(\DateTime $startDate, \DateInterval $recurrencePeriod,
            JobDescriptor $job) {
        // TODO
    }

    /**
     * @param string $id Plan identifier
     *
     * @return boolean
     */
    public static function planRemove($id) {
        // TODO
    }

    /**
     * @return Redis
     */
    public static function redis() {
        if (self::$redis !== null) {
            return self::$redis;
        }

        self::$redis = new Redis(self::$redisServer, self::$redisDatabase);

        return self::$redis;
    }

    /**
     * Given a host/port combination separated by a colon, set it as
     * the redis server that Resqu will talk to.
     *
     * @param string|mixed[] $server Host/port combination separated by a colon,
     *                      or DSN-formatted URI,
     *                      or a nested array of servers with host/port pairs.
     * @param int $database
     */
    public static function setBackend($server, $database = 0) {
        self::$redisServer = $server;
        self::$redisDatabase = $database;
        self::resetRedis();
    }

    private static function resetRedis() {
        if (self::$redis === null) {
            return;
        }
        try {
            self::$redis->close();
        } catch (Exception $ignore) {
        }
        self::$redis = null;
    }
}