<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddQuestion\AddQuestionnaireQuestion;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\ValueObject\QuestionType;
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
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/steps/{stepId}/questions/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id, string $stepId): Response
    {
        try {
            $questionnaire = $this->getQuestionnaire->execute($id)->questionnaire;
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        if ($questionnaire->findStep($stepId) === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(QuestionFormType::class);
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
}
