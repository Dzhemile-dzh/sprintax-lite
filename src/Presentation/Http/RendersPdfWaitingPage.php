<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-require-extends AbstractController
 */
trait RendersPdfWaitingPage
{
    /**
     * @param array<string, mixed> $parameters
     */
    private function renderPdfWaiting(string $view, array $parameters, bool $awaitingPdf): Response
    {
        $response = $this->render($view, [...$parameters, 'awaiting_pdf' => $awaitingPdf]);

        if ($awaitingPdf) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
