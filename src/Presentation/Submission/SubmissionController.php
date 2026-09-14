<?php

declare(strict_types=1);

namespace App\Presentation\Submission;

use App\Application\Pdf\DownloadSubmissionPdf;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
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
        private readonly DownloadSubmissionPdf $downloadSubmissionPdf,
    ) {
    }

    #[Route('/submissions/{id}', name: 'submission_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $submission = $this->submission($id);
        $this->denyAccessUnlessGranted(SubmissionVoter::VIEW, $submission);

        return $this->render('submission/show.html.twig', [
            'submission' => $submission,
        ]);
    }

    #[Route('/submissions/{id}/pdf', name: 'submission_pdf', methods: ['GET'])]
    public function download(string $id): Response
    {
        $submission = $this->submission($id);
        $this->denyAccessUnlessGranted(SubmissionVoter::DOWNLOAD, $submission);

        try {
            $path = $this->downloadSubmissionPdf->execute($id);
        } catch (InvalidSubmission) {
            throw $this->createNotFoundException();
        }

        $response = $this->file($path, basename($path));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    private function submission(string $id): QuestionnaireSubmission
    {
        try {
            return $this->submissions->get($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }
    }
}
