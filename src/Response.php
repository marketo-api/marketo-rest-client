<?php
/*
 * This file is part of the Marketo REST API Client package.
 *
 * (c) 2014 Daniel Chesterton
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CSD\Marketo;

use GuzzleHttp\Command\Result;

/**
 * Base response class for Marketo API responses.
 *
 * @author Daniel Chesterton <daniel@chestertondevelopment.com>
 */
class Response extends Result
{

    /**
     * @return array|null
     */
    public function getResult()
    {
        return isset($this->data['result'])? $this->data['result']: null;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->data['requestId'];
    }

    /**
     * @return string
     */
    public function getNextPageToken()
    {
        return isset($this->data['nextPageToken'])? $this->data['nextPageToken']: null;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return true === $this->data['success'];
    }

    /**
     * @return array|null
     */
    public function getError()
    {
        if (isset($this->data['errors']) && count($this->data['errors'])) {
            return $this->data['errors'][0];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromCommand(OperationCommand $command)
    {
        return new static($command->getResponse()->json());
    }
}
