<?php

declare(strict_types=1);

namespace App\Presentation\Client;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WizardController extends AbstractController
{
    #[Route('/client', name: 'client_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('client/home.html.twig');
    }
}
