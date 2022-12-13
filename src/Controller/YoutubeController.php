<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Controller;

use AnzuSystems\CoreDamBundle\Distribution\Modules\Youtube\YoutubeAuthenticator;
use AnzuSystems\CoreDamBundle\Domain\Configuration\DistributionConfigurationProvider;
use AnzuSystems\CoreDamBundle\Model\Dto\Youtube\YoutubeCodeDto;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use Google\Exception;
use Psr\Cache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/youtube', name: '_youtube')]
final class YoutubeController extends AbstractPublicController
{
    public function __construct(
        private readonly YoutubeAuthenticator $authenticator,
        private readonly DistributionConfigurationProvider $distributionConfigurationProvider,
    ) {
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws SerializerException
     */
    #[Route(path: '/code', name: '_code', methods: [Request::METHOD_GET])]
    #[ParamConverter('codeDto', converter: SerializerParamConverter::class)]
    public function exchangeCodeForAccessToken(YoutubeCodeDto $codeDto): Response
    {
        return new RedirectResponse(
            $this->distributionConfigurationProvider->getAuthorizedRedirectUrl(
                $this->authenticator->authorizeCode($codeDto)
            )
        );
    }
}
