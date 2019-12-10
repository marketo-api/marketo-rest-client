<?php

namespace CSD\Marketo\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class StaticCachePool implements CacheItemPoolInterface {

    private $deferred = [];
    private $cache = [];

    /**
     * @inheritDoc
     */
    public function getItem($key) {
        if ($this->deferred) {
            $this->commit();
        }

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return new CacheItem($key);
    }

    /**
     * @inheritDoc
     */
    public function getItems(array $keys = array()) {
        if ($this->deferred) {
            $this->commit();
        }
        return $this->cache;
    }

    /**
     * @inheritDoc
     */
    public function hasItem($key) {
        if (isset($this->deferred[$key])) {
            $this->commit();
        }
        return isset($this->cache[$key]);
    }

    /**
     * @inheritDoc
     */
    public function clear() {
        $this->deferred = [];
        $this->cache = [];
    }

    /**
     * @inheritDoc
     */
    public function deleteItem($key) {
        unset($this->cache[$key]);
        unset($this->deferred[$key]);
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(array $keys) {
        foreach ($keys as $key) {
            unset($this->cache[$key]);
            unset($this->deferred[$key]);
        }
    }

    /**
     * @inheritDoc
     */
    public function save(CacheItemInterface $item) {
        $this->cache[$item->getKey()] = $item;
        return $this->commit();
    }

    /**
     * @inheritDoc
     */
    public function saveDeferred(CacheItemInterface $item) {
        $this->deferred[$item->getKey()] = $item;
        return TRUE;
    }

    /**
     * @inheritDoc
     */
    public function commit() {
        foreach ($this->deferred as $k => $v) {
            $this->cache[$k] = $v;
        }
        return TRUE;
    }

}
