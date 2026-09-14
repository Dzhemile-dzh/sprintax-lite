<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
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
        $position = 0;

        foreach ($this->steps as $step) {
            $position = max($position, $step->position());
        }

        $step = QuestionnaireStep::create($id, $title, $position + 1, $this);
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
        $this->assertVisibilityQuestionsExist($question->visibility(), $question->id());
        $step->addQuestion($question);

        return $question;
    }

    public function addMapping(QuestionMapping $mapping): void
    {
        if ($mapping->source()->isQuestion() && $this->findQuestionByKey($mapping->source()->reference) === null) {
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

    public function renameStep(string $stepId, string $title): void
    {
        $this->step($stepId)->rename($title);
    }

    public function removeStep(string $stepId): void
    {
        $step = $this->step($stepId);
        $stepQuestionIds = [];

        foreach ($step->questions() as $question) {
            $stepQuestionIds[] = $question->id();
        }

        foreach ($step->questions() as $question) {
            $this->assertQuestionNotReferenced($question, $stepQuestionIds);
        }

        $this->steps->removeElement($step);
    }

    public function updateQuestion(
        string $questionId,
        string $label,
        ?string $helpText,
        QuestionValidation $validation,
        VisibilityRule $visibility,
    ): void {
        $question = $this->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        $this->assertVisibilityQuestionsExist($visibility, $question->id());
        $question->relabel($label);
        $question->changeHelpText($helpText);
        $question->configureValidation($validation);
        $question->configureVisibility($visibility);
    }

    public function removeQuestion(string $questionId): void
    {
        $question = $this->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        $this->assertQuestionNotReferenced($question);
        $question->step()->removeQuestion($question);
    }

    public function updateOption(string $questionId, string $optionId, string $label, string $value): void
    {
        $this->question($questionId)->updateOption($optionId, $label, $value);
    }

    public function removeOption(string $questionId, string $optionId): void
    {
        $this->question($questionId)->removeOption($optionId);
    }

    public function findMapping(string $mappingId): ?QuestionMapping
    {
        foreach ($this->mappings as $mapping) {
            if ($mapping->id() === $mappingId) {
                return $mapping;
            }
        }

        return null;
    }

    public function relocateMapping(string $mappingId, PdfCoordinates $coordinates): void
    {
        $mapping = $this->findMapping($mappingId);

        if ($mapping === null) {
            throw InvalidQuestionnaire::mappingNotFound($mappingId);
        }

        $mapping->relocate($coordinates);
    }

    public function removeMapping(string $mappingId): void
    {
        $mapping = $this->findMapping($mappingId);

        if ($mapping === null) {
            throw InvalidQuestionnaire::mappingNotFound($mappingId);
        }

        $this->mappings->removeElement($mapping);
    }

    private function question(string $questionId): Question
    {
        $question = $this->findQuestion($questionId);

        if ($question === null) {
            throw InvalidQuestionnaire::questionNotFound($questionId);
        }

        return $question;
    }

    private function assertVisibilityQuestionsExist(VisibilityRule $visibility, string $excludedQuestionId): void
    {
        foreach ($visibility->conditions as $condition) {
            $target = $this->findQuestionByKey($condition->questionKey);

            if ($target === null || $target->id() === $excludedQuestionId) {
                throw InvalidQuestionnaire::unknownVisibilityQuestion($condition->questionKey);
            }
        }
    }

    /**
     * @param list<string> $ignoredQuestionIds
     */
    private function assertQuestionNotReferenced(Question $question, array $ignoredQuestionIds = []): void
    {
        foreach ($this->allQuestions() as $other) {
            if ($other->id() === $question->id() || in_array($other->id(), $ignoredQuestionIds, true)) {
                continue;
            }

            foreach ($other->visibility()->conditions as $condition) {
                if ($condition->questionKey === $question->key()) {
                    throw InvalidQuestionnaire::questionReferenced($question->key());
                }
            }
        }

        foreach ($this->mappings as $mapping) {
            if ($mapping->source()->isQuestion() && $mapping->source()->reference === $question->key()) {
                throw InvalidQuestionnaire::questionReferenced($question->key());
            }
        }
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
