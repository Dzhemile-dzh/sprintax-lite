<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddOption\AddQuestionOption;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Application\Questionnaire\RemoveOption\RemoveQuestionOption;
use App\Application\Questionnaire\UpdateOption\UpdateQuestionOption;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\QuestionOption;
use App\Domain\Questionnaire\Entity\Questionnaire;
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
        private readonly UpdateQuestionOption $updateQuestionOption,
        private readonly RemoveQuestionOption $removeQuestionOption,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/questions/{questionId}/options/new', name: 'admin_option_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id, string $questionId): Response
    {
        $this->question($id, $questionId);
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

    #[Route('/questionnaires/{id}/questions/{questionId}/options/{optionId}/edit', name: 'admin_option_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, string $questionId, string $optionId): Response
    {
        $option = $this->option($id, $questionId, $optionId);
        $form = $this->createForm(OptionFormType::class, [
            'label' => $option->label(),
            'value' => $option->value(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->get('label')->getData();
            $value = $form->get('value')->getData();

            if (is_string($label) && is_string($value)) {
                try {
                    $this->updateQuestionOption->execute($id, $questionId, $optionId, $label, $value);

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Edit option',
        ]);
    }

    #[Route('/questionnaires/{id}/questions/{questionId}/options/{optionId}/delete', name: 'admin_option_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, string $questionId, string $optionId): Response
    {
        $this->option($id, $questionId, $optionId);

        if (!$this->isCsrfTokenValid('delete-option-'.$optionId, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->removeQuestionOption->execute($id, $questionId, $optionId);
        } catch (InvalidQuestionnaire $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
    }

    private function option(string $id, string $questionId, string $optionId): QuestionOption
    {
        $option = $this->question($id, $questionId)->findOption($optionId);

        if ($option === null) {
            throw $this->createNotFoundException();
        }

        return $option;
    }

    private function question(string $id, string $questionId): Question
    {
        $question = $this->questionnaire($id)->findQuestion($questionId);

        if ($question === null) {
            throw $this->createNotFoundException();
        }

        return $question;
    }

    private function questionnaire(string $id): Questionnaire
    {
        try {
            return $this->getQuestionnaire->execute($id)->questionnaire;
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }
    }
}
