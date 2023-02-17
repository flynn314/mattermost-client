<?php
namespace Tests;

use Flynn314\Mattermost\MattermostClient;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * vendor/bin/phpunit tests/ClientTest.php
 */
class ClientTest extends TestCase
{
    private MattermostClient $mmClient;
    private string $channel;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $baseUrl = getenv('MM_BASE_URL');
        $token = getenv('MM_TOKEN');
        $this->channel = getenv('MM_CHANNEL');

        $httpClient = new Client();

        $this->mmClient = new MattermostClient($baseUrl, $token, $httpClient);
    }

    public function testMessageSend()
    {
        $message = 'Simple test at ' . date('H:i:s');
        $response = $this->mmClient->messagePost($this->channel, $message);

        $this->assertEquals($message, $response['message']);
    }

    public function testFileUpload()
    {
        $name = 'screw_propelled_vehicle.gif';
        $filePath = dirname(__DIR__).'/tests/resources/' . $name;

        $response = $this->mmClient->filePost($this->channel, $filePath, 'Test from package');

        $this->assertEquals($name, $response['metadata']['files'][0]['name']);
    }
}
