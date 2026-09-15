<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Analytics\DTO\AnalyticsOverview;
use App\Domain\Analytics\DTO\QuestionnaireActivity;
use App\Domain\Analytics\DTO\StatusCount;
use App\Domain\Analytics\Repository\AnalyticsRepositoryInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Submission\Entity\Answer;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function overview(): AnalyticsOverview
    {
        return new AnalyticsOverview(
            $this->totalSubmissions(),
            $this->pdfEmailedCount(),
            $this->answeredQuestionCount(),
            $this->byStatus(),
            $this->byQuestionnaire(),
        );
    }

    private function totalSubmissions(): int
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(submission.id)')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function pdfEmailedCount(): int
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(submission.id)')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->where('submission.pdfEmailedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function answeredQuestionCount(): int
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(IDENTITY(answer.submission))')
            ->from(Answer::class, 'answer')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return list<StatusCount>
     */
    private function byStatus(): array
    {
        /** @var list<array{status: SubmissionStatus, count: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('submission.status AS status', 'COUNT(submission.id) AS count')
            ->from(QuestionnaireSubmission::class, 'submission')
            ->groupBy('submission.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach (SubmissionStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($rows as $row) {
            $status = $row['status'];
            $counts[$status->value] = (int) $row['count'];
        }

        $result = [];

        foreach ($counts as $status => $count) {
            $result[] = new StatusCount($status, $count);
        }

        return $result;
    }

    /**
     * @return list<QuestionnaireActivity>
     */
    private function byQuestionnaire(): array
    {
        /** @var list<Questionnaire> $questionnaires */
        $questionnaires = $this->entityManager->createQueryBuilder()
            ->select('questionnaire')
            ->from(Questionnaire::class, 'questionnaire')
            ->orderBy('questionnaire.name', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<array{
         *     questionnaireId: string,
         *     status: SubmissionStatus,
         *     count: int|string,
         *     emailed: int|string
         * }> $rows
         */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(submission.questionnaire) AS questionnaireId',
                'submission.status AS status',
                'COUNT(submission.id) AS count',
                'SUM(CASE WHEN submission.pdfEmailedAt IS NOT NULL THEN 1 ELSE 0 END) AS emailed',
            )
            ->from(QuestionnaireSubmission::class, 'submission')
            ->groupBy('submission.questionnaire', 'submission.status')
            ->getQuery()
            ->getArrayResult();

        $activity = [];

        foreach ($questionnaires as $questionnaire) {
            $activity[$questionnaire->id()] = [
                'name' => $questionnaire->name(),
                'started' => 0,
                'in_progress' => 0,
                'finalized' => 0,
                'pdf_ready' => 0,
                'pdf_emailed' => 0,
            ];
        }

        foreach ($rows as $row) {
            $id = $row['questionnaireId'];

            if (!isset($activity[$id])) {
                continue;
            }

            $count = (int) $row['count'];
            $activity[$id]['started'] += $count;
            $activity[$id][$row['status']->value] += $count;
            $activity[$id]['pdf_emailed'] += (int) $row['emailed'];
        }

        $result = [];

        foreach ($activity as $id => $row) {
            $result[] = new QuestionnaireActivity(
                $id,
                $row['name'],
                $row['started'],
                $row['in_progress'],
                $row['finalized'],
                $row['pdf_ready'],
                $row['pdf_emailed'],
            );
        }

        return $result;
    }
}
