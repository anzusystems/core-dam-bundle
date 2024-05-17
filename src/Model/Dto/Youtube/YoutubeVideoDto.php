<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Model\Dto\Youtube;

final class YoutubeVideoDto
{
    public const string UPLOAD_STATUS_PROCESSED = 'processed';

    private string $id;
    private string $uploadStatus;
    private int $thumbnailWidth;
    private int $thumbnailHeight;
    private string $thumbnailUrl;

    public function __construct()
    {
        $this->setId('');
        $this->setUploadStatus('');
        $this->setThumbnailWidth(0);
        $this->setThumbnailHeight(0);
        $this->setThumbnailUrl('');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUploadStatus(): string
    {
        return $this->uploadStatus;
    }

    public function setUploadStatus(string $uploadStatus): self
    {
        $this->uploadStatus = $uploadStatus;

        return $this;
    }

    public function getThumbnailWidth(): int
    {
        return $this->thumbnailWidth;
    }

    public function setThumbnailWidth(int $thumbnailWidth): self
    {
        $this->thumbnailWidth = $thumbnailWidth;

        return $this;
    }

    public function getThumbnailHeight(): int
    {
        return $this->thumbnailHeight;
    }

    public function setThumbnailHeight(int $thumbnailHeight): self
    {
        $this->thumbnailHeight = $thumbnailHeight;

        return $this;
    }

    public function getThumbnailUrl(): string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(string $thumbnailUrl): self
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }
}
