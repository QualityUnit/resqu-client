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
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $array) {
        if (!isset($array['class'], $array['sourceId'], $array['name'])) {
            throw new \InvalidArgumentException('Mandatory Job parameters missing');
        }

        $job = new self();

        $args = $job->args;
        if (isset($array['args'])) {
            if (!is_array($array['args'])) {
                throw new \InvalidArgumentException('Job \'args\' parameter must be an array.');
            }
            $args = $array['args'];
        }

        $job->class = isset($array['class']) ? $array['class'] : $job->class;
        $job->args =  $args;
        $job->sourceId = isset($array['sourceId']) ? $array['sourceId'] : $job->sourceId;
        $job->name = isset($array['name']) ? $array['name'] : $job->name;
        $job->includePath = isset($array['includePath']) ? $array['includePath'] : $job->includePath;
        $job->environment = isset($array['environment']) ? $array['environment'] : $job->environment;
        $uidValid = isset($array['unique']) && is_array($array['unique']);
        $job->uid = JobUid::fromArray($uidValid ? $array['unique'] : []);

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
        $job->includePath = $jobDescriptor->getIncludePath();
        $job->environment = $jobDescriptor->getEnvironment();

        return $job;
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
        return $this->uid !== null ? $this->uid->getId() : null;
    }

    /**
     * @return mixed[]
     */
    public function toArray() {
        return array_filter([
            'class' => $this->class,
            'sourceId' => $this->sourceId,
            'name' => $this->name,
            'args' => $this->args,
            'unique' => $this->uid === null ? null : $this->uid->toArray(),
            'includePath' => $this->includePath,
            'environment' => $this->environment,
        ]);
    }

    /**
     * @return string
     */
    public function toString() {
        return json_encode($this->toArray());
    }
}