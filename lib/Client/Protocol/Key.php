<?php


namespace Resqu\Client\Protocol;


class Key {

    /**
     * @param string $batchId
     *
     * @return string
     */
    public static function batchCommitted($batchId) {
        return self::of('committed', $batchId);
    }

    public static function batchCommittedList() {
        return self::of('committed');
    }

    /**
     * @param string $batchId
     *
     * @return string
     */
    public static function batchUncommitted($batchId) {
        return self::of('uncommitted', $batchId);
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