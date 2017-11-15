<?php


namespace Resqu\Client\Protocol;


use Resqu\Client;
use Resqu\Client\JobDescriptor;

class Planner {


    public static function insertJob(\DateTime $nextRun, \DateInterval $recurrenceInterval,
            JobDescriptor $job) {
        $id = null;
        do {
            $id = microtime(true);
            $plannedJob = new PlannedJob($id, $nextRun, $recurrenceInterval, BaseJob::fromJobDescriptor($job));
            $plannedJob->moveAfter(time());
        } while (!Client::redis()->setNx(Key::plan($id), $plannedJob->toString()));

        $nextRun = $plannedJob->getNextRunTimestamp();

        Client::redis()->zadd(Key::planSchedule(), $nextRun, $nextRun);
        Client::redis()->rpush(Key::planTimestamp($nextRun), $plannedJob->getId());

        return $id;
    }

    public static function removeJob($id) {
        $plannedJob = self::getPlannedJob($id);
        Client::redis()->del(Key::plan($id));

        if ($plannedJob == null) {
            return false;
        }

        $timestamp = $plannedJob->getNextRunTimestamp();
        Client::redis()->lRem(Key::planTimestamp($timestamp), 0, $id);

        self::cleanupTimestamp($timestamp);

        return true;
    }

    /**
     * If there are no jobs for a given key/timestamp, delete references to it.
     * Used internally to remove empty planned: items in Redis when there are
     * no more jobs left to run at that timestamp.
     *
     * @param int $timestamp Matching timestamp for $key.
     */
    private static function cleanupTimestamp($timestamp) {
        $redis = Client::redis();

        if ($redis->llen(Key::planTimestamp($timestamp)) == 0) {
            $redis->del(Key::planTimestamp($timestamp));
            $redis->zrem(Key::planSchedule(), $timestamp);
        }
    }

    /**
     * @param $planId
     *
     * @return null|PlannedJob
     */
    private static function getPlannedJob($planId) {
        $data = Client::redis()->get(Key::plan($planId));
        if (!$data) {
            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return null;
        }

        return PlannedJob::fromArray($decoded);
    }
}