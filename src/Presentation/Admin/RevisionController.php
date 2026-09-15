<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Audit\Repository\QuestionnaireRevisionRepositoryInterface;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class RevisionController extends AbstractController
{
    public function __construct(
        private readonly GetQuestionnaire $getQuestionnaire,
        private readonly QuestionnaireRevisionRepositoryInterface $revisions,
    ) {
    }

    #[Route('/questionnaires/{id}/history', name: 'admin_questionnaire_history', methods: ['GET'])]
    public function history(string $id): Response
    {
        return $this->render('admin/questionnaire/history.html.twig', [
            'questionnaire' => $this->questionnaire($id),
            'revisions' => $this->revisions->forQuestionnaire($id),
        ]);
    }

    #[Route(
        '/questionnaires/{id}/history/{version}',
        name: 'admin_questionnaire_revision',
        requirements: ['version' => '\d+'],
        methods: ['GET'],
    )]
    public function revision(string $id, int $version): Response
    {
        $questionnaire = $this->questionnaire($id);
        $revision = $this->revisions->find($id, $version);

        if ($revision === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/questionnaire/revision.html.twig', [
            'questionnaire' => $questionnaire,
            'revision' => $revision,
        ]);
    }

    private function questionnaire(string $id): Questionnaire
    {
        try {
            return $this->getQuestionnaire->execute($id)->questionnaire;
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }
    }
}
