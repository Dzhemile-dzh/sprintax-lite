<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Domain\Analytics\Repository\AnalyticsRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsRepositoryInterface $analytics,
    ) {
    }

    #[Route('/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/analytics/index.html.twig', [
            'overview' => $this->analytics->overview(),
        ]);
    }
}
