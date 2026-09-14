<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'questionnaire_step')]
#[ORM\UniqueConstraint(name: 'uniq_questionnaire_step_position', columns: ['questionnaire_id', 'position'])]
final class QuestionnaireStep
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(name: 'questionnaire_id', nullable: false, onDelete: 'CASCADE')]
    private Questionnaire $questionnaire;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column]
    private int $position;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'step', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    private function __construct(
        string $id,
        string $title,
        int $position,
        Questionnaire $questionnaire,
    ) {
        if (trim($id) === '') {
            throw InvalidQuestionnaire::blank('step id');
        }

        if (trim($title) === '') {
            throw InvalidQuestionnaire::blank('step title');
        }

        if ($position < 1) {
            throw InvalidQuestionnaire::blank('step position');
        }

        $this->id = $id;
        $this->title = $title;
        $this->position = $position;
        $this->questionnaire = $questionnaire;
        $this->questions = new ArrayCollection();
    }

    public static function create(string $id, string $title, int $position, Questionnaire $questionnaire): self
    {
        return new self($id, $title, $position, $questionnaire);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function questionnaire(): Questionnaire
    {
        return $this->questionnaire;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function rename(string $title): void
    {
        if (trim($title) === '') {
            throw InvalidQuestionnaire::blank('step title');
        }

        $this->title = $title;
    }

    /**
     * @return list<Question>
     */
    public function questions(): array
    {
        /** @var list<Question> $questions */
        $questions = $this->questions->toArray();
        usort(
            $questions,
            static fn (Question $left, Question $right): int => $left->position() <=> $right->position(),
        );

        return $questions;
    }

    public function addQuestion(Question $question): void
    {
        $this->questions->add($question);
    }

    public function nextQuestionPosition(): int
    {
        return $this->questions->count() + 1;
    }

    public function hasQuestion(string $questionId): bool
    {
        foreach ($this->questions as $question) {
            if ($question->id() === $questionId) {
                return true;
            }
        }

        return false;
    }
}
