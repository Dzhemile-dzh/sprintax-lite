<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Application\Submission\Get\GetSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Infrastructure\Security\SubmissionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('IS_AUTHENTICATED')]
final class SubmissionApiController extends AbstractController
{
    public function __construct(
        private readonly GetSubmission $getSubmission,
        private readonly ApiSerializer $serializer,
    ) {
    }

    #[Route('/submissions/{id}', name: 'api_submissions_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $submission = $this->getSubmission->execute($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted(SubmissionVoter::VIEW, $submission)) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'submission' => $this->serializer->submission($submission),
        ]);
    }
}
