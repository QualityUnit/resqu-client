<?php


namespace Resqu\Client\Protocol;


class UnassignedJob {

    /** @var string */
    private $id;
    /** @var float */
    private $queuedTime;
    /** @var BaseJob */
    private $job;

    /**
     * @param BaseJob $job
     * @param string $id
     */
    public function __construct(BaseJob $job, $id) {
        $this->job = $job;
        $this->id = $id;
        $this->queuedTime = microtime(true);
    }

    public static function fromArray(array $array) {
        $job = BaseJob::fromArray($array);
        $queuedJob = new self($job, $array['id']);
        $queuedJob->queuedTime = $array['queue_time'];

        return $queuedJob;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return BaseJob
     */
    public function getJob() {
        return $this->job;
    }

    /**
     * @return float
     */
    public function getQueuedTime() {
        return $this->queuedTime;
    }

    public function toArray() {
        $array = $this->job->toArray();
        $array['id'] = $this->id;
        $array['queue_time'] = $this->queuedTime;

        return $array;
    }

    public function toString() {
        return json_encode($this->toArray());
    }
}