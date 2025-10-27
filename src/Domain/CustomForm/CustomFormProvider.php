<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\CustomForm;

use AnzuSystems\CommonBundle\Domain\AbstractManager;
use AnzuSystems\CoreDamBundle\Entity\AssetCustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomForm;
use AnzuSystems\CoreDamBundle\Entity\CustomFormElement;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\AssetCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Entity\Interfaces\ResourceCustomFormProvidableInterface;
use AnzuSystems\CoreDamBundle\Exception\ForbiddenOperationException;
use AnzuSystems\CoreDamBundle\Repository\AssetCustomFormRepository;
use AnzuSystems\CoreDamBundle\Repository\CustomFormElementRepository;
use AnzuSystems\CoreDamBundle\Repository\ResourceCustomFormRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\NonUniqueResultException;

final class CustomFormProvider extends AbstractManager
{
    public function __construct(
        private readonly AssetCustomFormRepository $assetCustomFormRepository,
        private readonly ResourceCustomFormRepository $resourceCustomFormRepository,
        private readonly CustomFormElementRepository $customFormElementRepository,
        private readonly CustomFormCache $customFormCache,
    ) {
    }

    /**
     * @return Collection<int, CustomFormElement>
     */
    public function provideAllSearchableElementsForExtSystem(string $slug): Collection
    {
        return $this->customFormElementRepository->findAllAssetSearchableElementsByForms(
            $this->assetCustomFormRepository->findAllByExtSystemSlug($slug)->map(
                fn (CustomForm $customForm): string => (string) $customForm->getId()
            )->getValues()
        );
    }

    /**
     * @return Collection<int, AssetCustomForm>
     */
    public function provideAllSearchableElementsForExtSystemId(int $extSystemId): Collection
    {
        return $this->assetCustomFormRepository->findAllByExtSystem($extSystemId);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function provideForm(ResourceCustomFormProvidableInterface|AssetCustomFormProvidableInterface $formProvidable): CustomForm
    {
        if ($formProvidable instanceof AssetCustomFormProvidableInterface) {
            return $this->provideFormByAssetProvidable($formProvidable);
        }

        return $this->provideFormByResourceProvidable($formProvidable);
    }

    /**
     * @throws NonUniqueResultException
     * @throws ForbiddenOperationException
     */
    public function provideFormByAssetProvidable(AssetCustomFormProvidableInterface $formProvidable): CustomForm
    {
        $form = $this->customFormCache->getFromCache($formProvidable);
        if ($form) {
            return $form;
        }

        $form = $this->assetCustomFormRepository->findOneByTypeAndExtSystem(
            $formProvidable->getExtSystem(),
            $formProvidable->getAssetType(),
        );

        if ($form) {
            return $this->customFormCache->saveToCache($formProvidable, $form);
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::CUSTOM_FORM_NOT_EXISTS);
    }

    /**
     * @throws NonUniqueResultException
     * @throws ForbiddenOperationException
     */
    public function provideFormByResourceProvidable(ResourceCustomFormProvidableInterface $formProvidable): CustomForm
    {
        $form = $this->customFormCache->getFromCache($formProvidable);
        if ($form) {
            return $form;
        }

        $form = $this->resourceCustomFormRepository->findByResource($formProvidable->getResourceKey());

        if ($form) {
            return $this->customFormCache->saveToCache($formProvidable, $form);
        }

        throw new ForbiddenOperationException(ForbiddenOperationException::CUSTOM_FORM_NOT_EXISTS);
    }

    /**
     * @return ReadableCollection<int, CustomFormElement>
     */
    public function provideFormSearchableElements(CustomForm $form): ReadableCollection
    {
        return $form->getElements()->filter(
            fn (CustomFormElement $element): bool => true === $element->getAttributes()->isSearchable()
        );
    }
}
