<?php
namespace Flynn314\Mattermost\Tests;

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

        $baseUrl = $_ENV['MM_BASE_URL'];
        $token = $_ENV['MM_TOKEN'];
        $this->channel = $_ENV['MM_CHANNEL'];

        $this->mmClient = new MattermostClient($baseUrl, $token, new Client());
    }

    public function testMessageSend(): void
    {
        $message = 'Simple test at ' . date('H:i:s');
        $response = $this->mmClient->messagePost($this->channel, $message);

        $this->assertEquals($message, $response['message']);
    }

    public function testMessageEdit(): void
    {
        $message = 'Simple test at ' . date('H:i:s');
        $response = $this->mmClient->messagePost($this->channel, $message);
        $messageId = $response['id'];

        sleep(5);
        $message = 'Simple test at ' . date('H:i:s');
        $this->mmClient->messageEdit($messageId, $message);

        sleep(5);
        $message = 'Simple test at ' . date('H:i:s');
        $response = $this->mmClient->messageEdit($messageId, $message);

        $this->assertEquals($message, $response['message']);
    }

    public function testFileUpload(): void
    {
        $name = 'screw_propelled_vehicle.gif';
        $filePath = dirname(__DIR__).'/tests/resources/' . $name;

        $response = $this->mmClient->filePost($this->channel, $filePath, 'Test from package');

        $this->assertEquals($name, $response['metadata']['files'][0]['name']);
    }

    public function testFileUploadBinary(): void
    {
        $name = 'screw_propelled_vehicle.gif';
        $filePath = dirname(__DIR__).'/tests/resources/' . $name;
        $fileData = file_get_contents($filePath);

        $response = $this->mmClient->filePostBinary($this->channel, $fileData, 'test_binary_file.gif');

        $this->assertEquals($name, $response['metadata']['files'][0]['name']);
    }

    public function testDelete(): void
    {
        $response = $this->mmClient->messagePost($this->channel, 'This message should be delete after 5sec');
        sleep(1);
        $this->mmClient->deletePost($response['id']);

        $name = 'screw_propelled_vehicle.gif';
        $filePath = dirname(__DIR__).'/tests/resources/' . $name;

        $response = $this->mmClient->filePost($this->channel, $filePath, 'Should be delete shortly, also');
        sleep(3);
        $this->mmClient->deletePost($response['id']);

        $this->assertEquals($name, $response['metadata']['files'][0]['name']);
    }

    public function testWebhookPost(): void
    {
        $response = $this->mmClient->postWebhook([
            'channel' => 'test',
            'text' => 'asd',
        ], $_ENV['MM_TOKEN_WEBHOOK']);

        $this->assertEquals('ok', $response['message']);

        $response = $this->mmClient->postWebhookWithFace('asdsd', 'https://mattermost.com/wp-content/uploads/2022/02/icon.png', [
            'channel' => 'test',
            'text' => 'asd',
        ], $_ENV['MM_TOKEN_WEBHOOK']);
        $this->assertEquals('ok', $response['message']);
    }
}
