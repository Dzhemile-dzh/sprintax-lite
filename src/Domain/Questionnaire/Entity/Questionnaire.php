<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\QuestionValidation;
use App\Domain\Questionnaire\ValueObject\VisibilityRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'questionnaire')]
final class Questionnaire
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'form_type', enumType: FormType::class, length: 32)]
    private FormType $formType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    /**
     * @var Collection<int, QuestionnaireStep>
     */
    #[ORM\OneToMany(targetEntity: QuestionnaireStep::class, mappedBy: 'questionnaire', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $steps;

    /**
     * @var Collection<int, QuestionMapping>
     */
    #[ORM\OneToMany(targetEntity: QuestionMapping::class, mappedBy: 'questionnaire', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $mappings;

    private function __construct(
        string $id,
        string $name,
        FormType $formType,
        ?string $description,
    ) {
        if (trim($id) === '') {
            throw InvalidQuestionnaire::blank('id');
        }

        if (trim($name) === '') {
            throw InvalidQuestionnaire::blank('name');
        }

        if ($description !== null && trim($description) === '') {
            $description = null;
        }

        $this->id = $id;
        $this->name = $name;
        $this->formType = $formType;
        $this->description = $description;
        $this->steps = new ArrayCollection();
        $this->mappings = new ArrayCollection();
    }

    public static function create(
        string $id,
        string $name,
        FormType $formType,
        ?string $description = null,
    ): self {
        return new self($id, $name, $formType, $description);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function formType(): FormType
    {
        return $this->formType;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidQuestionnaire::blank('name');
        }

        $this->name = $name;
    }

    public function changeFormType(FormType $formType, bool $hasSubmissions): void
    {
        if ($hasSubmissions && $formType !== $this->formType) {
            throw InvalidQuestionnaire::formTypeLocked();
        }

        $this->formType = $formType;
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description !== null && trim($description) === '' ? null : $description;
    }

    /**
     * @return list<QuestionnaireStep>
     */
    public function steps(): array
    {
        /** @var list<QuestionnaireStep> $steps */
        $steps = $this->steps->toArray();
        usort(
            $steps,
            static fn (QuestionnaireStep $left, QuestionnaireStep $right): int => $left->position() <=> $right->position(),
        );

        return $steps;
    }

    public function addStep(string $id, string $title): QuestionnaireStep
    {
        $step = QuestionnaireStep::create($id, $title, $this->steps->count() + 1, $this);
        $this->steps->add($step);

        return $step;
    }

    public function addQuestion(
        string $stepId,
        string $questionId,
        string $key,
        string $label,
        QuestionType $type,
        ?string $helpText = null,
        ?QuestionValidation $validation = null,
        ?VisibilityRule $visibility = null,
    ): Question {
        if ($this->findQuestionByKey($key) !== null) {
            throw InvalidQuestionnaire::duplicateQuestionKey($key);
        }

        $step = $this->step($stepId);
        $question = Question::create(
            $questionId,
            $key,
            $label,
            $type,
            $step->nextQuestionPosition(),
            $step,
            $helpText,
            $validation,
            $visibility,
        );
        $step->addQuestion($question);

        return $question;
    }

    public function addMapping(QuestionMapping $mapping): void
    {
        if ($mapping->source()->isQuestion() && !$this->hasQuestion($mapping->source()->reference)) {
            throw InvalidQuestionnaire::questionNotFound($mapping->source()->reference);
        }

        foreach ($this->mappings as $existing) {
            if (
                $existing->source()->type === $mapping->source()->type
                && $existing->source()->reference === $mapping->source()->reference
            ) {
                throw InvalidQuestionnaire::duplicateMappingSource($mapping->source()->reference);
            }
        }

        $mapping->belongTo($this);
        $this->mappings->add($mapping);
    }

    /**
     * @return list<QuestionMapping>
     */
    public function mappings(): array
    {
        /** @var list<QuestionMapping> $mappings */
        $mappings = $this->mappings->toArray();

        return $mappings;
    }

    public function firstStep(): ?QuestionnaireStep
    {
        $steps = $this->steps();

        return $steps[0] ?? null;
    }

    public function hasQuestion(string $questionId): bool
    {
        foreach ($this->steps as $step) {
            if ($step->hasQuestion($questionId)) {
                return true;
            }
        }

        return false;
    }

    public function findQuestionByKey(string $key): ?Question
    {
        foreach ($this->allQuestions() as $question) {
            if ($question->key() === $key) {
                return $question;
            }
        }

        return null;
    }

    public function findQuestion(string $questionId): ?Question
    {
        foreach ($this->allQuestions() as $question) {
            if ($question->id() === $questionId) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<Question>
     */
    public function allQuestions(): array
    {
        $questions = [];

        foreach ($this->steps() as $step) {
            foreach ($step->questions() as $question) {
                $questions[] = $question;
            }
        }

        return $questions;
    }

    public function hasStep(string $stepId): bool
    {
        return $this->findStep($stepId) !== null;
    }

    public function nextStepAfter(string $stepId): ?QuestionnaireStep
    {
        $steps = $this->steps();

        foreach ($steps as $index => $step) {
            if ($step->id() === $stepId) {
                return $steps[$index + 1] ?? null;
            }
        }

        throw InvalidQuestionnaire::stepNotFound($stepId);
    }

    public function previousStepBefore(string $stepId): ?QuestionnaireStep
    {
        $steps = $this->steps();

        foreach ($steps as $index => $step) {
            if ($step->id() === $stepId) {
                return $index > 0 ? $steps[$index - 1] : null;
            }
        }

        throw InvalidQuestionnaire::stepNotFound($stepId);
    }

    public function findStep(string $stepId): ?QuestionnaireStep
    {
        foreach ($this->steps as $step) {
            if ($step->id() === $stepId) {
                return $step;
            }
        }

        return null;
    }

    private function step(string $stepId): QuestionnaireStep
    {
        $step = $this->findStep($stepId);

        if ($step === null) {
            throw InvalidQuestionnaire::stepNotFound($stepId);
        }

        return $step;
    }
}
