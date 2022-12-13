<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Request\ParamConverter;

use AnzuSystems\CoreDamBundle\Entity\RegionOfInterest;
use AnzuSystems\CoreDamBundle\Model\Dto\Image\Crop\RequestedCropDto;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class ImageCropConverter implements ParamConverterInterface
{
    public const WIDTH_ATTR_NAME = 'requestWidth';
    public const HEIGHT_ATTR_NAME = 'requestHeight';
    public const QUALITY_ATTR_NAME = 'quality';
    public const ROI_ATTR_NAME = 'regionOfInterestId';

    public const WIDTH_ARG_SYMBOL = 'w';
    public const HEIGHT_ARG_SYMBOL = '-h';
    public const ROI_ARG_SYMBOL = '-c';
    public const QUALITY_ARG_SYMBOL = '-q';

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $cropPayload = new RequestedCropDto();
        $cropPayload
            ->setRoi($this->getRoi($request))
            ->setRequestWidth($this->getWidth($request))
            ->setRequestHeight($this->getHeight($request))
            ->setQuality($this->getQuality($request))
        ;

        $name = $configuration->getName();
        $request->attributes->set($name, $cropPayload);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return (bool) $configuration->getClass();
    }

    private function getWidth(Request $request): int
    {
        return (int) $this->getValue($request, self::WIDTH_ATTR_NAME, self::WIDTH_ARG_SYMBOL);
    }

    private function getHeight(Request $request): int
    {
        return (int) $this->getValue($request, self::HEIGHT_ATTR_NAME, self::HEIGHT_ARG_SYMBOL);
    }

    private function getQuality(Request $request): ?int
    {
        return $this->getValue($request, self::QUALITY_ATTR_NAME, self::QUALITY_ARG_SYMBOL);
    }

    private function getRoi(Request $request): int
    {
        return $this->getValue($request, self::ROI_ATTR_NAME, self::ROI_ARG_SYMBOL)
            ?? RegionOfInterest::FIRST_ROI_POSITION;
    }

    private function getValue(Request $request, string $attrName, string $valuePrefix): ?int
    {
        $value = $request->attributes->get($attrName);
        if (false === empty($value)) {
            return (int) ltrim($value, $valuePrefix);
        }

        return null;
    }
}
