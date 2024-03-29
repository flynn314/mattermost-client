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
    private string $userId;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $baseUrl = $_ENV['MM_BASE_URL'];
        $token = $_ENV['MM_TOKEN'];
        $this->channel = $_ENV['MM_CHANNEL'];
        $this->userId = $_ENV['MM_USER_ID'];

        $this->mmClient = new MattermostClient($baseUrl, $token, new Client());
    }

    /**
     * vendor/bin/phpunit --filter=testCustomStatus
     */
    public function testCustomStatus(): void
    {
        $plusTwoDaysDate = new \DateTime('+2 days');
        $response = $this->mmClient->setCustomStatus($this->userId, 'doomgod', 'Testing', $plusTwoDaysDate);
        $this->assertEquals('OK', $response['status']);

        sleep(2);
        $customStatus = $this->mmClient->getCustomStatus($this->userId);
        $this->assertEquals('doomgod', $customStatus->getEmoji());
        $this->assertEquals('Testing', $customStatus->getText());
        $this->assertEquals($plusTwoDaysDate->getTimestamp(), $customStatus->getExpirationDate()->getTimestamp());
        sleep(1);

        $response = $this->mmClient->unsetCustomStatus($this->userId);
        $this->assertEquals('OK', $response['status']);
    }

    /**
     * vendor/bin/phpunit --filter=testChannelHeaderUpdate
     */
    public function testChannelHeaderUpdate(): void
    {
        $text = 'Header update 1';
        $response = $this->mmClient->setChannelHeader($this->channel, $text);
        $this->assertEquals($text, $response['header']);
        sleep(2);
        $text = 'Header update 2';
        $response = $this->mmClient->setChannelHeader($this->channel, $text);
        $this->assertEquals($text, $response['header']);
        sleep(2);
        $text = '';
        $response = $this->mmClient->setChannelHeader($this->channel, $text);
        $this->assertEquals($text, $response['header']);
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

    /**
     * vendor/bin/phpunit --filter=testTypingIndicator
     */
    public function testTypingIndicator(): void
    {
        $response = $this->mmClient->typingIndicatorStart($this->userId, $this->channel);
        $this->assertEquals('OK', $response['status']);
    }
}
