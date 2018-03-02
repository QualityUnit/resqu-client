<?php


namespace Resqu;

use Exception;
use Resqu\Client\Exception\DeferredException;
use Resqu\Client\Exception\PlanExistsException;
use Resqu\Client\Exception\RedisException;
use Resqu\Client\Exception\UniqueException;
use Resqu\Client\JobDescriptor;
use Resqu\Client\Protocol\BaseJob;
use Resqu\Client\Protocol\Batch;
use Resqu\Client\Protocol\ExceptionThrower;
use Resqu\Client\Protocol\Key;
use Resqu\Client\Protocol\PlannedJob;
use Resqu\Client\Protocol\Planner;
use Resqu\Client\Protocol\UnassignedJob;
use Resqu\Client\Protocol\UniqueList;
use Resqu\Client\Redis;

class Client {

    const PROTOCOL_VERSION = 'resqu-v4';

    /** @var string */
    private static $redisServer;
    /** @var Redis */
    private static $redis;

    /**
     * @param int $timeToLive time since last push in seconds
     *
     * @return Batch
     * @throws RedisException
     */
    public static function createBatch($timeToLive) {
        return new Batch(self::redis(), $timeToLive);
    }

    /**
     * @param string $batchId
     *
     * @return null|Batch
     * @throws RedisException
     */
    public static function loadBatch($batchId) {
        return Batch::load(self::redis(), $batchId);
    }

    /**
     * @param JobDescriptor $job
     *
     * @return string Job ID when the job was created
     * @throws DeferredException
     * @throws UniqueException
     * @throws RedisException
     */
    public static function enqueue(JobDescriptor $job) {
        $baseJob = BaseJob::fromJobDescriptor($job);
        UniqueList::add($baseJob);
        $unassignedJob = new UnassignedJob($baseJob);

        self::redis()->lPush(Key::unassignedQueue(), $unassignedJob->toString());

        return $unassignedJob->getId();
    }

    /**
     * @param int $delay Number of seconds from now when the job should be executed.
     * @param JobDescriptor $job
     *
     * @throws DeferredException
     * @throws UniqueException
     * @throws RedisException
     */
    public static function enqueueDelayed($delay, JobDescriptor $job) {
        $baseJob = BaseJob::fromJobDescriptor($job);
        UniqueList::add($baseJob);

        $enqueueAt = time() + $delay;
        self::redis()->rPush(Key::delayed($enqueueAt), $baseJob->toString());
        self::redis()->zAdd(Key::delayedQueueSchedule(), $enqueueAt, $enqueueAt);
    }

    /**
     * @param \DateTime $startDate
     * @param \DateInterval $recurrencePeriod
     * @param JobDescriptor $job
     * @param string|null $providedId
     *
     * @return string Plan identifier
     * @throws PlanExistsException
     * @throws RedisException
     */
    public static function planCreate(\DateTime $startDate, \DateInterval $recurrencePeriod,
        JobDescriptor $job, $providedId = null) {
        return Planner::insertPlan($startDate, $recurrencePeriod, $job, $providedId);
    }

    /**
     * @param string $source
     * @param string $planId
     *
     * @return null|PlannedJob
     * @throws RedisException
     */
    public static function planGet($source, $planId) {
        return Planner::getPlannedJob($source, $planId);
    }

    /**
     * @param string $source
     *
     * @return string[] Return planned ids per source
     * @throws RedisException
     */
    public static function planGetIds($source) {
        return Planner::getPlannedIds($source);
    }

    /**
     * @param string $source Source identifier
     * @param string $planId Plan identifier
     *
     * @return boolean
     * @throws RedisException
     */
    public static function planRemove($source, $planId) {
        return Planner::removePlan($source, $planId);
    }

    /**
     * @return Redis
     * @throws RedisException
     */
    public static function redis() {
        if (self::$redis !== null) {
            return self::$redis;
        }

        self::$redis = new Redis(self::$redisServer);

        return self::$redis;
    }

    /**
     * Given a host/port combination separated by a colon, set it as
     * the redis server that Resqu will talk to.
     *
     * @param string|mixed[] $server Host/port combination separated by a colon,
     *                      or DSN-formatted URI,
     *                      or a nested array of servers with host/port pairs.
     */
    public static function setBackend($server) {
        self::$redisServer = $server;
        self::resetRedis();
    }

    /**
     * @return ExceptionThrower
     */
    public static function throwException() {
        return new ExceptionThrower();
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