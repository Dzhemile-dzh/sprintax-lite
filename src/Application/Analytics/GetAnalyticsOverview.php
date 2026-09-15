<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\DTO\AnalyticsOverview;
use App\Domain\Analytics\Repository\AnalyticsRepositoryInterface;

final class GetAnalyticsOverview
{
    public function __construct(
        private readonly AnalyticsRepositoryInterface $analytics,
    ) {
    }

    public function execute(): AnalyticsOverview
    {
        return $this->analytics->overview();
    }
}
