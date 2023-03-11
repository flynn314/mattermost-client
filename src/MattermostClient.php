<?php
namespace Flynn314\Mattermost;

use Flynn314\Mattermost\Exception\MattermostClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;

class MattermostClient
{
    private ClientInterface $httpClient;
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token, ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    /**
     * @throws MattermostClientException
     */
    public function messageGet(string $messageId): array
    {
        return $this->request('get', 'api/v4/posts'.$messageId);
    }

    /**
     * @throws MattermostClientException
     */
    public function messagePost(string $channelId, string $message, ?string $rootId = null): array
    {
        $data = [
            'channel_id' => $channelId,
            'message' => $message,
        ];
        if ($rootId) {
            $data['root_id'] = $rootId;
        }

        return $this->dataPost($data);
    }

    /**
     * This method will work only with Laravel
     * @throws MattermostClientException
     */
    public function messagePostToGeneral(string $message, ?string $rootId = null): array
    {
        return $this->messagePost(config('mattermost.channel.general'), $message, $rootId);
    }

    /**
     * @throws MattermostClientException
     */
    public function messageEdit(string $messageId, string $message): array
    {
        $data = [
            'message' => $message,
        ];

        return $this->request('put', sprintf('api/v4/posts/%s/patch', $messageId), $data);
    }

    /**
     * @throws MattermostClientException
     */
    public function filePost(string $channelId, string $file, ?string $caption = null, ?string $rootId = null): array
    {
        return $this->filePostGallery($channelId, [$file], $caption, $rootId);
    }

    /**
     * This method requires Laravel config
     * @throws MattermostClientException
     */
    public function filePostToGeneral(string $file, ?string $caption = null, ?string $rootId = null): array
    {
        return $this->filePost(config('mattermost.channel.general'), $file, $caption, $rootId);
    }

    /**
     * @throws MattermostClientException
     */
    public function filePostGallery(string $channelId, array $files, ?string $caption = null, ?string $rootId = null): array
    {
        $filesIds = [];
        foreach ($files as $file) {
            $file = $this->fileUpload($channelId, $file);
            $filesIds[] = $file['id'];
            if (count($filesIds) >= 10) {
                break;
            }
        }

        $data = [
            'channel_id' => $channelId,
            'file_ids' => $filesIds,
        ];
        if ($rootId) {
            $data['root_id'] = $rootId;
        }
        if ($caption) {
            $data['message'] = $caption;
        }

        return $this->dataPost($data);
    }

    /**
     * This method requires Laravel config
     * @throws MattermostClientException
     */
    public function filePostGalleryToGeneral(string $channelId, array $files, ?string $caption = null, ?string $rootId = null): array
    {
        return $this->filePostGallery(config('mattermost.channel.general'), $files, $caption, $rootId);
    }

    /**
     * @throws MattermostClientException
     */
    public function fileUpload(string $channelId, string $file): array
    {
        //if (strstr($file, 'https://') || strstr($file, 'http://')) {
            $filename = basename($file);
            $fileData = file_get_contents($file);
        //}

        $data = [
            'binary' => $fileData,
        ];
        // $data['channel_id'] = $channelId;
        // $data['client_ids'] = [];

        $data =  $this->request('post', 'api/v4/files?channel_id='.$channelId.'&filename='.$filename, $data, [
            'enctype' => 'multipart/form-data'
        ]);

        return $data['file_infos'][0] ?? [];
    }

    /**
     * This method requires Laravel config
     * @throws MattermostClientException
     */
    public function fileUploadToGeneral(string $file): array
    {
        return $this->fileUpload(config('mattermost.channel.general'), $file);
    }

    /**
     * @throws MattermostClientException
     */
    public function deletePost(string $messageId): array
    {
        return $this->request('delete', 'api/v4/posts/' . $messageId);
    }

    /**
     * @throws MattermostClientException
     */
    public function dataPost(array $data): array
    {
        return $this->request('post', 'api/v4/posts', $data);
    }


    /**
     * @throws MattermostClientException
     */
    private function request(string $method, string $uri, array $data = [], array $header = []): array
    {
        $uri = sprintf('%s/%s', $this->baseUrl, $uri);

        $header['Authorization'] = 'Bearer ' . $this->token;
        if (!isset($header['Content-Type'])) {
            $header['Content-Type'] = 'application/json';
        }
        $options = [
            'headers' => $header,
        ];

        if (isset($data['binary'])) {
            $options['body'] = $data['binary'];
            unset($data['binary']);
        } else {
            $options['json'] = $data;
        }

        try {
            // todo PSR-7
            $response = $this->httpClient->request($method, $uri, $options);
            $content = $response->getBody()->getContents();

            return json_decode($content, true);
        } catch (GuzzleException $e) {
            throw new MattermostClientException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}
