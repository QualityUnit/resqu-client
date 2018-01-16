<?php


namespace Resqu;

use Exception;
use Resqu\Client\Exception\DeferredException;
use Resqu\Client\Exception\PlanExistsException;
use Resqu\Client\Exception\RedisException;
use Resqu\Client\Exception\UniqueException;
use Resqu\Client\JobDescriptor;
use Resqu\Client\Protocol\BaseJob;
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
    /** @var string */
    private static $redisDatabase;
    /** @var Redis */
    private static $redis;

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
        $unassignedJob = new UnassignedJob($baseJob, self::generateKey());

        self::redis()->sAdd(Key::unassignedSet(), "{$job->getSourceId()}:{$job->getName()}");
        self::redis()->rPush(Key::unassignedQueue($job->getSourceId(), $job->getName()), $unassignedJob->toString());

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
        return Planner::insertJob($startDate, $recurrencePeriod, $job, $providedId);
    }

    /**
     * @param string $id Plan identifier
     *
     * @return boolean
     * @throws RedisException
     */
    public static function planRemove($id) {
        return Planner::removeJob($id);
    }

    /**
     * @param string $id
     *
     * @return null|PlannedJob
     * @throws RedisException
     */
    public static function planGet($id) {
        return Planner::getPlannedJob($id);
    }

    /**
     * @return Redis
     * @throws RedisException
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

    private static function generateKey() {
        return uniqid(substr(md5(gethostname()), 0, 8), true);
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