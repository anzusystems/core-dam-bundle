<?php

declare(strict_types=1);


namespace AnzuSystems\CoreDamBundle\Tests\Helpers;

use AnzuSystems\CoreDamBundle\DataFixtures\ImageFixtures;
use AnzuSystems\CoreDamBundle\Helper\FileHelper;
use AnzuSystems\CoreDamBundle\Model\Dto\AbstractEntityDto;
use AnzuSystems\CoreDamBundle\Tests\ApiClient;
use AnzuSystems\CoreDamBundle\Tests\Data\Model\AssetUrl\AssetUrlInterface;
use AnzuSystems\SerializerBundle\Serializer;
use Exception;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class AssetUploader
{
    public function __construct(
        private readonly Serializer $serializer
    ) {
    }




}
