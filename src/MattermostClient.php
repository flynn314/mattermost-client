<?php
declare(strict_types=1);

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
    public function getMessage(string $messageId): array
    {
        return $this->request('get', 'api/v4/posts'.$messageId);
    }

    /**
     * @throws MattermostClientException
     */
    public function postMessage(string $channelId, string $message, string|null $rootId = null, array $data = []): array
    {
        $data['channel_id'] = $channelId;
        $data['message'] = $message;
        if ($rootId) {
            $data['root_id'] = $rootId;
        }

        return $this->post($data);
    }

    /**
     * @throws MattermostClientException
     */
    public function postMessageWithFace(string $channelId, string $message, string|null $rootId = null, string|null $overrideUsername = null, string|null $overrideAvatar = null): array
    {
        $data = [];
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

        return $this->postMessage($channelId, $message, $rootId, $data);
    }

    /**
     * @throws MattermostClientException
     */
    public function postEphemeral(string $channelId, string $message, string|null $rootId = null, array $data = []): array
    {
        $data['channel_id'] = $channelId;
        $data['message'] = $message;
        if (null !== $rootId) {
            $data['root_id'] = $rootId;
        }

        return $this->request('post', 'api/v4/posts/ephemeral', $data);
    }

    /**
     * @throws MattermostClientException
     */
    public function updateMessage(string $messageId, array $data = []): array
    {
        return $this->request('put', sprintf('api/v4/posts/%s/patch', $messageId), $data);
    }

    /**
     * @throws MattermostClientException
     */
    public function updateMessageText(string $messageId, string $text, array $data = []): array
    {
        $data['message'] = $text;

        return $this->updateMessage($messageId, $data);
    }

    /**
     * @throws MattermostClientException
     */
    public function postFile(string $channelId, string $file, string|null $caption = null, string|null $rootId = null): array
    {
        return $this->postGallery($channelId, [$file], $caption, $rootId);
    }

    /**
     * @throws MattermostClientException
     */
    public function postBinary(string $channelId, string $fileData, string $filename, string|null $caption = null, string|null $rootId = null, string|null $overrideUsername = null, string|null $overrideAvatar = null): array
    {
        return $this->postGalleryWithFace($channelId, [$filename => $fileData], $caption, $rootId, $overrideUsername, $overrideAvatar);
    }

    /**
     * @throws MattermostClientException
     */
    public function postGallery(string $channelId, array $files, string|null $caption = null, string|null $rootId = null): array
    {
        $filesIds = [];
        foreach ($files as $file) {
            $file = $this->uploadFile($channelId, $file);
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

        return $this->post($data);
    }

    /**
     * @throws MattermostClientException
     */
    public function postGalleryWithFace(string $channelId, array $files, string|null $caption = null, string|null $rootId = null, string|null $overrideUsername = null, string|null $overrideAvatar = null): array
    {
        $filesIds = [];
        foreach ($files as $filename => $fileData) {
            $file = $this->uploadBinary($channelId, $fileData, $filename);
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

        return $this->post($data);
    }

    /**
     * @throws MattermostClientException
     */
    public function uploadFile(string $channelId, string $file): array
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
    public function uploadBinary(string $channelId, string $fileData, string $filename, array $data = []): array
    {
        $data['binary'] = $fileData;

        $response = $this->request('post', 'api/v4/files?channel_id='.$channelId.'&filename='.$filename, $data, [
            'enctype' => 'multipart/form-data'
        ]);

        return $response['file_infos'][0] ?? [];
    }

    /**
     * @throws MattermostClientException
     */
    public function setReaction(string $postId, string $userId, string $emojiName): array
    {
        return $this->request('post', 'api/v4/reactions', [
            'post_id' => $postId,
            'user_id' => $userId,
            'emoji_name' => $emojiName,
            // 'create_at' => 0,
        ]);
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
    public function post(array $data): array
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

    public function getCustomStatus(string $userId): CustomStatus|null
    {
        $user = $this->getUser($userId);
        $cs = json_decode($user['props']['customStatus'] ?? '[]', true);
        if (!$cs) {
            return null;
        }

        return new CustomStatus($cs['emoji'], $cs['text'], new \DateTimeImmutable($cs['expires_at']));
    }

    public function setCustomStatus(string $userId, string $emoji, string $text, \DateTimeInterface|null $expiration = null): array
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

    /**
     * @throws MattermostClientException
     */
    public function unsetCustomStatus(string $userId): array
    {
        return $this->request(
            method: 'delete',
            uri: sprintf('api/v4/users/%s/status/custom', $userId),
        );
    }

    /**
     * @throws MattermostClientException
     */
    public function updateChannel(string $channelId, array $data): array
    {
        return $this->request(
            method: 'put',
            uri: sprintf('api/v4/channels/%s/patch', $channelId),
            data: $data,
        );
    }

    /**
     * @throws MattermostClientException
     */
    public function setChannelHeader(string $channelId, string $text): array
    {
        return $this->updateChannel($channelId, [
            'header' => $text,
        ]);
    }

    /**
     * @throws MattermostClientException
     */
    public function setChannelPurpose(string $channelId, string $text): array
    {
        return $this->updateChannel($channelId, [
            'purpose' => $text,
        ]);
    }

    /**
     * @throws MattermostClientException
     */
    public function setUserPreference(string $userId, string $categoryName, string $name, string|array|null $value): array
    {
        return $this->request(
            method: 'put',
            uri: sprintf('api/v4/users/%s/preferences', $userId),
            data: [
                [
                    'user_id' => $userId,
                    'category' => $categoryName,
                    'name' => $name,
                    'value' => is_array($value) ? json_encode($value) : $value,
                ],
            ],
        );
    }

    /**
     * @throws MattermostClientException
     */
    public function setUserTheme(string $userId, array $value): array
    {
        return $this->setUserPreference(userId: $userId, categoryName: 'theme', name: '', value: $value);
    }

    /**
     * @throws MattermostClientException
     */
    public function getUser(string $userId): array
    {
        $users = $this->getUsers([$userId]);

        return (array) end($users);
    }

    /**
     * @throws MattermostClientException
     */
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
    public function typingIndicatorStart(string $userId, string $channelId, string|null $rootId = null): array
    {
        $data = ['channel_id' => $channelId];
        if ($rootId) {
            $data['parent_id'] = $rootId;
        }

        return $this->request(
            method: 'post',
            uri: sprintf('api/v4/users/%s/typing', $userId),
            data: $data,
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
