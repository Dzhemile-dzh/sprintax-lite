<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Analytics\DTO\AnalyticsOverview;
use App\Domain\Analytics\DTO\QuestionnaireActivity;
use App\Domain\Analytics\DTO\StatusCount;
use App\Domain\Analytics\Repository\AnalyticsRepositoryInterface;

final class InMemoryAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function __construct(
        private readonly AnalyticsOverview $overview = new AnalyticsOverview(0, 0, 0, [], []),
    ) {
    }

    public static function empty(): self
    {
        return new self(new AnalyticsOverview(
            0,
            0,
            0,
            [
                new StatusCount('in_progress', 0),
                new StatusCount('finalized', 0),
                new StatusCount('pdf_ready', 0),
            ],
            [],
        ));
    }

    /**
     * @param list<QuestionnaireActivity> $byQuestionnaire
     */
    public static function withTotals(
        int $total,
        int $inProgress,
        int $finalized,
        int $pdfReady,
        int $pdfEmailed,
        int $answers,
        array $byQuestionnaire = [],
    ): self {
        return new self(new AnalyticsOverview(
            $total,
            $pdfEmailed,
            $answers,
            [
                new StatusCount('in_progress', $inProgress),
                new StatusCount('finalized', $finalized),
                new StatusCount('pdf_ready', $pdfReady),
            ],
            $byQuestionnaire,
        ));
    }

    public function overview(): AnalyticsOverview
    {
        return $this->overview;
    }
}
