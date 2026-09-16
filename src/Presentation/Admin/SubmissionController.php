<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    #[Route('/submissions', name: 'admin_submissions', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/submission/index.html.twig', [
            'submissions' => $this->submissions->findAllRecent(),
        ]);
    }
}
