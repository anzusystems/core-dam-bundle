<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use AnzuSystems\CoreDamBundle\Repository\CustomFormElementRepository;
use AnzuSystems\CoreDamBundle\Repository\ResourceCustomFormRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;

final class CustomFormProvider extends AbstractManager
{
    public function __construct(
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
        private readonly ResourceCustomFormRepository $resourceCustomFormRepository,
        private readonly CustomFormElementRepository $customFormElementRepository,
    ) {
    }

    public function provideAllSearchableElementsForExtSystem(string $slug): Collection
    {
        return $this->customFormElementRepository->findAllAssetSearchableElementsByForms(
            $this->assetCustomFormRepository->findAllByExtSystemSlug($slug)->map(
                fn (CustomForm $customForm): string => $customForm->getId()
            )->getValues()
        );
    }

    /**
     * @throws NonUniqueResultException
     * @throws ForbiddenOperationException
     */
    public function provideFormByAssetProvidable(AssetCustomFormProvidableInterface $formProvidable): CustomForm
    {
        $form = $this->assetCustomFormRepository->findOneByTypeAndExtSystem(
            $formProvidable->getExtSystem(),
            $formProvidable->getAssetType(),
        );

        if (null === $form) {
            throw new ForbiddenOperationException(ForbiddenOperationException::CUSTOM_FORM_NOT_EXISTS);
        }

        return $form;
    }

    /**
     * @throws NonUniqueResultException
     * @throws ForbiddenOperationException
     */
    public function provideFormByResourceProvidable(ResourceCustomFormProvidableInterface $formProvidable): CustomForm
    {
        $form = $this->resourceCustomFormRepository->findByResource($formProvidable->getResourceKey());

        if (null === $form) {
            throw new ForbiddenOperationException(ForbiddenOperationException::CUSTOM_FORM_NOT_EXISTS);
        }

        return $form;
    }

    /**
     * @return Collection<int, CustomFormElement>
     */
    public function provideFormSearchableElements(CustomForm $form): Collection
    {
        return $form->getElements()->filter(
            fn (CustomFormElement $element): bool => true === $element->getAttributes()->isSearchable()
        );
    }
}
