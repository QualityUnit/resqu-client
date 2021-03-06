<?php


namespace Resqu\Client;


class JobUid {

    /** @var string */
    private $id;
    /** @var bool */
    private $isDeferred;
    /** @var int */
    private $deferralDelay;

    /**
     * @param string $id unique identifier
     * @param int $deferrableBy delay for deferral of a job in seconds, null if job should not be
     *         deferred
     */
    public function __construct($id, $deferrableBy = null) {
        $this->id = $id;
        $this->isDeferred = $deferrableBy !== null;
        $this->deferralDelay = max(0, $deferrableBy);
    }

    /**
     * @param mixed[] $array
     *
     * @return JobUid|null
     */
    public static function fromArray(array $array) {
        if (!isset($array['uid'])) {
            return null;
        }

        $deferralDelay = isset($array['deferrableBy']) ? $array['deferrableBy'] : null;

        return new self($array['uid'], $deferralDelay);
    }

    /**
     * @return int seconds
     */
    public function getDeferralDelay() {
        return $this->deferralDelay;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isDeferred() {
        return $this->isDeferred;
    }

    /**
     * @return mixed[]
     */
    public function toArray() {
        $result = [
            'uid' => $this->id
        ];
        if ($this->isDeferred) {
            $result['deferrableBy'] = $this->deferralDelay;
        }

        return $result;
    }
}