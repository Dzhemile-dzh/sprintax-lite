<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Application\Questionnaire\WriteQuestionnaire;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\Repository\QuestionnaireRepositoryInterface;
use App\Domain\Questionnaire\ValueObject\FormType;
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
        private readonly QuestionnaireRepositoryInterface $questionnaires,
        private readonly WriteQuestionnaire $writeQuestionnaire,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('', name: 'admin_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/questionnaire/index.html.twig', [
            'questionnaires' => $this->questionnaires->all(),
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
                    $questionnaire = $this->writeQuestionnaire->create($name, $formType, $description);

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
        try {
            $loaded = $this->getQuestionnaire->execute($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        $questionnaire = $loaded->questionnaire;
        $form = $this->createForm(QuestionnaireFormType::class, [
            'name' => $questionnaire->name(),
            'formType' => $questionnaire->formType(),
            'description' => $questionnaire->description(),
        ], [
            'form_type_locked' => $loaded->formTypeLocked,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $formType = $form->get('formType')->getData() ?? $questionnaire->formType();
            $description = $form->get('description')->getData();

            if (is_string($name) && $formType instanceof FormType && ($description === null || is_string($description))) {
                try {
                    $this->writeQuestionnaire->update($id, $name, $description, $formType);

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
        return $this->render('admin/questionnaire/preview.html.twig', [
            'questionnaire' => $this->questionnaire($id),
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
