<?php


namespace Resqu\Client\Protocol;


use Resqu\Client;
use Resqu\Client\Exception\PlanExistsException;
use Resqu\Client\Exception\RedisException;
use Resqu\Client\JobDescriptor;
use Resqu\Client\Log;

class Planner {

    /**
     * KEYS [PLAN_SCHEDULE_KEY, PLAN_SCHEDULE_TIMESTAMP_KEY]
     * ARGS [TIMESTAMP]
     */
    const CLEAN_TIMESTAMP_SCRIPT = /* @lang Lua */
        <<<LUA
if 0==redis.call('llen', KEYS[2]) then
    redis.call('del', KEYS[2])
    redis.call('zrem', KEYS[1], ARGV[1])
end
LUA;
    /**
     * KEYS [PLAN_KEY, PLAN_SCHEDULE_KEY, PLAN_SCHEDULE_TIMESTAMP_KEY, PLAN_LIST_KEY]
     * ARGS [PLAN_DATA, NEXT_RUN_TIMESTAMP, PLAN_ID]\
     */
    const INSERT_SCRIPT = /* @lang Lua */
        <<<LUA
if 0==redis.call('setnx', KEYS[1], ARGV[1]) then
    return false
end
redis.call('zadd', KEYS[2], ARGV[2], ARGV[2])
redis.call('rpush', KEYS[3], ARGV[3])
redis.call('sadd', KEYS[4], ARGV[3])
return 1
LUA;


    /**
     * @param $planId
     *
     * @return null|PlannedJob
     * @throws RedisException
     */
    public static function getPlannedJob($planId) {
        $data = Client::redis()->get(Key::plan($planId));
        if (!$data) {
            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            Log::error('Failed to parse planned job.', [
                'payload' => $data
            ]);

            return null;
        }

        try {
            return PlannedJob::fromArray($decoded);
        } catch (\Exception $e) {
            Log::error('Failed to instantiate planned job.', [
                'exception' => $e,
                'payload' => $data
            ]);
        }

        return null;
    }

    /**
     * @param string $source
     *
     * @return null|string[]
     * @throws RedisException
     */
    public static function getPlansIds($source) {
        return Client::redis()->sMembers(Key::planList($source));
    }

    /**
     * @param \DateTime $nextRun
     * @param \DateInterval $recurrenceInterval
     * @param JobDescriptor $job
     * @param string $providedId custom plan id to use
     *
     * @return string
     * @throws PlanExistsException
     * @throws RedisException
     */
    public static function insertJob(\DateTime $nextRun, \DateInterval $recurrenceInterval,
        JobDescriptor $job, $providedId = null) {

        do {
            $id = $job->getSourceId() . '_' . ($providedId ?: (string)microtime(true));
            $plannedJob = new PlannedJob($id, $nextRun, $recurrenceInterval, BaseJob::fromJobDescriptor($job));
            $plannedJob->moveAfter(time());

            if (self::callInsertScript($plannedJob) !== false) {
                break;
            }

            if ($providedId !== null) {
                throw new PlanExistsException($id);
            }
        } while (true);

        return $id;
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws RedisException
     */
    public static function removeJob($id) {
        $plannedJob = self::getPlannedJob($id);
        Client::redis()->del(Key::plan($id));

        if ($plannedJob == null) {
            return false;
        }

        $timestamp = $plannedJob->getNextRunTimestamp();
        Client::redis()->lRem(Key::planTimestamp($timestamp), 0, $id);
        Client::redis()->sRem(Key::planList($plannedJob->getJob()->getSourceId()), $id);

        self::cleanupTimestamp($timestamp);

        return true;
    }

    /**
     * @param PlannedJob $plannedJob
     *
     * @return bool|int
     * @throws RedisException
     */
    private static function callInsertScript(PlannedJob $plannedJob) {
        $id = $plannedJob->getId();
        $nextRunTimestamp = $plannedJob->getNextRunTimestamp();

        return Client::redis()->eval(
            self::INSERT_SCRIPT,
            [
                Key::plan($id),
                Key::planSchedule(),
                Key::planTimestamp($nextRunTimestamp),
                Key::planList($plannedJob->getJob()->getSourceId())
            ],
            [
                $plannedJob->toString(),
                $nextRunTimestamp,
                $id
            ]
        );
    }

    /**
     * If there are no jobs for a given key/timestamp, delete references to it.
     * Used internally to remove empty planned: items in Redis when there are
     * no more jobs left to run at that timestamp.
     *
     * @param int $timestamp Matching timestamp for $key.
     *
     * @throws RedisException
     */
    private static function cleanupTimestamp($timestamp) {
        Client::redis()->eval(
            self::CLEAN_TIMESTAMP_SCRIPT,
            [
                Key::planSchedule(),
                Key::planTimestamp($timestamp)
            ],
            [$timestamp]
        );
    }
}