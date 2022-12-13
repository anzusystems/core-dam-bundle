<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Tests;

use AnzuSystems\CommonBundle\ApiFilter\ApiResponseList;
use AnzuSystems\CoreDamBundle\Tests\Data\Entity\User;
use AnzuSystems\SerializerBundle\Serializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T
 */
final class ApiClient
{
    private ?Response $response = null;

    public function __construct(
        protected KernelBrowser $client,
        protected Serializer $serializer,
        private readonly ?int $userId = null,
    ) {}

    public function get(string $uri): Response
    {
        return $this->request(Request::METHOD_GET, $uri);
    }

    public function delete(string $uri): Response
    {
        return $this->request(Request::METHOD_DELETE, $uri);
    }

    public function post(string $uri, array $jsonBody = []): Response
    {
        return $this->request(Request::METHOD_POST, $uri, json_encode($jsonBody));
    }

    public function postChunkFile(string $uri, UploadedFile $file, array $attributes = []): Response
    {
        return $this->request(
            method: Request::METHOD_POST,
            uri: $uri,
            attributes: [
                'chunk' => json_encode($attributes)
            ],
            files: [
                'file' => $file
            ]
        );
    }

    public function patch(string $uri, array $jsonBody = []): Response
    {
        return $this->request(Request::METHOD_PATCH, $uri,  json_encode($jsonBody));
    }

    public function put(string $uri, array $jsonBody = []): Response
    {
        return $this->request(Request::METHOD_PUT, $uri, json_encode($jsonBody));
    }

    /**
     * @param class-string<T> $className
     * @return T
     */
    public function deserializeResponse(Response $response, string $className): object
    {
        return $this->serializer->deserialize($response->getContent(), $className);
    }

    /**
     * @return iterable<string|int, T>
     *
     * @param class-string<T> $className
     */
    public function deserializeApiResponseList(Response $response, string $className): iterable
    {
        $apiResponseList = $this->serializer->deserialize($response->getContent(), ApiResponseList::class);

        return $this->serializer->fromArrayToIterable($apiResponseList->getData(), $className, []);
    }

    /**
     * @return iterable<string|int, T>
     *
     * @param T|null
     */
    public function deserializeFirstFromList(Response $response, string $className): object|null
    {
        $list = $this->deserializeApiResponseList($response, $className);

        return $list[0] ?? null;
    }

    private function request(string $method, string $uri, string $body = '', array $attributes = [], array $files = []): Response
    {
        $this->response = null;
        $headers =  [
            'CONTENT_TYPE' => 'application/json',
        ];
        if ($this->userId) {
            $user = $this->client->getContainer()
                ->get(EntityManagerInterface::class)
                ->getReference(User::class, $this->userId);
            $this->client->loginUser($user);
        }

        $this->client->request(
            method: $method,
            uri: $uri,
            parameters: $attributes,
            files: $files,
            server: $headers,
            content: $body,
        );

        $this->response = $this->client->getResponse();

        return $this->response;
    }
}
