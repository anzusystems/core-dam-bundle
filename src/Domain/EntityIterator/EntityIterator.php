<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Domain\EntityIterator;

use AnzuSystems\CommonBundle\Repository\AnzuRepositoryInterface;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Command\Traits\OutputUtilTrait;
use AnzuSystems\CoreDamBundle\Domain\EntityIterator\Model\EntityIteratorConfig;
use AnzuSystems\CoreDamBundle\Domain\EntityIterator\Visitor\EntityIteratorOnBatchVisitorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class EntityIterator
{
    use OutputUtilTrait;

    /**
     * @var ServiceLocator<EntityIteratorOnBatchVisitorInterface>
     */
    private ServiceLocator $visitorLocator;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[AutowireLocator(EntityIteratorOnBatchVisitorInterface::class)]
        ServiceLocator $visitorLocator,
    ) {
        $this->visitorLocator = $visitorLocator;
    }

    /**
     * @template T of BaseIdentifiableInterface
     * @param class-string<T> $class
     *
     * @return Generator<int, T>
     */
    public function iterateEntities(
        string $class,
        EntityIteratorConfig $config,
    ): Generator {
        /** @var AnzuRepositoryInterface $repository */
        $repository = $this->entityManager->getRepository($class);
        $onBatchVisitor = $this->getOnBatchVisitor($config);

        $lastId = (string) $config->getFromId();
        $progressBar = new ProgressBar(
            $this->outputUtil->getOutput(),
            $config->isFetchTotalCount()
                ? $repository->getAllCount($lastId, (string) $config->getToId())
                : App::ZERO
        );
        $progressBar->start();

        do {
            $entities = $repository->getAll($lastId, (string) $config->getToId(), $config->getBatch());

            foreach ($entities as $entity) {
                $lastId = $entity->getId();
                yield $entity;
                $progressBar->advance();
            }

            $onBatchVisitor?->onBatch($this->entityManager);
        } while ($config->getBatch() === $entities->count());

        $progressBar->finish();
        $this->writeln('');
    }

    private function getOnBatchVisitor(EntityIteratorConfig $config): ?EntityIteratorOnBatchVisitorInterface
    {
        if (null === $config->getUseOnBatchVisitor()) {
            return null;
        }

        try {
            return $this->visitorLocator->get($config->getUseOnBatchVisitor());
        } catch (ContainerExceptionInterface) {
            throw new RuntimeException(sprintf(
                'Invalid (%s), provided (%s)',
                EntityIteratorOnBatchVisitorInterface::class,
                $config->getUseOnBatchVisitor()
            ));
        }
    }
}
