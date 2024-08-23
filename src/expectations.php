<?php

declare(strict_types=1);

namespace BenTools\Pest\ApiPlatform;

use ApiPlatform\Symfony\Routing\Router;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Comparator\ComparisonFailure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function BenTools\Pest\Symfony\inject;
use function expect;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function str_starts_with;

expect()->extend('toHaveStatusCode', function (int $expectedStatusCode) {
    /** @var Expectation<mixed> $expectation */
    $expectation = $this;

    $response = $expectation->value;
    if (!$response instanceof ResponseInterface) {
        throw new ExpectationFailedException(
            sprintf('Expected instance of %s, %s given.', ResponseInterface::class, get_debug_type($response))
        );
    }

    return $expectation->getStatusCode()->toBe($expectedStatusCode);
});

expect()->extend('toHaveViolation', function (string $propertyPath, ?string $expectedMessage = null) {
    /** @var Expectation<mixed> $expectation */
    $expectation = $this;

    $content = $expectation->value;
    if ($content instanceof ResponseInterface) {
        $expectation->toHaveStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        $content = $content->toArray(false);
    }

    foreach ($content['violations'] ?? [] as $violation) {
        if (($violation['propertyPath'] ?? null) !== $propertyPath) {
            continue;
        }

        if (null === $expectedMessage) {
            return $expectation->not->toBeEmpty();
        }

        if (($violation['message'] ?? null) !== $expectedMessage) {
            throw new ExpectationFailedException(
                "Failed asserting that $propertyPath was violated as expected.",
                new ComparisonFailure(
                    $expectedMessage,
                    $violation['message'] ?? null,
                    $expectedMessage,
                    $violation['message'] ?? '',
                ),
            );
        }

        return $expectation->not->toBeEmpty();
    }

    throw new ExpectationFailedException("Failed asserting that $propertyPath was violated as expected.");
});

expect()->extend('toHaveHydraType', function (string $expectedType) {
    /** @var Expectation<mixed> $expectation */
    $expectation = $this;

    $response = $expectation->value;
    if (!$response instanceof ResponseInterface) {
        throw new ExpectationFailedException(
            sprintf('Expected instance of %s, %s given.', ResponseInterface::class, get_debug_type($response))
        );
    }

    $json = $response->toArray(false);

    if ($expectedType !== ($json['@type'] ?? null)) {
        throw new ExpectationFailedException(
            sprintf('Expecting Hydra type `%s`, got `%s`', $expectedType, $json['@type'] ?? 'null')
        );
    }

    return $expectation->not->toBeEmpty();
});

expect()->extend('toHaveRelation', function (string|object $resource, bool $nullable = false) {
    /** @var Expectation<mixed> $expectation */
    $expectation = $this;

    if (null !== $expectation->value && !is_array($expectation->value) && !is_string($expectation->value)) {
        throw new ExpectationFailedException(
            sprintf('Expecting array|null|string, got `%s`', get_debug_type($expectation->value))
        );
    }

    if (false === $nullable && null === $expectation->value) {
        throw new ExpectationFailedException('This relation cannot be null.');
    }

    if (true === $nullable && null === $expectation->value) {
        return $expectation->toBeNull();
    }

    $router = inject(Router::class);
    $iri = (string) getIriFromJson($expectation->value);

    try {
        $route = $router->match($iri);

        if (is_object($resource)) {
            if ((string) iri($resource) !== $iri) {
                throw new ExpectationFailedException(
                    sprintf('Failed asserting that `%s` is `%s`.', $iri, iri($resource))
                );
            }

            return $expectation;
        }

        if (str_starts_with($resource, '/')) {
            return $expectation->toBe($iri);
        }

        if ($resource !== ($route['_api_resource_class'] ?? null)) {
            throw new ExpectationFailedException(
                sprintf('Failed asserting that `%s` is a resource of type `%s`.', $iri, $resource)
            );
        }

        return $expectation->not->toBeEmpty();
    } catch (ResourceNotFoundException) {
        throw new ExpectationFailedException(
            sprintf('Failed asserting that `%s` is a resource of type `%s`.', $iri, $resource)
        );
    }
});

expect()->extend('toBeUlidString', function () {
    /** @var Expectation<mixed> $expectation */
    $expectation = $this;

    if (!is_string($expectation->value)) {
        throw new ExpectationFailedException(
            sprintf('Expected valid ULID, got %s', get_debug_type($expectation->value))
        );
    }

    if (false === Ulid::isValid($expectation->value)) {
        throw new ExpectationFailedException(
            sprintf('`%s` is not a valid ULID.', $expectation->value)
        );
    }

    return $expectation->toBeString();
});
