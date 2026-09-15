<?php

declare(strict_types=1);

namespace App\Domain\Audit\Entity;

use App\Domain\Audit\DTO\StructureSnapshot;
use App\Domain\Audit\Exception\InvalidRevision;
use App\Domain\Audit\ValueObject\RevisionAction;
use App\Domain\Audit\ValueObject\RevisionActor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * An append-only entry in the questionnaire structure trail.
 *
 * The questionnaire is referenced by id rather than by association so the trail is
 * never cascaded away with the structure it describes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'questionnaire_revision')]
#[ORM\UniqueConstraint(name: 'uniq_questionnaire_revision_version', columns: ['questionnaire_id', 'version'])]
#[ORM\Index(name: 'idx_questionnaire_revision_recorded_at', columns: ['recorded_at'])]
final class QuestionnaireRevision
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\Column(name: 'questionnaire_id', length: 64)]
    private string $questionnaireId;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(enumType: RevisionAction::class, length: 32)]
    private RevisionAction $action;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column(name: 'actor_id', length: 64, nullable: true)]
    private ?string $actorId;

    #[ORM\Column(name: 'actor_email', length: 180, nullable: true)]
    private ?string $actorEmail;

    #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
    private DateTimeImmutable $recordedAt;

    #[ORM\Column(type: 'structure_snapshot')]
    private StructureSnapshot $snapshot;

    private function __construct(
        string $id,
        string $questionnaireId,
        int $version,
        RevisionAction $action,
        string $summary,
        RevisionActor $actor,
        StructureSnapshot $snapshot,
        DateTimeImmutable $recordedAt,
    ) {
        if (trim($id) === '') {
            throw InvalidRevision::blank('id');
        }

        if (trim($questionnaireId) === '') {
            throw InvalidRevision::blank('questionnaire id');
        }

        if (trim($summary) === '') {
            throw InvalidRevision::blank('summary');
        }

        if ($version < 1) {
            throw InvalidRevision::invalidVersion($version);
        }

        $this->id = $id;
        $this->questionnaireId = $questionnaireId;
        $this->version = $version;
        $this->action = $action;
        $this->summary = $summary;
        $this->actorId = $actor->id;
        $this->actorEmail = $actor->email;
        $this->snapshot = $snapshot;
        $this->recordedAt = $recordedAt;
    }

    public static function record(
        string $id,
        string $questionnaireId,
        int $version,
        RevisionAction $action,
        string $summary,
        RevisionActor $actor,
        StructureSnapshot $snapshot,
        DateTimeImmutable $recordedAt,
    ): self {
        return new self(
            $id,
            $questionnaireId,
            $version,
            $action,
            $summary,
            $actor,
            $snapshot,
            $recordedAt,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function questionnaireId(): string
    {
        return $this->questionnaireId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function action(): RevisionAction
    {
        return $this->action;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function actor(): RevisionActor
    {
        if ($this->actorId === null || $this->actorEmail === null) {
            return RevisionActor::system();
        }

        return RevisionActor::user($this->actorId, $this->actorEmail);
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function snapshot(): StructureSnapshot
    {
        return $this->snapshot;
    }
}
