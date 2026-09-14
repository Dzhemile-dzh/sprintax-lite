<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddQuestion\AddQuestionnaireQuestion;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Application\Questionnaire\RemoveQuestion\RemoveQuestionnaireQuestion;
use App\Application\Questionnaire\UpdateQuestion\UpdateQuestionnaireQuestion;
use App\Domain\Questionnaire\Entity\Question;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Questionnaire\ValueObject\VisibilityOperator;
use App\Presentation\Admin\Form\QuestionFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class QuestionController extends AbstractController
{
    public function __construct(
        private readonly AddQuestionnaireQuestion $addQuestionnaireQuestion,
        private readonly UpdateQuestionnaireQuestion $updateQuestionnaireQuestion,
        private readonly RemoveQuestionnaireQuestion $removeQuestionnaireQuestion,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/steps/{stepId}/questions/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id, string $stepId): Response
    {
        $questionnaire = $this->questionnaire($id);

        if ($questionnaire->findStep($stepId) === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(QuestionFormType::class, null, [
            'visibility_question_keys' => QuestionnaireFormChoices::questionKeys($questionnaire),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $key = $form->get('key')->getData();
            $label = $form->get('label')->getData();
            $type = $form->get('type')->getData();
            $helpText = $form->get('helpText')->getData();

            if (is_string($key) && is_string($label) && $type instanceof QuestionType && ($helpText === null || is_string($helpText))) {
                try {
                    $this->addQuestionnaireQuestion->execute(
                        $id,
                        $stepId,
                        $key,
                        $label,
                        $type,
                        $helpText,
                        $form->get('required')->getData() === true,
                        $form->get('min')->getData(),
                        $form->get('max')->getData(),
                        $form->get('regex')->getData(),
                        $form->get('visibilityQuestionKey')->getData(),
                        $form->get('visibilityOperator')->getData(),
                        $form->get('visibilityExpectedValue')->getData(),
                    );

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Add question',
        ]);
    }

    #[Route('/questionnaires/{id}/questions/{questionId}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, string $questionId): Response
    {
        $questionnaire = $this->questionnaire($id);
        $question = $questionnaire->findQuestion($questionId);

        if ($question === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(QuestionFormType::class, $this->questionData($question), [
            'lock_identity' => true,
            'visibility_question_keys' => QuestionnaireFormChoices::questionKeys($questionnaire, $question->key()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->get('label')->getData();
            $helpText = $form->get('helpText')->getData();

            if (is_string($label) && ($helpText === null || is_string($helpText))) {
                try {
                    $this->updateQuestionnaireQuestion->execute(
                        $id,
                        $questionId,
                        $label,
                        $helpText,
                        $form->get('required')->getData() === true,
                        $form->get('min')->getData(),
                        $form->get('max')->getData(),
                        $form->get('regex')->getData(),
                        $form->get('visibilityQuestionKey')->getData(),
                        $form->get('visibilityOperator')->getData(),
                        $form->get('visibilityExpectedValue')->getData(),
                    );

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Edit question',
        ]);
    }

    #[Route('/questionnaires/{id}/questions/{questionId}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, string $questionId): Response
    {
        $this->questionnaire($id);

        if (!$this->isCsrfTokenValid('delete-question-'.$questionId, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->removeQuestionnaireQuestion->execute($id, $questionId);
        } catch (InvalidQuestionnaire $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     helpText: ?string,
     *     required: bool,
     *     min: ?int,
     *     max: ?int,
     *     regex: ?string,
     *     visibilityQuestionKey: ?string,
     *     visibilityOperator: ?VisibilityOperator,
     *     visibilityExpectedValue: ?string
     * }
     */
    private function questionData(Question $question): array
    {
        $condition = $question->visibility()->conditions[0] ?? null;

        return [
            'key' => $question->key(),
            'label' => $question->label(),
            'type' => $question->type(),
            'helpText' => $question->helpText(),
            'required' => $question->validation()->required,
            'min' => $question->validation()->min,
            'max' => $question->validation()->max,
            'regex' => $question->validation()->regex,
            'visibilityQuestionKey' => $condition?->questionKey,
            'visibilityOperator' => $condition?->operator,
            'visibilityExpectedValue' => $condition?->expectedValue,
        ];
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
