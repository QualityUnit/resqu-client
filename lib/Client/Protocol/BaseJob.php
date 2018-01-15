<?php


namespace Resqu\Client\Protocol;

use Resqu\Client\JobDescriptor;
use Resqu\Client\JobUid;

class BaseJob {

    /** @var string */
    protected $class;
    /** @var array */
    protected $args = [];
    /** @var string */
    protected $sourceId;
    /** @var string */
    protected $name;
    /** @var JobUid|null */
    protected $uid;
    /** @var boolean */
    protected $isMonitored = false;
    /** @var string */
    protected $includePath;
    /** @var string[] */
    protected $environment;

    private function __construct() {
    }

    /**
     * @param array $array
     *
     * @return self
     */
    public static function fromArray(array $array) {
        $job = new self();
        $job->class = isset($array['class']) ? $array['class'] : $job->class;
        $job->args = isset($array['args']) ? $array['args'] : $job->args;
        $job->sourceId = isset($array['sourceId']) ? $array['sourceId'] : $job->sourceId;
        $job->name = isset($array['name']) ? $array['name'] : $job->name;
        $job->isMonitored = isset($array['isMonitored']) ? $array['isMonitored'] : $job->isMonitored;
        $job->includePath = isset($array['includePath']) ? $array['includePath'] : $job->includePath;
        $job->environment = isset($array['environment']) ? $array['environment'] : $job->environment;
        $uidValid = isset($array['unique']) && is_array($array['unique']);
        $job->uid = self::uidFromArray($uidValid ? $array['unique'] : []);

        return $job;
    }

    /**
     * @param JobDescriptor $jobDescriptor
     *
     * @return self
     */
    public static function fromJobDescriptor(JobDescriptor $jobDescriptor) {
        $job = new self();
        $job->class = $jobDescriptor->getClass();
        $job->args = $jobDescriptor->getArgs();
        $job->sourceId = $jobDescriptor->getSourceId();
        $job->name = $jobDescriptor->getName();
        $job->uid = $jobDescriptor->getUid();
        $job->isMonitored = $jobDescriptor->isMonitored();
        $job->includePath = $jobDescriptor->getIncludePath();
        $job->environment = $jobDescriptor->getEnvironment();

        return $job;
    }


    /**
     * @param mixed[] $array
     *
     * @return JobUid|null
     */
    private static function uidFromArray(array $array) {
        if (!isset($array['uid'])) {
            return null;
        }

        $deferralDelay = isset($array['deferrableBy']) ? $array['deferrableBy'] : null;

        return new JobUid($array['uid'], $deferralDelay);
    }

    /**
     * @return array
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return string
     */
    public function getClass() {
        return $this->class;
    }

    /**
     * @return string[]
     */
    public function getEnvironment() {
        return $this->environment;
    }

    /**
     * @return string
     */
    public function getIncludePath() {
        return $this->includePath;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getSourceId() {
        return $this->sourceId;
    }

    /**
     * @return null|JobUid
     */
    public function getUid() {
        return $this->uid;
    }

    /**
     * @return null|string
     */
    public function getUniqueId() {
        return $this->uid === null ? null : $this->uid->getId();
    }

    /**
     * @return boolean
     */
    public function isMonitored() {
        return $this->isMonitored;
    }

    public function toArray() {
        return array_filter([
                'class' => $this->class,
                'args' => $this->args,
                'sourceId' => $this->sourceId,
                'name' => $this->name,
                'unique' => $this->getUidArray(),
                'isMonitored' => $this->isMonitored,
                'includePath' => $this->includePath,
                'environment' => $this->environment,
        ]);
    }

    public function toString() {
        return json_encode($this->toArray());
    }

    /**
     * @return mixed[]|null
     */
    private function getUidArray() {
        if ($this->uid === null) {
            return null;
        }

        $result = ['uid' => $this->uid->getId()];
        if ($this->uid->isDeferred()) {
            $result['deferrableBy'] = $this->uid->getDeferralDelay();
        }

        return $result;
    }
}