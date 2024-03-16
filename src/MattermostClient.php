<?php
namespace Flynn314\Mattermost;

use Flynn314\Mattermost\Entity\CustomStatus;
use Flynn314\Mattermost\Exception\MattermostClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;

readonly class MattermostClient
{
    public function __construct(
        private string $baseUrl,
        private string $token,
        private ClientInterface $httpClient
    ) {}

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
    public function messagePost(string $channelId, string $message, ?string $rootId = null, array $data = []): array
    {
        $data['channel_id'] = $channelId;
        $data['message'] = $message;
        if ($rootId) {
            $data['root_id'] = $rootId;
        }

        return $this->dataPost($data);
    }

    public function messagePostWithFace(string $channelId, string $message, ?string $rootId = null, ?string $overrideUsername = null, ?string $overrideAvatar = null): array
    {
        $data = [
            'channel_id' => $channelId,
            'message' => $message,
        ];
        if ($rootId) {
            $data['root_id'] = $rootId;
        }
        if ($overrideAvatar || $overrideUsername) {
            $data['props'] = [
                'from_bot' => "true",
                'from_webhook' => "true",
            ];
            if ($overrideAvatar) {
                $data['props']['override_icon_url'] = $overrideAvatar;
            }
            if ($overrideUsername) {
                $data['props']['override_username'] = $overrideUsername;
            }
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
     * @throws MattermostClientException
     */
    public function filePostBinary(string $channelId, string $fileData, string $filename, ?string $caption = null, ?string $rootId = null, ?string $overrideUsername = null, ?string $overrideAvatar = null): array
    {
        return $this->filePostGalleryWithFace($channelId, [$filename => $fileData], $caption, $rootId, $overrideUsername, $overrideAvatar);
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
     * @throws MattermostClientException
     */
    public function filePostGalleryWithFace(string $channelId, array $files, ?string $caption = null, ?string $rootId = null, ?string $overrideUsername = null, ?string $overrideAvatar = null): array
    {
        $filesIds = [];
        foreach ($files as $filename => $fileData) {
            $file = $this->fileUploadBinary($channelId, $fileData, $filename);
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

        if ($overrideAvatar || $overrideUsername) {
            $data['props'] = [
                'from_bot' => "true",
                'from_webhook' => "true",
            ];
            if ($overrideAvatar) {
                $data['props']['override_icon_url'] = $overrideAvatar;
            }
            if ($overrideUsername) {
                $data['props']['override_username'] = $overrideUsername;
            }
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
     * @throws MattermostClientException
     */
    public function fileUploadBinary(string $channelId, string $fileData, string $filename, array $data = []): array
    {
        $data['binary'] = $fileData;

        $response = $this->request('post', 'api/v4/files?channel_id='.$channelId.'&filename='.$filename, $data, [
            'enctype' => 'multipart/form-data'
        ]);

        return $response['file_infos'][0] ?? [];
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

    public function postWebhook(array $data, string $webhookKey): array
    {
        return $this->request('post', 'hooks/' . $webhookKey, $data);
    }

    public function postWebhookWithFace(string $username, string $photo, array $data, string $webhookKey): array
    {
        $data['username'] = $username;
        $data['icon_url'] = $photo;

        return $this->postWebhook($data, $webhookKey);
    }

    public function getCustomStatus(string $userId): ?CustomStatus
    {
        $user = $this->getUser($userId);
        $cs = json_decode($user['props']['customStatus'] ?? '[]', true);
        if (!$cs) {
            return null;
        }

        return new CustomStatus($cs['emoji'], $cs['text'], new \DateTimeImmutable($cs['expires_at']));
    }

    public function setCustomStatus(string $userId, string $emoji, string $text, ?\DateTimeInterface $expiration = null): array
    {
        $data = [];
        $data['emoji'] = $emoji;
        $data['text'] = $text;
        // todo
        // https://api.mattermost.com/#tag/status/operation/UpdateUserCustomStatus
        // $data['duration'] = 'thirty_minutes'; // thirty_minutes, one_hour, four_hours, today, this_week or date_and_time
        if ($expiration instanceof \DateTimeInterface) {
            $data['expires_at'] = $expiration->format(\DateTimeInterface::ATOM); // '2030-10-31T01:30:00.000-05:00'
        }

        return $this->request(
            method: 'put',
            uri: sprintf('api/v4/users/%s/status/custom', $userId),
            data: $data
        );
    }

    public function unsetCustomStatus(string $userId): array
    {
        return $this->request(
            method: 'delete',
            uri: sprintf('api/v4/users/%s/status/custom', $userId),
        );
    }

    public function setChannelHeader(string $channelId, string $text): array
    {
        return $this->request(
            method: 'put',
            uri: sprintf('api/v4/channels/%s/patch', $channelId),
            data: ['header' => $text],
        );
    }

    public function getUser(string $userId): array
    {
        $users = $this->getUsers([$userId]);

        return end($users);
    }

    public function getUsers(
        array $userIds,
        // todo ?int $timestamp = null
    ): array
    {
        // $timestamp *= 1000;

        return $this->request(
            method: 'post',
            uri: 'api/v4/users/ids',
            data: $userIds,
        );
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
            if ('ok' === $content) {
                return [
                    'message' => $content,
                ];
            }

            return json_decode($content, true);
        } catch (GuzzleException $e) {
            throw new MattermostClientException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}
