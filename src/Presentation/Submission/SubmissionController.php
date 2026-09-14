<?php

declare(strict_types=1);

namespace App\Presentation\Submission;

use App\Application\Pdf\DownloadSubmissionPdf;
use App\Application\Submission\Get\GetSubmission;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Infrastructure\Security\SecurityUser;
use App\Infrastructure\Security\SubmissionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly GetSubmission $getSubmission,
        private readonly DownloadSubmissionPdf $downloadSubmissionPdf,
    ) {
    }

    #[Route('/submissions/{id}', name: 'submission_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $submission = $this->authorizedSubmission($id, SubmissionVoter::VIEW);

        return $this->render('submission/show.html.twig', [
            'submission' => $submission,
        ]);
    }

    #[Route('/submissions/{id}/pdf', name: 'submission_pdf', methods: ['GET'])]
    public function download(string $id): Response
    {
        $this->authorizedSubmission($id, SubmissionVoter::DOWNLOAD);
        $actor = $this->securityUser();

        try {
            $path = $this->downloadSubmissionPdf->execute($id, $actor->id(), $actor->isAdmin());
        } catch (InvalidSubmission) {
            throw $this->createNotFoundException();
        }

        $response = $this->file($path, basename($path));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    private function authorizedSubmission(string $id, string $attribute): QuestionnaireSubmission
    {
        try {
            $submission = $this->getSubmission->execute($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted($attribute, $submission)) {
            throw $this->createNotFoundException();
        }

        return $submission;
    }

    private function securityUser(): SecurityUser
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
