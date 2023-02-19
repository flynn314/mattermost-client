<?php
declare(strict_types=1);

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

    public function messageGet(string $messageId): array
    {
        return $this->request('get', 'api/v4/posts/'.$messageId);
    }

    public function messagePost(string $channelId, string $message): array
    {
        $data = [
            'channel_id' => $channelId,
            'message' => $message,
        ];

        return $this->request('post', 'api/v4/posts', $data);
    }

    public function messageEdit(string $messageId, string $message): array
    {
        $data = [
            'message' => $message,
        ];

        return $this->request('put', sprintf('api/v4/posts/%s/patch', $messageId), $data);
    }

    public function filePost(string $channelId, string $file, ?string $caption = null): array
    {
        return $this->filePostGallery($channelId, [$file], $caption);
    }

    /**
     * @throws MattermostClientException
     */
    public function filePostGallery(string $channelId, array $files, ?string $caption = null): array
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
        if ($caption) {
            $data['message'] = $caption;
        }

        return $this->request('post', 'api/v4/posts', $data);
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
            // 'channel_id' => $channelId,
            'binary' => $fileData,
            // 'client_ids' => [],
        ];

        $data =  $this->request('post', 'api/v4/files?channel_id='.$channelId.'&filename='.$filename, $data, [
            'enctype' => 'multipart/form-data'
        ]);
        $data = $data['file_infos'][0] ?? [];

        return $data;
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
            $response = $this->httpClient->request($method, $uri, $options);
            $content = $response->getBody()->getContents();

            return json_decode($content, true);
        } catch (GuzzleException $e) {
            throw new MattermostClientException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}
