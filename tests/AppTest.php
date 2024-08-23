<?php

declare(strict_types=1);

namespace BenTools\Pest\ApiPlatform\Tests;

use BenTools\Pest\ApiPlatform\ApiClient;

use function BenTools\Pest\ApiPlatform\api;

it('works', function () {
    expect(api())->toBeInstanceOf(ApiClient::class);
});
