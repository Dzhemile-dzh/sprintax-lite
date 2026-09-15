<?php

declare(strict_types=1);

namespace App\Domain\Analytics\DTO;

final readonly class AnalyticsOverview
{
    /**
     * @param list<StatusCount> $byStatus
     * @param list<QuestionnaireActivity> $byQuestionnaire
     */
    public function __construct(
        public int $totalSubmissions,
        public int $pdfEmailed,
        public int $answeredQuestions,
        public array $byStatus,
        public array $byQuestionnaire,
    ) {
    }

    public function countForStatus(string $status): int
    {
        foreach ($this->byStatus as $row) {
            if ($row->status === $status) {
                return $row->count;
            }
        }

        return 0;
    }
}
