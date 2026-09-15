<?php

declare(strict_types=1);

namespace App\Application\Pdf;

use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;

final class ResolveQuestionnairePdfTemplate
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly FileStorageInterface $fileStorage,
        private readonly string $templatesDirectory,
    ) {
    }

    public function execute(string $questionnaireId): ?string
    {
        return $this->forFormType($this->questionnaires->get($questionnaireId)->formType());
    }

    public function forFormType(FormType $formType): ?string
    {
        $path = $this->templatesDirectory.DIRECTORY_SEPARATOR.$formType->value.'.pdf';

        if (!$this->fileStorage->isReadable($path)) {
            return null;
        }

        return $path;
    }
}
