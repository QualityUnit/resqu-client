<?php


namespace Resqu\Client\Exception;

use Resqu\Client\ResquException;

class PlanExistsException extends ResquException {

    /**
     * @param string $planId
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($planId, $code = 0, \Throwable $previous = null) {
        parent::__construct("Plan $planId already exists.", $code, $previous);
    }
}