<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddOption\AddQuestionOption;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Presentation\Admin\Form\OptionFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class OptionController extends AbstractController
{
    public function __construct(
        private readonly AddQuestionOption $addQuestionOption,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/questions/{questionId}/options/new', name: 'admin_option_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id, string $questionId): Response
    {
        try {
            $questionnaire = $this->getQuestionnaire->execute($id)->questionnaire;
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        if ($questionnaire->findQuestion($questionId) === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(OptionFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->get('label')->getData();
            $value = $form->get('value')->getData();

            if (is_string($label) && is_string($value)) {
                try {
                    $this->addQuestionOption->execute($id, $questionId, $label, $value);

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Add option',
        ]);
    }
}
