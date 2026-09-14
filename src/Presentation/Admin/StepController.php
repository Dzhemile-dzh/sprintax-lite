<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddStep\AddQuestionnaireStep;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Presentation\Admin\Form\StepFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class StepController extends AbstractController
{
    public function __construct(
        private readonly AddQuestionnaireStep $addQuestionnaireStep,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/steps/new', name: 'admin_step_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id): Response
    {
        try {
            $this->getQuestionnaire->execute($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(StepFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $title = $form->get('title')->getData();

            if (is_string($title)) {
                try {
                    $this->addQuestionnaireStep->execute($id, $title);

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Add step',
        ]);
    }
}
