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
     */
    public function __construct(BaseJob $job) {
        $this->job = $job;
        $this->id = $this->generateKey();
        $this->queuedTime = microtime(true);
    }

    public static function fromArray(array $array) {
        $job = BaseJob::fromArray($array);
        $queuedJob = new self($job);
        $queuedJob->id = $array['id'];
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

    private function generateKey() {
        return uniqid(substr(md5(gethostname()), 0, 8), true);
    }
}