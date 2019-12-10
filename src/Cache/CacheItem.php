<?php

namespace CSD\Marketo\Cache;

use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    protected $key;
    protected $isHit;
    protected $value;

    public function __construct($key = NULL, $value = NULL, $isHit = FALSE) {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
    }

    /**
     * @inheritDoc
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function get() {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    public function isHit() {
        return $this->isHit;
    }

    /**
     * @inheritDoc
     */
    public function set($value) {
        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    public function expiresAt($expiration) {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function expiresAfter($time) {
        return $this;
    }

}
