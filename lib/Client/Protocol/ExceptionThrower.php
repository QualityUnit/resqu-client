<?php

namespace Resqu\Client\Protocol;

class ExceptionThrower {

    const CODE_RESCHEDULE = -1;
    const CODE_RETRY = -2;

    /**
     * @param int $delay in seconds
     *
     * @throws \RuntimeException
     */
    public function reschedule($delay = 0) {
        $data = json_encode(['delay' => $delay]);

        throw new \RuntimeException($data, self::CODE_RESCHEDULE);
    }

    /**
     * @throws \RuntimeException
     */
    public function retry() {
        throw new \RuntimeException('', self::CODE_RETRY);
    }
}