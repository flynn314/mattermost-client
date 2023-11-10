<?php
declare(strict_types=1);

namespace Flynn314\Mattermost\Entity;

readonly class CustomStatus
{
    public function __construct(
        private string $emoji,
        private string $text,
        private ?\DateTimeInterface $expiresAt,
    ) {}

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getExpirationDate(): ?\DateTimeImmutable
    {
        if (!$this->expiresAt) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($this->expiresAt);
    }
}
