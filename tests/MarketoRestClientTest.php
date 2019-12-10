<?php

namespace CSD\Marketo\Tests;

use CSD\Marketo\Cache\CacheItem;
use CSD\Marketo\Client;
use CSD\Marketo\Response\AddCustomActivitiesResponse;
use CSD\Marketo\Response\GetCampaignResponse;
use CSD\Marketo\Response\GetCampaignsResponse;
use CSD\Marketo\Response\GetLeadPartitionsResponse;
use CSD\Marketo\Response\GetListResponse;
use CSD\Marketo\Response\GetListsResponse;
use CSD\Marketo\Response\GetPagingToken;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;

/**
 * @group marketo-rest-client
 * @coversDefaultClass \CSD\Marketo\Client
 */
class MarketoRestClientTest extends TestCase
{

    /**
     * @covers ::factory
     */
    public function testFactoryOauthRegistration() {
        $data = [
            'client_id' => 'test_id',
            'client_secret' => 'test secret',
            'marketo_client_id' => 'test munchkin client id',
            'munchkin_id' => 'test munchkin id',
        ];
        // If things are working correctly, oauth would make a request to the
        // token provider _outside_ of our stack. So we don't have a lot of
        // ways to interact with it. We can bypass it by warming the cache and
        // asserting that it is requested though. The token ID is a MD5 of the
        // request url so it will be static.
        $cache = $this->prophesize(CacheItemPoolInterface::class);
        $cache_key = 'oauth2-token-9543291ffdf03537ce1540edbb55a4fa';
        $cache_item = new CacheItem($cache_key, 'supersecuretoken', TRUE);
        $cache->getItem($cache_key)
            ->willReturn($cache_item)
            ->shouldBeCalledOnce();

        $client = Client::factory($data, $cache->reveal());
        self::assertTrue($client instanceof \CSD\Marketo\Client);

        /** @var \GuzzleHttp\HandlerStack $stack */
        $stack = $client->getHttpClient()->getConfig('handler');
        $handler = new MockHandler();
        $handler->append(function (RequestInterface $req, array $options) {
            $this->assertEquals(['Bearer supersecuretoken'], $req->getHeader('Authorization'));
        });
        $stack->setHandler($handler);
        $stack(new Request('GET', 'http://example.com/'), []);
    }

    public function provideBadFactoryConfig() {
        return[
            [[], '/Config is missing the following keys: .*/'],
            [['client_id' => 'a'], '/Config is missing the following keys: .*/'],
            [['client_id' => 'a', 'version' => 'a'], '/Config is missing the following keys: .*/'],
            // This one is a little weird. A missing version looks like it would
            // throw an error but the default logic will always insert something.
            // [['client_id' => 'a', 'client_secret' => 'a'], '/Config is missing the following keys: .*/'],
            [['client_id' => 'a', 'client_secret' => 'a', 'version' => 'a', 'activityDate' => ''], '/Must provide either a URL or Munchkin code\./'],
        ];
    }

    /**
     * @covers ::factory
     * @dataProvider provideBadFactoryConfig
     */
    public function testFactoryBadConfig($config, $message) {
        $this->setExpectedExceptionRegExp(\InvalidArgumentException::class, $message);
        Client::factory($config);
    }

    /**
     * @covers ::factory
     */
    public function testFactoryOauthClientConfig() {
        $this->markTestIncomplete('It is difficult to test this configuration as it is stored deep in the middleware stack.');

        $data = [
            'client_id' => 'test_id',
            'client_secret' => 'test secret',
            'marketo_client_id' => 'test munchkin client id',
            'munchkin_id' => 'test munchkin id',
        ];
        $client = Client::factory($data);
        $config = $client->getHttpClient()->getConfig();
        $this->assertContains('client_id', $config, 'The `marketo_client_id` environment variable is empty.');
        $this->assertContains('client_secret', $config, 'The `marketo_client_secret` environment variable is empty.');
        $this->assertContains('munchkin_id', $config, 'The `marketo_munchkin_id` environment variable is empty.');
    }

    public function testExecutesCommands() {
        $response_json = '{"requestId": "f81c#157b104ca98","result": [{ "id": 1004, "name": "Foo", "description": " ", "type": "trigger", "workspaceName": "Default","createdAt": "2012-09-12T19:04:12Z","updatedAt": "2014-10-22T15:51:18Z","active": false}],"success": true}';

        // Queue up a response for getCampaigns request.
        $client = $this->getServiceClient($this->generateResponses(200, $response_json));
        $cmd = new Command('getCampaigns', [], $client->getHandlerStack());
        $response = $client->execute($cmd);
        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->getError());
    }

    public function testResponse() {
        $response_json = '{"requestId": "f81c#157b104ca98","result": [{ "id": 1004, "name": "Foo", "description": " ", "type": "trigger", "workspaceName": "Default","createdAt": "2012-09-12T19:04:12Z","updatedAt": "2014-10-22T15:51:18Z","active": false}],"success": true}';

        // Queue up a response for getCampaigns request.
        $client = $this->getServiceClient($this->generateResponses(200, $response_json));

        $response = $client->getCampaigns();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->getError());
        $this->assertNotEmpty($response->getRequestId());
        $this->assertNull($response->getNextPageToken());

        // @todo: figure out how to rest \CSD\Marketo\Response::fromCommand().
    }

    /**
     * @covers ::getCampaign
     * @covers ::getCampaigns
     */
    public function testGetCampaigns() {
        // Campaign response json.
        $response_json = '{"requestId": "f81c#157b104ca98","result": [{ "id": 1004, "name": "Foo", "description": " ", "type": "trigger", "workspaceName": "Default","createdAt": "2012-09-12T19:04:12Z","updatedAt": "2014-10-22T15:51:18Z","active": false}],"success": true}';

        // Queue up a response for getCampaigns as well as getCampaign (by ID).
        $client = $this->getServiceClient($this->generateResponses(200, [
            $response_json,
            $response_json,
        ]));

        $campaigns_response = $client->getCampaigns();
        $this->assertInstanceOf(GetCampaignsResponse::class, $campaigns_response);
        $campaigns = $campaigns_response->getResult();
        $this->assertNotEmpty($campaigns[0]['id']);

        $campaign_response = $client->getCampaign($campaigns[0]['id']);
        $this->assertInstanceOf(GetCampaignResponse::class, $campaign_response);
        $campaign = $campaign_response->getResult();
        $this->assertNotEmpty($campaign[0]['name']);
        $this->assertEquals($campaigns[0]['name'], $campaign[0]['name']);
    }

    /**
     * @covers ::getList
     * @covers ::getlists
     */
    public function testGetLists() {
        // Campaign response json.
        $response_json = '{"requestId":"5e2c#157b132e104","result":[{"id":1,"name":"Foo","description":"Foo description","programName":"Foo program name","workspaceName":"Default","createdAt":"2016-05-05T16:37:00Z","updatedAt":"2016-05-19T17:27:41Z"}],"success":true}';
        // Queue up a response for getLists as well as getList (by ID).
        $client = $this->getServiceClient($this->generateResponses(200,  [
            $response_json,
            $response_json,
        ]));

        $list_response = $client->getLists();
        $this->assertInstanceOf(GetListsResponse::class, $list_response);
        $lists = $list_response->getResult();
        $this->assertNotEmpty($lists[0]['id']);

        $list_response = $client->getList($lists[0]['id']);
        $this->assertInstanceOf(GetListResponse::class, $list_response);
        $list = $list_response->getResult();
        $this->assertNotEmpty($list[0]['name']);
        $this->assertEquals($lists[0]['name'], $list[0]['name']);
    }

    /**
     * @covers ::getLeadPartitions
     */
    public function testLeadPartitions() {
        $response_json = '{"requestId":"984e#157b140b012","result":[{"id":1,"name":"Default","description":"Initial system lead partition"}],"success":true}';

        // Queue up a response for getLeadPartitions request.
        $client = $this->getServiceClient($this->generateResponses(200, $response_json));

        $partitions_result = $client->getLeadPartitions();
        $this->assertInstanceOf(GetLeadPartitionsResponse::class, $partitions_result);
        $partitions = $partitions_result->getResult();
        $this->assertNotEmpty($partitions[0]['name']);
        $this->assertEquals($partitions[0]['name'], 'Default');
    }

    /**
     * @covers ::describeLeads
     * @covers ::describeObject
     */
    public function testDescribeLeads() {
        $response_json = '{"requestId":"fb0#157b1501f31","result":[{"id":48,"displayName":"First Name","dataType":"string","length":255,"rest":{"name":"firstName","readOnly":false},"soap":{"name":"FirstName","readOnly":false}},{"id":50,"displayName":"Last Name","dataType":"string","length":255,"rest":{"name":"lastName","readOnly":false},"soap":{"name":"LastName","readOnly":false}},{"id":51,"displayName":"Email Address","dataType":"email","length":255,"rest":{"name":"email","readOnly":false},"soap":{"name":"Email","readOnly":false}},{"id":60,"displayName":"Address","dataType":"text","rest":{"name":"address","readOnly":false},"soap":{"name":"Address","readOnly":false}}],"success":true}';

        // Queue up a response for describeLeads request.
        $client = $this->getServiceClient($this->generateResponses(200, $response_json));

        $leadFields_result = $client->describeLeads();
        $this->assertInstanceOf(Result::class, $leadFields_result);
        $leadFields = $leadFields_result->getResult();
        $this->assertEquals($leadFields[0]['displayName'], 'First Name');
    }

    /**
     * Test dynamic calls
     *
     * @coversNothing
     */
    public function testGetActivityTypes() {
        $response_json = '{"requestId":"6e78#148ad3b76f1","success":true,"result":[{"id":2,"name":"Fill Out Form","description":"User fills out and submits form on web page","primaryAttribute":{"name":"Webform ID","dataType":"integer"},"attributes":[{"name":"Client IP Address","dataType":"string"},{"name":"Form Fields","dataType":"text"},{"name":"Query Parameters","dataType":"string"},{"name":"Referrer URL","dataType":"string"},{"name":"User Agent","dataType":"string"},{"name":"Webpage ID","dataType":"integer"}]}]}';

        // Queue up a response for getActivityTypes request.
        $client = $this->getServiceClient($this->generateResponses(200, $response_json));

        /** @var \CSD\Marketo\Response $response */
        $response = $client->getActivityTypes();
        $this->assertInstanceOf(Result::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->getError());
        $this->assertEquals('Fill Out Form', $response->getResult()[0]['name']);
    }

    /**
     * Test making calls to lead activity and making paging token calls.
     *
     * @covers ::getPagingToken
     * @covers ::getLeadActivity
     */
    public function testGetLeadActivity() {
        // Queue up a response for getActivityTypes, getPagingToken and getLeadActivity requests.
        $client = $this->getServiceClient($this->generateResponses(200, [
            '{"requestId":"6e78#148ad3b76f1","success":true,"result":[{"id":2,"name":"Fill Out Form","description":"User fills out and submits form on web page","primaryAttribute":{"name":"Webform ID","dataType":"integer"},"attributes":[{"name":"Client IP Address","dataType":"string"},{"name":"Form Fields","dataType":"text"},{"name":"Query Parameters","dataType":"string"},{"name":"Referrer URL","dataType":"string"},{"name":"User Agent","dataType":"string"},{"name":"Webpage ID","dataType":"integer"}]}]}',
            '{"requestId":"f84c#157b16681eb","success":true,"nextPageToken":"JXBIK3O6SUWULQ12345678Y57ZJCBBZRGHQV57IZSKSLYLLU6PPQ===="}',
            '{"requestId":"24fd#15188a88d7f","result":[{"id":102988,"leadId":1,"activityDate":"2015-01-16T23:32:19Z","activityTypeId":1,"primaryAttributeValueId":71,"primaryAttributeValue":"localhost/munchkintest2.html","attributes":[{"name":"Client IP Address","value":"10.0.19.252"},{"name":"Query Parameters","value":""},{"name":"Referrer URL","value":""},{"name":"User Agent","value":"Mozilla/5.0(Windows NT6.1;WOW64)AppleWebKit/537.36(KHTML,like Gecko)Chrome/39.0.2171.95Safari/537.36"},{"name":"Webpage URL","value":"/munchkintest2.html"}]}],"success":true,"nextPageToken":"WQV2VQVPPCKHC6AQYVK7JDSA3J62DUSJ3EXJGDPTKPEBFW3SAVUA====","moreResult":false}',
        ]));


        // Get activity types, needed for $activityTypesIds.
        $activity_types = $client->getActivityTypes()->getResult();
        // Get only the ids of the activity types.
        $activity_types_ids = array_map(function ($type) {
            return $type['id'];
        }, $activity_types);

        $paging_response = $client->getPagingToken(date('c'));
        $this->assertInstanceOf(GetPagingToken::class, $paging_response);

        /** @var \CSD\Marketo\Response $response */
        $response = $client->getLeadActivity(
            $paging_response->getNextPageToken(),
            [1],
            array_slice($activity_types_ids, 0, 10));
        $this->assertInstanceOf(Result::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->getError());
        $this->assertEquals('1', $response->getResult()[0]['activityTypeId']);
        $this->assertEquals('localhost/munchkintest2.html', $response->getResult()[0]['primaryAttributeValue']);
    }

    public function provideBadCustomActivities() {
        return[
            [[[]], '/Required parameter ".*" is missing\./'],
            [[['leadId' => 'a']], '/Required parameter ".*" is missing\./'],
            [[['leadId' => 'a', 'activityTypeId' => 'a']], '/Required parameter ".*" is missing\./'],
            [[['leadId' => 'a', 'primaryAttributeValue' => 'a']], '/Required parameter ".*" is missing\./'],
            [[['leadId' => 'a', 'activityTypeId' => 'a', 'primaryAttributeValue' => 'a', 'activityDate' => '']], '/Required parameter "activityDate" must be a DateTime object\./'],
        ];
    }

    /**
     * @dataProvider provideBadCustomActivities
     */
    public function testGetAddCustomActivities2($activities, $message) {

        // Queue up some valid responses
        $client = $this->getServiceClient($this->generateResponses(200, [
            '{"requestId":"16b08#1583f618888","result":[{"id":13847522,"status":"added"}],"success":true}',
            '{"requestId":"16b08#1583f618889","result":[{"id":13847165,"status":"added"},{"id":13847290,"status":"updated"}],"success":true}',
        ]));
        $this->setExpectedExceptionRegExp(\InvalidArgumentException::class, $message);
        $client->addCustomActivities($activities);
    }

    public function testGetAddCustomActivities() {
        // Queue up some valid responses
        $client = $this->getServiceClient($this->generateResponses(200, [
            '{"requestId":"16b08#1583f618888","result":[{"id":13847522,"status":"added"}],"success":true}',
            '{"requestId":"16b08#1583f618889","result":[{"id":13847165,"status":"added"},{"id":13847290,"status":"updated"}],"success":true}',
        ]));

        // Positive use case
        $activities = [
            [ // Example of minimum set of attributes for an activity
                'leadId' => 4,
                'activityTypeId' => 100002,
                'primaryAttributeValue' => 'FooBar'
            ]
        ];
        $response = $client->addCustomActivities($activities);
        $this->assertInstanceOf(AddCustomActivitiesResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('added', $response->getStatus());

        // Positive use case
        $activities = [
            [ // Example of minimum set of attributes for an activity
                'leadId' => 4,
                'activityTypeId' => 100002,
                'primaryAttributeValue' => 'FooBar',
            ],
            [ // Example of all optional attributes used
                'leadId' => 6,
                'activityTypeId' => 100003,
                'primaryAttributeValue' => 42,
                'activityDate' => new \DateTime('+1 day'),
                'apiName' => 'FooBar',
                'status' => 'updated',
                'attributes' => [
                    [
                        'name' => 'quantity',
                        'value' => 3,
                    ],
                    [
                        'name' => 'price',
                        'value' => 123.45,
                        'apiName' => 'FooBar',
                    ]
                ]
            ],
        ];
        $response = $client->addCustomActivities($activities);
        $this->assertInstanceOf(AddCustomActivitiesResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('added', $response->getStatus());
        $this->assertEquals('updated', $response->getStatus(13847290));
    }

}
