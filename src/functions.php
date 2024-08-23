<?php

declare(strict_types=1);

namespace BenTools\Pest\ApiPlatform;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
use Stringable;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\Security\Core\User\UserInterface;

use function BenTools\Pest\Symfony\inject;
use function class_exists;
use function is_string;


function jwt(UserInterface $user): string
{
    return inject(JWTTokenManagerInterface::class)->create($user);
}

function createBrowser(): Client
{
    if (!class_exists(Client::class)) {
        throw new RuntimeException('You need the api-platform/core package to use this function.');
    }
    if (!class_exists(NativeHttpClient::class)) {
        throw new RuntimeException('You need the symfony/http-client package to use this function.');
    }
    if (!class_exists(AbstractBrowser::class)) {
        throw new RuntimeException('You need the symfony/browser-kit package to use this function.');
    }

    /** @var Client $client */
    $client = inject('test.api_platform.client');
    $client->disableReboot();

    return $client;
}

function getIriFromJson(string|array|null $json): ?string
{
    return $json['@id'] ?? $json ?? null;
}

function api(?Client $client = null): ApiClient
{
    return new ApiClient($client ?? createBrowser());
}

function iri(object|string|null $resource, int|string|Stringable $id = null, ?Operation $operation = null, array $context = []): ?string
{
    if (null === $resource) {
        return null;
    }

    $iriConverter = inject(IriConverterInterface::class);

    if (is_string($resource)) {
        $endpoint = $iriConverter->getIriFromResource(
            $resource,
            operation: $operation ?? new GetCollection(),
            context: $context,
        );

        if (null !== $id) {
            $endpoint .= '/' . $id;
        }

        return $endpoint;
    }

    return $iriConverter->getIriFromResource($resource, operation: $operation, context: $context);
}
