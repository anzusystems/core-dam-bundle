<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\FileSystem;

use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use DateTime;
use Google\Cloud\Storage\Bucket;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\PathPrefixer;

class GCloudFilesystem extends AbstractFilesystem
{
    private readonly PathPrefixer $prefixer;

    public function __construct(
        GoogleCloudStorageAdapter $adapter,
        string $prefix,
        private readonly Bucket $bucket,
    ) {
        $this->prefixer = new PathPrefixer($prefix);
        parent::__construct($adapter);
    }

    public function getDownloadLink(AssetFile $assetFile, DateTime $dateTime): string
    {
        $object = $this->bucket->object(
            $this->prefixer->prefixPath($assetFile->getFilePath())
        );

        return $object->signedUrl(
            $dateTime,
            [
                'version' => 'v4',
                'responseDisposition' => 'attachment',
            ]
        );
    }
}
