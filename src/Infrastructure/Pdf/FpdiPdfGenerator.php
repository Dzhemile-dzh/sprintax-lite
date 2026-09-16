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
            $this->assertPagesInRange($request->mappedPages, $pageCount);
            $fieldsByPage = $this->fieldsByPage($request->fields, $pageCount);

            for ($pageNo = 1; $pageNo <= $pageCount; ++$pageNo) {
                $templateId = $pdf->importPage($pageNo);
                $size = $this->pageSize($pdf->getTemplateSize($templateId), $pageNo, $request->sourcePdfPath);

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
     * @return array{orientation: string, width: float, height: float}
     */
    private function pageSize(mixed $size, int $pageNo, string $sourcePdfPath): array
    {
        if (!is_array($size)) {
            throw $this->unreadablePageSize($sourcePdfPath, $pageNo);
        }

        $orientation = $size['orientation'] ?? null;
        $width = $size['width'] ?? null;
        $height = $size['height'] ?? null;
        $hasWidth = is_int($width) || is_float($width);
        $hasHeight = is_int($height) || is_float($height);

        if (!is_string($orientation) || $orientation === '' || !$hasWidth || !$hasHeight) {
            throw $this->unreadablePageSize($sourcePdfPath, $pageNo);
        }

        return [
            'orientation' => $orientation,
            'width' => (float) $width,
            'height' => (float) $height,
        ];
    }

    private function unreadablePageSize(string $sourcePdfPath, int $pageNo): PdfGenerationFailed
    {
        return PdfGenerationFailed::overlayFailed(
            $sourcePdfPath,
            new RuntimeException(sprintf('Could not read size for page %d.', $pageNo)),
        );
    }

    /**
     * @param list<int> $pages
     */
    private function assertPagesInRange(array $pages, int $pageCount): void
    {
        foreach ($pages as $page) {
            $this->assertPageInRange($page, $pageCount);
        }
    }

    private function assertPageInRange(int $page, int $pageCount): void
    {
        if ($page < 1 || $page > $pageCount) {
            throw PdfGenerationFailed::pageOutOfRange($page, $pageCount);
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
            $this->assertPageInRange($page, $pageCount);
            $fieldsByPage[$page][] = $field;
        }

        return $fieldsByPage;
    }

    private function drawField(Fpdi $pdf, PdfFieldPlacement $placement, string $value): void
    {
        $fontSize = $placement->fontSize ?? self::DEFAULT_FONT_SIZE;
        $pdf->SetFont('Helvetica', $this->isCheckboxMark($value) ? 'B' : '', $fontSize);

        if ($this->isCheckboxMark($value)) {
            $this->drawCheckboxMark($pdf, $placement, $value, $fontSize);

            return;
        }

        $this->drawText($pdf, $placement, $value);
    }

    private function drawText(Fpdi $pdf, PdfFieldPlacement $placement, string $value): void
    {
        $encoded = $this->encode($value);
        $xMm = $placement->xMm;
        $yMm = $placement->yMm;
        $fontSize = $placement->fontSize ?? self::DEFAULT_FONT_SIZE;
        $fontHeightMm = $fontSize * 25.4 / 72.0;

        // Amount overlays use mapped X as the right edge of the IRS amount column.
        // Mapped Y is the amount-row content band; FPDF Text() uses the glyph baseline,
        // so shift down so digits sit on the form line instead of floating above it.
        if ($this->isAmountValue($value)) {
            $xMm -= $pdf->GetStringWidth($encoded);
            $yMm += $fontHeightMm * 0.75;
        }

        $pdf->Text($xMm, $yMm, $encoded);
    }

    private function isCheckboxMark(string $value): bool
    {
        return preg_match('/^[Xx✓✗]$/u', $value) === 1;
    }

    private function isAmountValue(string $value): bool
    {
        return preg_match('/^-?\d+\.\d{2}$/', $value) === 1;
    }

    private function drawCheckboxMark(
        Fpdi $pdf,
        PdfFieldPlacement $placement,
        string $value,
        int $fontSize,
    ): void {
        $encoded = $this->encode($value);
        $width = $pdf->GetStringWidth($encoded);
        $fontHeightMm = $fontSize * 25.4 / 72.0;

        // Mapped coordinates are the checkbox center (picker click). FPDF Text() uses
        // the glyph baseline and left edge, so center the mark inside the box.
        $pdf->Text(
            $placement->xMm - ($width / 2),
            $placement->yMm + ($fontHeightMm * 0.25),
            $encoded,
        );
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
