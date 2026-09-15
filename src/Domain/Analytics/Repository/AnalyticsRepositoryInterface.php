<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Repository;

use App\Domain\Analytics\DTO\AnalyticsOverview;

interface AnalyticsRepositoryInterface
{
    public function overview(): AnalyticsOverview;
}
