<?php


namespace Resqu\Client\Protocol;


use Resqu\Client;
use Resqu\Client\Exception\DeferredException;
use Resqu\Client\Exception\RedisException;
use Resqu\Client\Exception\UniqueException;
use Resqu\Client\Log;

class UniqueList {

    const KEY_DEFERRED = 'deferred';
    const KEY_STATE = 'state';

    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE, JOB_PAYLOAD ]
     */
    const SCRIPT_ADD_DEFERRED = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if state ~= ARGV[1] then
    return false
end

return redis.call('SETNX', KEYS[2], ARGV[2])
LUA;

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    /**
     * @param BaseJob $job job to create unique record for
     * @param bool $ignoreFail if true, ignore already existing unique record
     *
     * @throws DeferredException if job was deferred and should not be queued
     * @throws UniqueException if adding wasn't successful and job could not be deferred
     * @throws RedisException
     */
    public static function add(BaseJob $job, $ignoreFail = false) {
        $uniqueId = $job->getUniqueId();

        if (!$uniqueId
            || Client::redis()->setNx(Key::uniqueState($uniqueId), self::STATE_QUEUED)
            || $ignoreFail) {
            return;
        }

        if ($job->getUid()->isDeferred() && self::addDeferred($job) !== false) {
            throw new DeferredException('Job was deferred.');
        }

        throw new UniqueException($job->getUniqueId());
    }

    /**
     * @param BaseJob $job
     *
     * @return array|bool|\Credis_Client|int|string
     * @throws RedisException
     */
    public static function addDeferred(BaseJob $job) {
        $uid = $job->getUid();
        if ($uid === null || !$uid->isDeferred()) {
            Log::error('Attempted to defer non-deferrable job.', [
                'payload' => $job->toArray()
            ]);
            throw new \RuntimeException('Only deferrable jobs can be deferred.');
        }

        return Client::redis()->eval(
            self::SCRIPT_ADD_DEFERRED,
            [
                Client::PROTOCOL_VERSION . Key::uniqueState($uid->getId()),
                Client::PROTOCOL_VERSION . Key::uniqueDeferred($uid->getId())
            ],
            [
                self::STATE_RUNNING,
                $job->toString()
            ]
        );
    }

    /**
     * @param string $uniqueId
     * @param string $newState
     *
     * @return bool true if the new state was set
     * @throws RedisException
     */
    public static function editState($uniqueId, $newState) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
            || Client::redis()->set(Key::uniqueState($uniqueId), $newState, ['XX']);
    }
}