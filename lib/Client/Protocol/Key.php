<?php


namespace Resqu\Client\Protocol;


class Key {

    /**
     * @param string $sourceId
     * @param string $jobName
     * @param string $suffix
     *
     * @return string
     */
    public static function batchUncommitted($sourceId, $jobName, $suffix) {
        return self::of('uncommitted', $sourceId, $jobName, $suffix);
    }

    public static function batchCommittedSet() {
        return self::of('committed');
    }

    public static function batchCommitted($sourceId, $jobName, $suffix) {
        return self::of('committed', $sourceId, $jobName, $suffix);
    }

    /**
     * @param int $at
     *
     * @return string
     */
    public static function delayed($at) {
        return self::of('delayed', $at);
    }

    /**
     * @return string
     */
    public static function delayedQueueSchedule() {
        return 'delayed_queue_schedule';
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function jobStatus($id) {
        return self::of('job', $id, 'status');
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function plan($id) {
        return self::of('plan', $id);
    }

    public static function planSchedule() {
        return self::of('plan_schedule');
    }

    /**
     * @param int $timestamp
     *
     * @return string
     */
    public static function planTimestamp($timestamp) {
        return self::of('plan_schedule', $timestamp);
    }

    /**
     * @return string
     */
    public static function unassignedQueue() {
        return self::of('unassigned');
    }

    public static function uniqueDeferred($uniqueId) {
        return self::of('unique', $uniqueId, 'deferred');
    }

    public static function uniqueState($uniqueId) {
        return self::of('unique', $uniqueId, 'state');
    }

    private static function of(...$parts) {
        return implode(':', $parts);
    }
}