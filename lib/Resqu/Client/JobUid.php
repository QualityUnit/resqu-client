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
}