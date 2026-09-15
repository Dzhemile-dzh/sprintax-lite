<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_ADMIN')]
final class QuestionnaireApiController extends AbstractController
{
    public function __construct(
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly GetQuestionnaire $getQuestionnaire,
        private readonly ApiSerializer $serializer,
    ) {
    }

    #[Route('/questionnaires', name: 'api_questionnaires_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = [];

        foreach ($this->questionnaires->all() as $questionnaire) {
            $items[] = $this->serializer->questionnaireSummary($questionnaire);
        }

        return $this->json(['questionnaires' => $items]);
    }

    #[Route('/questionnaires/{id}', name: 'api_questionnaires_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $loaded = $this->getQuestionnaire->execute($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'questionnaire' => $this->serializer->questionnaireDetail($loaded->questionnaire),
        ]);
    }
}
