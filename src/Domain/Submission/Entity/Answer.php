<?php

declare(strict_types=1);

namespace App\Domain\Submission\Entity;

use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Submission\ValueObject\AnswerValue;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'submission_answer')]
final class Answer
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: QuestionnaireSubmission::class, inversedBy: 'answers')]
    #[ORM\JoinColumn(name: 'submission_id', nullable: false, onDelete: 'CASCADE')]
    private QuestionnaireSubmission $submission;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'RESTRICT')]
    private Question $question;

    #[ORM\Column(type: 'answer_value')]
    private AnswerValue $value;

    public function __construct(
        QuestionnaireSubmission $submission,
        Question $question,
        AnswerValue $value,
    ) {
        $this->submission = $submission;
        $this->question = $question;
        $this->value = $value;
    }

    public function question(): Question
    {
        return $this->question;
    }

    public function value(): AnswerValue
    {
        return $this->value;
    }

    public function replaceValue(AnswerValue $value): void
    {
        $this->value = $value;
    }
}
