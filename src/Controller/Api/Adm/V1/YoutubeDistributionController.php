<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller\Api\Adm\V1;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Model\OpenApi\Parameter\OAParameterPath;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponse;
use AnzuSystems\CommonBundle\Model\OpenApi\Response\OAResponseValidation;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use AnzuSystems\CoreDamBundle\App;
use AnzuSystems\CoreDamBundle\Controller\Api\AbstractApiController;
use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Domain\Distribution\DistributionFacade;
use AnzuSystems\CoreDamBundle\Domain\YoutubeDistribution\YoutubeDistributionFacade;
use AnzuSystems\CoreDamBundle\Entity\AssetFile;
use AnzuSystems\CoreDamBundle\Entity\YoutubeDistribution;
use AnzuSystems\CoreDamBundle\Exception\DomainException;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\AuthorizeUrlAdmGetDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\PlaylistDto;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeLanguageDto;
use AnzuSystems\CoreDamBundle\Model\OpenApi\Request\OARequest;
use AnzuSystems\CoreDamBundle\Security\Permission\DamPermissions;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Doctrine\ORM\NonUniqueResultException;
use Google\Exception;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/youtube-distribution', name: 'adm_youtube_distribution_v1_')]
#[OA\Tag('YoutubeDistribution')]
final class YoutubeDistributionController extends AbstractApiController
{
    public function __construct(
        private readonly YoutubeDistributionFacade $youtubeDistributionFacade,
        private readonly DistributionFacade $distributionFacade,
        private readonly YoutubeAuthenticator $youtubeAuthenticator,
    ) {
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    #[Route('/{distributionService}/auth-url', name: 'get_auth_url', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionService'), OAResponse(AuthorizeUrlAdmGetDto::class)]
    public function getAuthUrl(string $distributionService): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        try {
            return $this->okResponse(
                AuthorizeUrlAdmGetDto::getInstance($this->youtubeAuthenticator->generateAuthUrl($distributionService))
            );
        } catch (DomainException) {
            throw new NotFoundHttpException(sprintf('YT Distribution service (%s) not found', $distributionService));
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/asset-file/{assetFile}/prepare-payload/{distributionService}', name: 'prepare_payload', methods: [Request::METHOD_GET])]
    #[OAParameterPath('assetFile'), OAParameterPath('distributionService'), OAResponse(YoutubeDistribution::class)]
    public function preparePayload(AssetFile $assetFile, string $distributionService): JsonResponse
    {
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetFile);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->youtubeDistributionFacade->preparePayload($assetFile, $distributionService)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    #[Route('/{distributionService}/playlist/{force<0|1>}', name: 'get_playlist', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionService'), OAParameterPath('force'), OAResponse([PlaylistDto::class])]
    public function getPlaylist(string $distributionService, bool $force = false): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->youtubeDistributionFacade->getPlaylists($distributionService, $force)
        );
    }

    /**
     * @throws AppReadOnlyModeException
     * @throws InvalidArgumentException
     */
    #[Route('/{distributionService}/language', name: 'get_language', methods: [Request::METHOD_GET])]
    #[OAParameterPath('distributionService'), OAResponse([YoutubeLanguageDto::class])]
    public function getLanguage(string $distributionService): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $distributionService);

        return $this->okResponse(
            $this->youtubeDistributionFacade->getLanguage($distributionService)
        );
    }

    /**
     * @throws NonUniqueResultException
     * @throws ValidationException
     * @throws AppReadOnlyModeException
     */
    #[Route('/asset-file/{assetFile}/distribute', name: 'distribute', methods: [Request::METHOD_POST])]
    #[ParamConverter('youtubeDistribution', converter: SerializerParamConverter::class)]
    #[OARequest(YoutubeDistribution::class), OAParameterPath('assetFile'), OAResponse(YoutubeDistribution::class), OAResponseValidation]
    public function distribute(AssetFile $assetFile, YoutubeDistribution $youtubeDistribution): JsonResponse
    {
        App::throwOnReadOnlyMode();
        $this->denyAccessUnlessGranted(DamPermissions::DAM_ASSET_VIEW, $assetFile);
        $this->denyAccessUnlessGranted(DamPermissions::DAM_DISTRIBUTION_ACCESS, $youtubeDistribution->getDistributionService());

        return $this->okResponse(
            $this->distributionFacade->distribute($assetFile, $youtubeDistribution)
        );
    }
}
