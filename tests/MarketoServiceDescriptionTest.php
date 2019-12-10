<?php

namespace CSD\Marketo\Tests;

use CSD\Marketo\Client;

/**
 * @group marketo-rest-client
 */
class MarketoServiceDescriptionTest extends TestCase
{

    public function testServices() {
        $description = Client::createDescription();
        foreach ($description->getOperations() as $op => $operation) {
            $client = $this->getServiceClient($this->generateResponses(200, [
                '{"requestId":"e6be#157b5944116","success":true,"nextPageToken":"OAPD51234567890KBPLTBBZIC7KKF5FR5Y2VQGENTYVAOZ7EF3YQ===="}'
            ], TRUE));
            $client->execute($client->getCommand($op, []));
        }
    }

}
