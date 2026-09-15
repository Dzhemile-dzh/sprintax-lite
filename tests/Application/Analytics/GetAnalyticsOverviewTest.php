<?php

declare(strict_types=1);

namespace App\Tests\Application\Analytics;

use App\Application\Analytics\GetAnalyticsOverview;
use App\Domain\Analytics\DTO\QuestionnaireActivity;
use App\Tests\Support\InMemoryAnalyticsRepository;
use PHPUnit\Framework\TestCase;

final class GetAnalyticsOverviewTest extends TestCase
{
    public function testItReturnsTheRepositoryOverview(): void
    {
        $overview = InMemoryAnalyticsRepository::withTotals(
            5,
            2,
            1,
            2,
            1,
            12,
            [
                new QuestionnaireActivity('q-1', '1040-NR', 5, 2, 1, 2, 1),
            ],
        )->overview();

        $useCase = new GetAnalyticsOverview(
            InMemoryAnalyticsRepository::withTotals(5, 2, 1, 2, 1, 12, [
                new QuestionnaireActivity('q-1', '1040-NR', 5, 2, 1, 2, 1),
            ]),
        );

        $result = $useCase->execute();

        self::assertSame(5, $result->totalSubmissions);
        self::assertSame(2, $result->countForStatus('in_progress'));
        self::assertSame(1, $result->countForStatus('finalized'));
        self::assertSame(2, $result->countForStatus('pdf_ready'));
        self::assertSame(1, $result->pdfEmailed);
        self::assertSame(12, $result->answeredQuestions);
        self::assertSame('1040-NR', $result->byQuestionnaire[0]->questionnaireName);
        self::assertSame($overview->totalSubmissions, $result->totalSubmissions);
    }
}
