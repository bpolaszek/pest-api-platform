<?php

declare(strict_types=1);

namespace BenTools\Pest\ApiPlatform;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_key_exists;
use function sprintf;

final class ApiResponse implements ResponseInterface, ArrayAccess
{
    private array $json;

    public function __construct(
        public ResponseInterface $decorated,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->decorated->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->decorated->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        return $this->decorated->getContent($throw);
    }

    public function toArray(bool $throw = true): array
    {
        return $this->json ??= $this->decorated->toArray($throw);
    }

    public function cancel(): void
    {
        $this->decorated->cancel();
    }

    public function getInfo(string $type = null): mixed
    {
        return $this->decorated->getInfo($type);
    }

    public function items(): array
    {
        return $this['hydra:member'];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray(false));
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            throw new InvalidArgumentException("Key $offset not found.");
        }
        return $this->toArray(false)[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException(sprintf('%s is not invokable.', __METHOD__));
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException(sprintf('%s is not invokable.', __METHOD__));
    }
}

