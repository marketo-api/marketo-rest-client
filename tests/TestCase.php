<?php

namespace CSD\Marketo\Tests;

use CSD\Marketo\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as PhpunitTestCase;

abstract class TestCase extends PhpunitTestCase
{

    protected function getServiceClient(array $responses, MockHandler $mock = NULL, callable $commandToRequestTransformer = NULL) {
        $mock = $mock ?: new MockHandler();

        foreach ($responses as $response) {
            $mock->append($response);
        }

        $description = Client::createDescription();
        return new Client(
            new HttpClient([
                'handler' => $mock,
            ]),
            $description,
            null,
            Client::createDeserializer($description)
        );
    }

    protected function generateResponses($status_code, $response_data, $add_token_response = FALSE) {
        $responses = !$add_token_response ? [] : [
            new Response(200, [], '{"access_token": "0f9cc479-30ae-4d7a-b850-53bd9d44de45:sj","token_type": "bearer","expires_in": 3599,"scope": "smuvva+apiuser@tibco.com"}'),
        ];

        foreach ((array) $response_data as $item) {
            $json_string = is_array($item) ? json_encode($item) : $item;
            $responses[] = new Response($status_code, [], $json_string);
        }

        return $responses;
    }

}
