<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Application\Questionnaire\AddMapping\AddQuestionMapping;
use App\Application\Questionnaire\Get\GetQuestionnaire;
use App\Domain\Questionnaire\Exception\InvalidQuestionnaire;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\ValueObject\MappingSourceType;
use App\Presentation\Admin\Form\MappingFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
        private readonly GetQuestionnaire $getQuestionnaire,
    ) {
    }

    #[Route('/questionnaires/{id}/mappings/new', name: 'admin_mapping_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $id): Response
    {
        try {
            $this->getQuestionnaire->execute($id);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(MappingFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sourceType = $form->get('sourceType')->getData();
            $sourceReference = $form->get('sourceReference')->getData();
            $page = $form->get('page')->getData();
            $xMm = $form->get('xMm')->getData();
            $yMm = $form->get('yMm')->getData();
            $fontSize = $form->get('fontSize')->getData();

            if (
                $sourceType instanceof MappingSourceType
                && is_string($sourceReference)
                && is_int($page)
                && (is_int($xMm) || is_float($xMm) || is_numeric($xMm))
                && (is_int($yMm) || is_float($yMm) || is_numeric($yMm))
                && ($fontSize === null || is_int($fontSize))
            ) {
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

                    return $this->redirectToRoute('admin_questionnaire_show', ['id' => $id]);
                } catch (InvalidQuestionnaire $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }

        return $this->render('admin/structure/form.html.twig', [
            'form' => $form,
            'title' => 'Add PDF mapping',
        ]);
    }
}
