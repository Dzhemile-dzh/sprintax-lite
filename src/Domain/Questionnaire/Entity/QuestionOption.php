<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'question_option')]
#[ORM\UniqueConstraint(name: 'uniq_question_option_value', columns: ['question_id', 'value'])]
#[ORM\UniqueConstraint(name: 'uniq_question_option_position', columns: ['question_id', 'position'])]
final class QuestionOption
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 100)]
    private string $value;

    #[ORM\Column]
    private int $position;

    private function __construct(
        string $id,
        string $label,
        string $value,
        int $position,
    ) {
        if (trim($id) === '') {
            throw InvalidQuestionnaire::blank('option id');
        }

        if (trim($label) === '') {
            throw InvalidQuestionnaire::blank('option label');
        }

        if (trim($value) === '') {
            throw InvalidQuestionnaire::blank('option value');
        }

        if ($position < 1) {
            throw InvalidQuestionnaire::blank('option position');
        }

        $this->id = $id;
        $this->label = $label;
        $this->value = $value;
        $this->position = $position;
    }

    public static function create(string $id, string $label, string $value, int $position): self
    {
        return new self($id, $label, $value, $position);
    }

    public function belongTo(Question $question): void
    {
        $this->question = $question;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function relabel(string $label): void
    {
        if (trim($label) === '') {
            throw InvalidQuestionnaire::blank('option label');
        }

        $this->label = $label;
    }

    public function changeValue(string $value): void
    {
        if (trim($value) === '') {
            throw InvalidQuestionnaire::blank('option value');
        }

        $this->value = $value;
    }

    public function reposition(int $position): void
    {
        if ($position < 1) {
            throw InvalidQuestionnaire::blank('option position');
        }

        $this->position = $position;
    }
}
