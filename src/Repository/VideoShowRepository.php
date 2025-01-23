<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Repository;

use AnzuSystems\CoreDamBundle\Entity\VideoShow;
use Doctrine\Common\Collections\Order;

/**
 * @extends AbstractAnzuRepository<VideoShow>
 *
 * @method VideoShow|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoShow|null findOneBy(array $criteria, array $orderBy = null)
 */
class VideoShowRepository extends AbstractAnzuRepository
{
    public function findOneLastMobile(): ?VideoShow
    {
        return $this->findOneBy(
            [],
            [
                'attributes.mobileOrderPosition' => Order::Descending->value,
            ]
        );
    }

    public function findOneLastWeb(): ?VideoShow
    {
        return $this->findOneBy(
            [],
            [
                'attributes.webOrderPosition' => Order::Descending->value,
            ]
        );
    }

    protected function getEntityClass(): string
    {
        return VideoShow::class;
    }
}
