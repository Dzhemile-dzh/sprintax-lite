<?php

declare(strict_types=1);

namespace App\Presentation\Submission;

use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Infrastructure\Security\SubmissionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    #[Route('/submissions/{id}', name: 'submission_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        try {
            $submission = $this->submissions->get($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(SubmissionVoter::VIEW, $submission);

        return $this->render('submission/show.html.twig', [
            'submission' => $submission,
        ]);
    }
}
