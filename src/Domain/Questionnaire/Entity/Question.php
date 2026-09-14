<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'question')]
#[ORM\UniqueConstraint(name: 'uniq_question_step_position', columns: ['step_id', 'position'])]
#[ORM\UniqueConstraint(name: 'uniq_question_questionnaire_key', columns: ['questionnaire_id', 'key'])]
final class Question
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: QuestionnaireStep::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'step_id', nullable: false, onDelete: 'CASCADE')]
    private QuestionnaireStep $step;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class)]
    #[ORM\JoinColumn(name: 'questionnaire_id', nullable: false, onDelete: 'CASCADE')]
    private Questionnaire $questionnaire;

    #[ORM\Column(length: 100)]
    private string $key;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(enumType: QuestionType::class, length: 32)]
    private QuestionType $type;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(name: 'help_text', type: 'text', nullable: true)]
    private ?string $helpText;

    #[ORM\Column(type: 'question_validation')]
    private QuestionValidation $validation;

    #[ORM\Column(type: 'visibility_rule')]
    private VisibilityRule $visibility;

    /**
     * @var Collection<int, QuestionOption>
     */
    #[ORM\OneToMany(targetEntity: QuestionOption::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    private function __construct(
        string $id,
        string $key,
        string $label,
        QuestionType $type,
        int $position,
        ?string $helpText,
        QuestionValidation $validation,
        VisibilityRule $visibility,
        QuestionnaireStep $step,
    ) {
        if (trim($id) === '') {
            throw InvalidQuestionnaire::blank('question id');
        }

        if (trim($key) === '') {
            throw InvalidQuestionnaire::blank('question key');
        }

        if (trim($label) === '') {
            throw InvalidQuestionnaire::blank('question label');
        }

        if ($position < 1) {
            throw InvalidQuestionnaire::blank('question position');
        }

        if ($helpText !== null && trim($helpText) === '') {
            $helpText = null;
        }

        $this->id = $id;
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
        $this->position = $position;
        $this->helpText = $helpText;
        $this->validation = $validation;
        $this->visibility = $visibility;
        $this->step = $step;
        $this->questionnaire = $step->questionnaire();
        $this->options = new ArrayCollection();
    }

    public static function create(
        string $id,
        string $key,
        string $label,
        QuestionType $type,
        int $position,
        QuestionnaireStep $step,
        ?string $helpText = null,
        ?QuestionValidation $validation = null,
        ?VisibilityRule $visibility = null,
    ): self {
        return new self(
            $id,
            $key,
            $label,
            $type,
            $position,
            $helpText,
            $validation ?? QuestionValidation::none(),
            $visibility ?? VisibilityRule::alwaysVisible(),
            $step,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function helpText(): ?string
    {
        return $this->helpText;
    }

    public function validation(): QuestionValidation
    {
        return $this->validation;
    }

    public function visibility(): VisibilityRule
    {
        return $this->visibility;
    }

    /**
     * @return list<QuestionOption>
     */
    public function options(): array
    {
        /** @var list<QuestionOption> $options */
        $options = $this->options->toArray();
        usort(
            $options,
            static fn (QuestionOption $left, QuestionOption $right): int => $left->position() <=> $right->position(),
        );

        return $options;
    }

    public function addOption(QuestionOption $option): void
    {
        if (!$this->type->allowsOptions()) {
            throw InvalidQuestionnaire::optionsNotAllowed($this->type->value);
        }

        foreach ($this->options as $existing) {
            if ($existing->value() === $option->value()) {
                throw InvalidQuestionnaire::duplicateOptionValue($option->value());
            }
        }

        $option->belongTo($this);
        $this->options->add($option);
    }

    public function configureValidation(QuestionValidation $validation): void
    {
        $this->validation = $validation;
    }

    public function configureVisibility(VisibilityRule $visibility): void
    {
        $this->visibility = $visibility;
    }
}
