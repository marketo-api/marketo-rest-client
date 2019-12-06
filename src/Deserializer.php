<?php

namespace CSD\Marketo;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\Deserializer as GuzzleDeserializer;
use GuzzleHttp\Command\Result;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Deserializer extends GuzzleDeserializer
{

    private $description;

    public function __construct(DescriptionInterface $description, $process, array $responseLocations = []) {
        // A local version because parent is private.
        $this->description = $description;
        parent::__construct($description, $process, $responseLocations);
    }

    public function __invoke(ResponseInterface $response, RequestInterface $request, CommandInterface $command) {
        // Use parent invoke process.
        $result = parent::__invoke($response, $request, $command);
        // But then convert the result if we can.
        if ($result instanceof Result) {
            $tmp = $this->description->getOperation($command->getName());
            $config = $tmp->toArray();
            $response_class = $config['responseClass'] ?? Response::class;
            return new $response_class($result->toArray());
        }

        return $result;
    }

}
