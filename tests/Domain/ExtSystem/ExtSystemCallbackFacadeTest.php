<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests\Domain\ExtSystem;

use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackFacade;
use AnzuSystems\CoreDamBundle\Domain\ExtSystem\ExtSystemCallbackInterface;
use AnzuSystems\CoreDamBundle\Entity\AssetLicence;
use AnzuSystems\CoreDamBundle\Entity\ExtSystem;
use AnzuSystems\CoreDamBundle\Entity\ImageFile;
use AnzuSystems\CoreDamBundle\Logger\DamLogger;
use AnzuSystems\CoreDamBundle\Model\Domain\ExtSystem\ImageFileUsage;
use AnzuSystems\CoreDamBundle\Repository\ExtSystemRepository;
use AnzuSystems\CoreDamBundle\Tests\CoreDamKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * DamLogger and ExtSystemRepository are final/DB-backed services, so this boots
 * a kernel to obtain real instances rather than mocking them; the ServiceLocator
 * under test is still built by hand with test-double callbacks, no fixtures needed.
 */
final class ExtSystemCallbackFacadeTest extends CoreDamKernelTestCase
{
    private const string SLUG = 'test';

    /**
     * @param list<string> $imageIds
     */
    #[DataProvider('bulkUnansweredDataProvider')]
    public function testResolveImageFileUsageAnswersNothingWhenTheCallbackCannot(bool $callbackThrows, array $imageIds): void
    {
        $facade = $callbackThrows
            ? $this->createFacade($this->createLocator($this->createThrowingCallback('resolveImageFileUsage')))
            : $this->createFacade(new ServiceLocator([]));
        $images = array_map(fn (string $id) => $this->createImageFile($id), $imageIds);

        self::assertSame([], $facade->resolveImageFileUsage($images));
    }

    public static function bulkUnansweredDataProvider(): array
    {
        return [
            'callback_missing' => ['callbackThrows' => false, 'imageIds' => ['image-1', 'image-2']],
            'callback_throws' => ['callbackThrows' => true, 'imageIds' => ['image-1']],
        ];
    }

    public function testResolveImageFileUsageCallsCallbackExactlyOnceForTheWholeBatch(): void
    {
        $callback = $this->createMock(ExtSystemCallbackInterface::class);
        $callback->expects(self::once())
            ->method('resolveImageFileUsage')
            ->willReturn([
                'image-1' => new ImageFileUsage(true),
                'image-2' => new ImageFileUsage(false),
                'image-3' => new ImageFileUsage(false),
            ])
        ;
        $facade = $this->createFacade($this->createLocator($callback));
        $images = [
            $this->createImageFile('image-1'),
            $this->createImageFile('image-2'),
            $this->createImageFile('image-3'),
        ];

        $result = $facade->resolveImageFileUsage($images);

        self::assertSame(
            ['image-1' => true, 'image-2' => false, 'image-3' => false],
            array_map(static fn (ImageFileUsage $usage): bool => $usage->isUsed(), $result),
        );
    }

    public function testResolveImageFileUsageLeavesOutAnImageTheCallbackDidNotAnswerFor(): void
    {
        $callback = self::createStub(ExtSystemCallbackInterface::class);
        $callback->method('resolveImageFileUsage')->willReturn(['image-1' => new ImageFileUsage(false)]);
        $facade = $this->createFacade($this->createLocator($callback));
        $images = [
            $this->createImageFile('image-1'),
            $this->createImageFile('image-unknown-to-callback'),
        ];

        $result = $facade->resolveImageFileUsage($images);

        self::assertSame(
            ['image-1' => false],
            array_map(static fn (ImageFileUsage $usage): bool => $usage->isUsed(), $result),
        );
    }

    #[DataProvider('failClosedDataProvider')]
    public function testIsImageFileUsedFailsClosedOnCallbackFailure(bool $callbackThrows): void
    {
        $facade = $callbackThrows
            ? $this->createFacade($this->createLocator($this->createThrowingCallback('isImageFileUsed')))
            : $this->createFacade(new ServiceLocator([]));

        self::assertTrue($facade->isImageFileUsed($this->createImageFile('image-1')));
    }

    public static function failClosedDataProvider(): array
    {
        return [
            'callback_missing' => ['callbackThrows' => false],
            'callback_throws' => ['callbackThrows' => true],
        ];
    }

    private function createFacade(ServiceLocator $locator): ExtSystemCallbackFacade
    {
        return new ExtSystemCallbackFacade(
            $locator,
            $this->getService(DamLogger::class),
            $this->getService(ExtSystemRepository::class),
        );
    }

    private function createLocator(ExtSystemCallbackInterface $callback): ServiceLocator
    {
        return new ServiceLocator([
            self::SLUG => static fn (): ExtSystemCallbackInterface => $callback,
        ]);
    }

    private function createThrowingCallback(string $method): ExtSystemCallbackInterface
    {
        $callback = self::createStub(ExtSystemCallbackInterface::class);
        $callback->method($method)->willThrowException(new RuntimeException('CMS unreachable'));

        return $callback;
    }

    private function createImageFile(string $id): ImageFile
    {
        $extSystem = (new ExtSystem())->setSlug(self::SLUG);
        $licence = (new AssetLicence())->setExtSystem($extSystem);

        $imageFile = new ImageFile();
        $imageFile->setId($id);
        $imageFile->setLicence($licence);

        return $imageFile;
    }
}
