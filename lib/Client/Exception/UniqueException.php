<?php

namespace Resqu\Client\Exception;


use Resqu\Client\ResquException;

class UniqueException extends ResquException {

    public function __construct($uniqueId) {
        parent::__construct("A job with unique id '$uniqueId' is already in queue", 0, null);
    }

}