<?php

declare(strict_types=1);

namespace BenTools\Pest\ApiPlatform;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_intersect;
use function array_map;
use function array_replace;
use function array_shift;
use function explode;
use function is_string;
use function sprintf;
use function Symfony\Component\String\u;

final class ApiClient
{
    public Client $client;
    private ?string $jwthp = null;
    private ?string $jwts = null;

    /**
     * @var ResponseInterface[]
     */
    private array $mocks = [];

    public function __construct(Client $client, ?UserInterface $user = null)
    {
        $this->client = $client;

        if (null === $user) {
            return;
        }

        [$header, $payload, $signature] = explode('.', jwt($user));
        $this->jwthp = sprintf('%s.%s', $header, $payload);
        $this->jwts = $signature;
    }

    public function as(?UserInterface $user): self
    {
        return new self(clone $this->client, $user);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): ApiResponse
    {
        return $this->request('GET', $url, $this->withOptions($options));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function post(string $url, array|string $data = [], array $options = []): ApiResponse
    {
        if (is_string($data)) {
            $options['body'] = $data;
        } else {
            $options['json'] = $data;
        }

        return $this->request('POST', $url, $this->withOptions($options));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function put(string $url, array $data = [], array $options = []): ApiResponse
    {
        $options['json'] = $data;

        return $this->request('PUT', $url, $this->withOptions($options));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function patch(string $url, array $data = [], array $options = []): ApiResponse
    {
        $options['json'] = $data;
        $options['headers'] = array_replace(
            ['Content-Type' => 'application/merge-patch+json'],
            $options['headers'] ?? [],
        );

        return $this->request('PATCH', $url, $this->withOptions($options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $url, array $options = []): ApiResponse
    {
        return $this->request('DELETE', $url, $this->withOptions($options));
    }

    /**
     * Mocks the next response.
     */
    public function mock(ResponseInterface $response): void
    {
        $this->mocks[] = $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options): ApiResponse
    {
        if ([] !== $this->mocks) {
            return new ApiResponse(
                (new MockHttpClient(array_shift($this->mocks), Client::API_OPTIONS_DEFAULTS['base_uri']))
                    ->request($method, $url, $options),
            );
        }
        $this->client->request($method, $url, $options);

        /** @var ResponseInterface $response */
        $response = $this->client->getResponse();

        $contentTypes = array_map(
            fn (string $contentType) => (string) u($contentType)->before(';'),
            $response->getHeaders(false)['content-type'] ?? [],
        );

        return new ApiResponse($response);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function withOptions(array $options): array
    {
        if (null !== $this->jwthp && !$this->client->getCookieJar()->get('jwt_hp')) {
            $this->client->getCookieJar()->set(new Cookie('jwt_hp', $this->jwthp));
            $this->client->getCookieJar()->set(new Cookie('jwt_s', $this->jwts));
        }

        return $options;
    }
}
