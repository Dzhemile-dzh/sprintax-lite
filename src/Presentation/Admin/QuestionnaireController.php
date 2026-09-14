<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionnaireController extends AbstractController
{
    #[Route('/admin', name: 'admin_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('admin/home.html.twig');
    }
}
