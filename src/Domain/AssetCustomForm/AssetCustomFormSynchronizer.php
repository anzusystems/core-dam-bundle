<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\AssetCustomForm;

use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\Configuration\ExtSystemConfigurationProvider;
use AnzuSystems\CoreDamBundle\Domain\CustomForm\CustomFormManager;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Model\Enum\AssetType;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use Doctrine\ORM\NonUniqueResultException;

final class AssetCustomFormSynchronizer
{
    use OutputUtilTrait;

    public function __construct(
        private readonly CustomFormManager $customFormManager,
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
        private readonly ExtSystemConfigurationProvider $extSystemConfigurationProvider,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function synchronizeForExtSystem(ExtSystem $extSystem): void
    {
        foreach (AssetType::cases() as $type) {
            $config = $this->extSystemConfigurationProvider->getAssetConfiguration(
                slug: $extSystem->getSlug(),
                assetType: $type
            );

            if ($config->isEnabled()) {
                $this->createIfNotExist($extSystem, $type);
            }
        }
    }

    /**
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    private function createIfNotExist(ExtSystem $extSystem, AssetType $assetType): AssetCustomForm
    {
        $customForm = $this->assetCustomFormRepository->findOneByTypeAndExtSystem($extSystem, $assetType);
        if ($customForm) {
            $this->outputUtil->writeln(
                sprintf(
                    'CustomForm already exists for ExtSystem (%s) and AssetType (%s)',
                    $extSystem->getSlug(),
                    $assetType->toString()
                )
            );

            return $customForm;
        }

        $this->outputUtil->info(
            sprintf(
                'Creating CustomForm for ExtSystem (%s) and AssetType (%s)',
                $extSystem->getSlug(),
                $assetType->toString()
            )
        );

        $form = (new AssetCustomForm())
            ->setAssetType($assetType)
            ->setExtSystem($extSystem);

        $this->customFormManager->create($form, false);

        return $form;
    }
}
