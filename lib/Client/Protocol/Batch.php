<?php

namespace Resqu\Client\Protocol;

use Resqu\Client\Exception\BatchAlreadyCommittedException;
use Resqu\Client\Exception\BatchCommitException;
use Resqu\Client\Exception\BatchExpiredException;
use Resqu\Client\JobDescriptor;
use Resqu\Client\Redis;

class Batch {

    /**
     * KEYS [COMMITTED_LIST_KEY, COMMITTED_BATCH_KEY, UNCOMMITTED_BATCH_KEY]
     * ARGS [BATCH_ID]
     */
    const SCRIPT_COMMIT_BATCH = /** @lang Lua */
        <<<LUA
if 1 ~= redis.pcall('renamenx', KEYS[3], KEYS[2]) then
    return false
end
return redis.call('lpush', KEYS[1], ARGV[1])
LUA;
    /**
     * KEYS [UNCOMMITED_BATCH_KEY]
     * ARGS [PAYLOAD]
     */
    const SCRIPT_INITIALIZE_BATCH = /** @lang Lua */
        <<<LUA
if 1 == redis.call('exists', KEYS[1]) then
    return false
end
return redis.call('lpush', KEYS[1], ARGV[1])
LUA;
    /** @var Redis */
    private $redis;
    /** @var int */
    private $timeToLive;
    /** @var string */
    private $batchId;
    /** @var string */
    private $uncommittedKey;
    /** @var bool */
    private $committed = false;
    /** @var string */
    private $committedKey;

    /**
     * @param Redis $redis
     * @param string $batchId
     * @return null|Batch
     */
    public static function load(Redis $redis, $batchId) {
        $batch = new self($redis, 0);
        $batch->batchId = $batchId;
        $batch->committed = true;
        $batch->generateKeys();

        if ($redis->exists($batch->committedKey)) {
            return $batch;
        }

        return null;
    }

    /**
     * @param Redis $redis
     * @param int $timeToLive in seconds
     */
    public function __construct(Redis $redis, $timeToLive) {
        $this->timeToLive = $timeToLive;
        $this->redis = $redis;
    }

    /**
     * @return bool
     */
    public function isCommitted() {
        return $this->committed;
    }

    /**
     * @return int
     */
    public function getLength() {
        if ($this->isCommitted()) {
            return $this->redis->lLen($this->committedKey);
        }

        return $this->redis->lLen($this->uncommittedKey);
    }

    /**
     * @return string batch identifier
     * @throws BatchCommitException
     */
    public function commit() {
        if ($this->uncommittedKey === null) {
            throw new BatchCommitException("Can't commit empty batch.");
        }
        if (
            false === $this->redis->eval(self::SCRIPT_COMMIT_BATCH,
                [
                    Key::batchCommittedList(),
                    $this->committedKey,
                    $this->uncommittedKey
                ], [
                    $this->batchId
                ])
        ) {
            throw new BatchCommitException('Batch commit failed.');
        }

        $this->committed = true;

        return $this->batchId;
    }

    /**
     * @param JobDescriptor $job
     *
     * @throws BatchExpiredException
     * @throws BatchAlreadyCommittedException
     */
    public function push(JobDescriptor $job) {
        if ($this->committed) {
            throw new BatchAlreadyCommittedException('Batch has been already committed.');
        }

        $unassignedJob = new UnassignedJob(BaseJob::fromJobDescriptor($job));

        if ($this->batchId === null) {
            $this->initialize($unassignedJob);
        } else {
            if (0 === $this->redis->lPushX($this->uncommittedKey, $unassignedJob->toString())) {
                throw new BatchExpiredException('Batch expired.');
            }
        }

        $this->redis->expire($this->uncommittedKey, $this->timeToLive);
    }

    /**
     * @param string $sourceId
     * @param string $jobName
     */
    private function generateId($sourceId, $jobName) {
        $this->batchId = implode(':', [$sourceId, $jobName, substr(md5(uniqid('', true)), 0, 8)]);
    }

    private function generateKeys() {
        $this->uncommittedKey = Key::batchUncommitted($this->batchId);
        $this->committedKey = Key::batchCommitted($this->batchId);
    }

    /**
     * @param UnassignedJob $job
     */
    private function initialize(UnassignedJob $job) {
        $this->generateId($job->getJob()->getSourceId(), $job->getJob()->getName());
        $this->generateKeys();

        $result = $this->redis->eval(self::SCRIPT_INITIALIZE_BATCH,
            [$this->uncommittedKey],
            [$job->toString()]
        );

        if ($result === false) {
            $this->initialize($job);
        }
    }
}