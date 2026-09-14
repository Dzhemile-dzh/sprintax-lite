<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\Create\CreateQuestionnaire;
use App\Application\Questionnaire\List\ListQuestionnaires;
use App\Application\Questionnaire\Preview\PreviewQuestionnaire;
use App\Application\Questionnaire\Update\UpdateQuestionnaire;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Presentation\Admin\Form\QuestionnaireFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class QuestionnaireController extends AbstractController
{
    public function __construct(
        private readonly ListQuestionnaires $listQuestionnaires,
        private readonly CreateQuestionnaire $createQuestionnaire,
        private readonly UpdateQuestionnaire $updateQuestionnaire,
        private readonly PreviewQuestionnaire $previewQuestionnaire,
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly SubmissionRepositoryInterface $submissions,
    ) {
    }

    #[Route('', name: 'admin_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/questionnaire/index.html.twig', [
            'questionnaires' => $this->listQuestionnaires->execute(),
        ]);
    }

    #[Route('/questionnaires/new', name: 'admin_questionnaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(QuestionnaireFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $formType = $form->get('formType')->getData();
            $description = $form->get('description')->getData();

            if (is_string($name) && $formType instanceof FormType && ($description === null || is_string($description))) {
                try {
                    $questionnaire = $this->createQuestionnaire->execute($name, $formType, $description);

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $questionnaire->id()]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/questionnaire/form.html.twig', [
            'form' => $form,
            'title' => 'New questionnaire',
        ]);
    }

    #[Route('/questionnaires/{id}', name: 'admin_questionnaire_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        return $this->render('admin/questionnaire/show.html.twig', [
            'questionnaire' => $this->questionnaire($id),
        ]);
    }

    #[Route('/questionnaires/{id}/edit', name: 'admin_questionnaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $questionnaire = $this->questionnaire($id);
        $form = $this->createForm(QuestionnaireFormType::class, [
            'name' => $questionnaire->name(),
            'formType' => $questionnaire->formType(),
            'description' => $questionnaire->description(),
        ], [
            'form_type_locked' => $this->submissions->existsForQuestionnaire($id),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $formType = $form->get('formType')->getData() ?? $questionnaire->formType();
            $description = $form->get('description')->getData();

            if (is_string($name) && $formType instanceof FormType && ($description === null || is_string($description))) {
                try {
                    $this->updateQuestionnaire->execute($id, $name, $description, $formType);

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/questionnaire/form.html.twig', [
            'form' => $form,
            'title' => 'Edit questionnaire',
        ]);
    }

    #[Route('/questionnaires/{id}/preview', name: 'admin_questionnaire_preview', methods: ['GET'])]
    public function preview(string $id): Response
    {
        try {
            $questionnaire = $this->previewQuestionnaire->execute($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/questionnaire/preview.html.twig', [
            'questionnaire' => $questionnaire,
        ]);
    }

    private function questionnaire(string $id): Questionnaire
    {
        try {
            return $this->questionnaires->get($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }
    }
}
