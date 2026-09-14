<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Calculation\ListCalculatorFields;
use App\Application\Questionnaire\AddMapping\AddQuestionMapping;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Application\Questionnaire\RemoveMapping\RemoveQuestionMapping;
use App\Application\Questionnaire\UpdateMapping\UpdateQuestionMapping;
use App\Domain\Questionnaire\Entity\QuestionMapping;
use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Presentation\Admin\Form\MappingFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class MappingController extends AbstractController
{
    public function __construct(
        private readonly AddQuestionMapping $addQuestionMapping,
        private readonly UpdateQuestionMapping $updateQuestionMapping,
        private readonly RemoveQuestionMapping $removeQuestionMapping,
        private readonly ListCalculatorFields $calculatorFields,
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/mappings/new', name: 'admin_mapping_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id): Response
    {
        $questionnaire = $this->questionnaire($id);
        $form = $this->createForm(MappingFormType::class, null, $this->mappingChoices($questionnaire));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->saveNew($form, $id)) {
            return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Add PDF mapping',
        ]);
    }

    #[Route('/questionnaires/{id}/mappings/{mappingId}/edit', name: 'admin_mapping_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, string $mappingId): Response
    {
        $questionnaire = $this->questionnaire($id);
        $mapping = $this->mapping($questionnaire, $mappingId);
        $source = $mapping->source();
        $form = $this->createForm(MappingFormType::class, [
            'sourceType' => $source->type,
            'questionKey' => $source->isQuestion() ? $source->reference : null,
            'computedField' => $source->isQuestion() ? null : $source->reference,
            'page' => $mapping->coordinates()->page,
            'xMm' => $mapping->coordinates()->xMm,
            'yMm' => $mapping->coordinates()->yMm,
            'fontSize' => $mapping->coordinates()->fontSize,
        ], [
            ...$this->mappingChoices($questionnaire),
            'lock_source' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $page = $form->get('page')->getData();
            $xMm = $form->get('xMm')->getData();
            $yMm = $form->get('yMm')->getData();
            $fontSize = $form->get('fontSize')->getData();

            if (
                is_int($page)
                && (is_int($xMm) || is_float($xMm) || is_numeric($xMm))
                && (is_int($yMm) || is_float($yMm) || is_numeric($yMm))
                && ($fontSize === null || is_int($fontSize))
            ) {
                try {
                    $this->updateQuestionMapping->execute(
                        $id,
                        $mappingId,
                        $page,
                        (float) $xMm,
                        (float) $yMm,
                        $fontSize,
                    );

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Edit PDF mapping',
        ]);
    }

    #[Route('/questionnaires/{id}/mappings/{mappingId}/delete', name: 'admin_mapping_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, string $mappingId): Response
    {
        $this->mapping($this->questionnaire($id), $mappingId);

        if (!$this->isCsrfTokenValid('delete-mapping-'.$mappingId, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->removeQuestionMapping->execute($id, $mappingId);
        } catch (InvalidQuestionnaire $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function saveNew(FormInterface $form, string $id): bool
    {
        $sourceType = $form->get('sourceType')->getData();
        $sourceReference = $sourceType === MappingSourceType::Question
            ? $form->get('questionKey')->getData()
            : $form->get('computedField')->getData();
        $page = $form->get('page')->getData();
        $xMm = $form->get('xMm')->getData();
        $yMm = $form->get('yMm')->getData();
        $fontSize = $form->get('fontSize')->getData();

        if (
            !$sourceType instanceof MappingSourceType
            || !is_string($sourceReference)
            || !is_int($page)
            || !(is_int($xMm) || is_float($xMm) || is_numeric($xMm))
            || !(is_int($yMm) || is_float($yMm) || is_numeric($yMm))
            || !($fontSize === null || is_int($fontSize))
        ) {
            return false;
        }

        try {
            $this->addQuestionMapping->execute(
                $id,
                $sourceType,
                $sourceReference,
                $page,
                (float) $xMm,
                (float) $yMm,
                $fontSize,
            );
        } catch (InvalidQuestionnaire $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * @return array{question_keys: array<string, string>, computed_fields: array<string, string>}
     */
    private function mappingChoices(Questionnaire $questionnaire): array
    {
        return [
            'question_keys' => QuestionnaireFormChoices::questionKeys($questionnaire),
            'computed_fields' => QuestionnaireFormChoices::computedFields(
                $this->calculatorFields->execute($questionnaire->formType()),
            ),
        ];
    }

    private function mapping(Questionnaire $questionnaire, string $mappingId): QuestionMapping
    {
        $mapping = $questionnaire->findMapping($mappingId);

        if ($mapping === null) {
            throw $this->createNotFoundException();
        }

        return $mapping;
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
