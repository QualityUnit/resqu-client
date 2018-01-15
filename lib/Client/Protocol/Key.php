<?php


namespace Resqu\Client\Protocol;


class Key {

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
     * @param string $name
     *
     * @return string
     */
    public static function queue($name) {
        return Key::of('queue', $name);
    }

    /**
     * @return string
     */
    public static function queues() {
        return 'queues';
    }

    public static function uniqueDeferred($uniqueId) {
        return self::of('unique', $uniqueId, 'deferred');
    }

    public static function uniqueState($uniqueId) {
        return self::of('unique', $uniqueId, 'state');
    }

    /**
     * @param string $sourceId
     * @param string $jobName
     * @return string
     */
    public static function unassignedQueue($sourceId, $jobName) {
        return self::of('unassigned', $sourceId, $jobName);
    }

    public static function unassignedSet() {
        return self::of('unassigned');
    }

    private static function of(...$parts) {
        return implode(':', $parts);
    }
}