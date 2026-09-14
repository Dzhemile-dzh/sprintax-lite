<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Filesystem\Contract\FileStorageInterface;
use App\Domain\Pdf\Contract\PdfGeneratorInterface;
use App\Domain\Pdf\DTO\PdfFieldPlacement;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Overlays values onto a source PDF using coordinates from the request.
 * Questionnaire field positions must never be hardcoded here.
 */
final class FpdiPdfGenerator implements PdfGeneratorInterface
{
    private const DEFAULT_FONT_SIZE = 10;

    public function __construct(
        private readonly FileStorageInterface $fileStorage,
        private readonly bool $compressStreams = true,
    ) {
    }

    public function generate(PdfGenerationRequest $request): void
    {
        if (!$this->fileStorage->isReadable($request->sourcePdfPath)) {
            throw PdfGenerationFailed::sourceUnreadable($request->sourcePdfPath);
        }

        try {
            $this->fileStorage->ensureDirectory(dirname($request->outputPath));
        } catch (Throwable $exception) {
            throw PdfGenerationFailed::writeFailed($request->outputPath, $exception);
        }

        try {
            $pdf = new Fpdi('P', 'mm');
            $pdf->SetCompression($this->compressStreams);
            $pageCount = $pdf->setSourceFile($request->sourcePdfPath);
            $fieldsByPage = $this->fieldsByPage($request->fields, $pageCount);

            for ($pageNo = 1; $pageNo <= $pageCount; ++$pageNo) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                if ($size === false) {
                    throw PdfGenerationFailed::overlayFailed(
                        $request->sourcePdfPath,
                        new RuntimeException(sprintf('Could not read size for page %d.', $pageNo)),
                    );
                }

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                foreach ($fieldsByPage[$pageNo] ?? [] as $field) {
                    $this->drawField($pdf, $field['placement'], $field['value']);
                }
            }

            $pdf->Output('F', $request->outputPath);
        } catch (PdfGenerationFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw PdfGenerationFailed::overlayFailed($request->sourcePdfPath, $exception);
        }
    }

    /**
     * @param list<array{placement: PdfFieldPlacement, value: string}> $fields
     *
     * @return array<int, list<array{placement: PdfFieldPlacement, value: string}>>
     */
    private function fieldsByPage(array $fields, int $pageCount): array
    {
        $fieldsByPage = [];

        foreach ($fields as $field) {
            $page = $field['placement']->page;

            if ($page > $pageCount) {
                throw PdfGenerationFailed::pageOutOfRange($page, $pageCount);
            }

            $fieldsByPage[$page][] = $field;
        }

        return $fieldsByPage;
    }

    private function drawField(Fpdi $pdf, PdfFieldPlacement $placement, string $value): void
    {
        $fontSize = $placement->fontSize ?? self::DEFAULT_FONT_SIZE;
        $pdf->SetFont('Helvetica', '', $fontSize);
        $pdf->Text($placement->xMm, $placement->yMm, $this->encode($value));
    }

    private function encode(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

        if ($encoded === false) {
            return $value;
        }

        return $encoded;
    }
}
