<?php

declare(strict_types=1);

namespace App\Domain\Questionnaire\Entity;

use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\ValueObject\MappingSource;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Domain\Questionnaire\ValueObject\PdfCoordinates;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'question_mapping')]
#[ORM\UniqueConstraint(name: 'uniq_question_mapping_source', columns: ['questionnaire_id', 'source_type', 'source_reference'])]
final class QuestionMapping
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class, inversedBy: 'mappings')]
    #[ORM\JoinColumn(name: 'questionnaire_id', nullable: false, onDelete: 'CASCADE')]
    private Questionnaire $questionnaire;

    #[ORM\Column(name: 'source_type', enumType: MappingSourceType::class, length: 32)]
    private MappingSourceType $sourceType;

    #[ORM\Column(name: 'source_reference', length: 100)]
    private string $sourceReference;

    #[ORM\Column(type: 'pdf_coordinates')]
    private PdfCoordinates $coordinates;

    private function __construct(
        string $id,
        MappingSource $source,
        PdfCoordinates $coordinates,
    ) {
        if (trim($id) === '') {
            throw InvalidQuestionnaire::blank('mapping id');
        }

        $this->id = $id;
        $this->sourceType = $source->type;
        $this->sourceReference = $source->reference;
        $this->coordinates = $coordinates;
    }

    public static function forQuestion(
        string $id,
        string $questionId,
        PdfCoordinates $coordinates,
    ): self {
        return new self($id, MappingSource::question($questionId), $coordinates);
    }

    public static function forComputedField(
        string $id,
        string $fieldName,
        PdfCoordinates $coordinates,
    ): self {
        return new self($id, MappingSource::computedField($fieldName), $coordinates);
    }

    public function belongTo(Questionnaire $questionnaire): void
    {
        $this->questionnaire = $questionnaire;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function source(): MappingSource
    {
        if ($this->sourceType === MappingSourceType::Question) {
            return MappingSource::question($this->sourceReference);
        }

        return MappingSource::computedField($this->sourceReference);
    }

    public function coordinates(): PdfCoordinates
    {
        return $this->coordinates;
    }
}
