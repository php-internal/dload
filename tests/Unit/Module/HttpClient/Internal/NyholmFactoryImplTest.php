<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\HttpClient\Internal;

use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\HttpClient\Internal\RedirectingClient;
use Internal\DLoad\Service\Logger;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(NyholmFactoryImpl::class)]
final class NyholmFactoryImplTest
{
    #[Test]
    public function clientFollowsRedirectsWithoutLeakingCredentials(): void
    {
        Assert::instanceOf((new NyholmFactoryImpl(new Logger()))->client(), RedirectingClient::class);
    }
}
