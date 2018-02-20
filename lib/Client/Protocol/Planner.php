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
     * @param string $source
     *
     * @return string[]
     * @throws RedisException
     */
    public static function getPlannedIds($source) {
        $list = Client::redis()->sMembers(Key::planList($source));
        for ($i = 0; $i < count($list); $i++) {
            $id = self::getPlanId($source, $list[$i]);

            $list[$i] = $id;
        }

        return $list;
    }

    /**
     * @param string $source
     * @param string $planId
     *
     * @return null|PlannedJob
     * @throws RedisException
     */
    public static function getPlannedJob($source, $planId) {
        $id = self::createPlanId($source, $planId);
        $data = Client::redis()->get(Key::plan($id));
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
     * @param \DateTime $nextRun
     * @param \DateInterval $recurrenceInterval
     * @param JobDescriptor $job
     * @param string $providedId custom plan id to use
     *
     * @return string
     * @throws PlanExistsException
     * @throws RedisException
     */
    public static function insertPlan(\DateTime $nextRun, \DateInterval $recurrenceInterval, JobDescriptor $job, $providedId = null) {
        do {
            $id = self::createPlanId($job->getSourceId(), ($providedId ?: (string)microtime(true)));
            $plannedJob = new PlannedJob($id, $nextRun, $recurrenceInterval, BaseJob::fromJobDescriptor($job));
            $plannedJob->moveAfter(time());

            if (self::callInsertScript($plannedJob) !== false) {
                break;
            }

            if ($providedId !== null) {
                throw new PlanExistsException($id);
            }
        } while (true);

        return self::getPlanId($job->getSourceId(), $id);
    }

    /**
     * @param string $sourceId
     * @param string $id
     *
     * @return bool
     * @throws RedisException
     */
    public static function removePlan($sourceId, $id) {
        $plannedJob = self::getPlannedJob($sourceId, $id);
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

    private static function createPlanId($sourceId, $id) {
        return $sourceId . '_' . $id;
    }

    /**
     * @param string $source
     * @param string $id
     *
     * @return string
     */
    private static function getPlanId($source, $id) {
        list($prefix, $planId) = explode('_', $id, 2);
        if ($prefix !== $source || !$planId) {
            throw new \RuntimeException('Bad plan id format');
        }

        return $planId;
    }

}