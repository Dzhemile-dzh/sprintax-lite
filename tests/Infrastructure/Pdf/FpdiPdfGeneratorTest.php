<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Pdf;

use App\Domain\Pdf\DTO\PdfFieldPlacement;
use App\Domain\Pdf\DTO\PdfGenerationRequest;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Infrastructure\Filesystem\LocalFileStorage;
use App\Infrastructure\Pdf\FpdiPdfGenerator;
use FPDF;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;

final class FpdiPdfGeneratorTest extends TestCase
{
    public function testItImportsTemplatePagesAndOverlaysTextAtRequestCoordinates(): void
    {
        $source = $this->blankPdf(2);
        $output = $this->tempFile('out');

        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);
        $generator->generate(new PdfGenerationRequest($source, $output, [
            [
                'placement' => new PdfFieldPlacement(1, 20.5, 40.25, 11),
                'value' => 'Ada Lovelace',
            ],
            [
                'placement' => new PdfFieldPlacement(2, 100.0, 180.5, 9, amountColumn: true),
                'value' => '4000.00',
            ],
        ]));

        self::assertFileExists($output);
        self::assertGreaterThan(0, filesize($output));

        $reader = new Fpdi();
        self::assertSame(2, $reader->setSourceFile($output));

        $contents = file_get_contents($output);
        self::assertNotFalse($contents);
        self::assertStringContainsString('Ada Lovelace', $contents);
        self::assertStringContainsString('4000.00', $contents);
    }

    public function testItRightAlignsAmountColumnPlacementsOnTheMappedX(): void
    {
        $source = $this->blankPdf(1);
        $output = $this->tempFile('amount-align');

        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);
        $generator->generate(new PdfGenerationRequest($source, $output, [
            [
                'placement' => new PdfFieldPlacement(1, 202.5, 143.0, 9, amountColumn: true),
                'value' => '123.00',
            ],
            [
                'placement' => new PdfFieldPlacement(1, 14.0, 42.5, 9),
                'value' => 'Ada',
            ],
        ]));

        self::assertFileExists($output);
        $contents = file_get_contents($output);
        self::assertNotFalse($contents);
        self::assertStringContainsString('123.00', $contents);
        self::assertStringContainsString('Ada', $contents);
    }

    public function testItDoesNotRightAlignMoneyShapedValuesWithoutAmountColumn(): void
    {
        $source = $this->blankPdf(1);
        $aligned = $this->tempFile('amount-aligned');
        $plain = $this->tempFile('amount-plain');
        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);

        $generator->generate(new PdfGenerationRequest($source, $aligned, [
            [
                'placement' => new PdfFieldPlacement(1, 202.5, 143.0, 9, amountColumn: true),
                'value' => '123.00',
            ],
        ]));
        $generator->generate(new PdfGenerationRequest($source, $plain, [
            [
                'placement' => new PdfFieldPlacement(1, 202.5, 143.0, 9, amountColumn: false),
                'value' => '123.00',
            ],
        ]));

        $alignedContents = file_get_contents($aligned);
        $plainContents = file_get_contents($plain);
        self::assertNotFalse($alignedContents);
        self::assertNotFalse($plainContents);
        self::assertNotSame(
            $alignedContents,
            $plainContents,
            'amountColumn:false must keep left-edge placement even for *.00-shaped values.',
        );

        // FPDF writes mm coordinates in points (202.5 mm → 574.02).
        self::assertMatchesRegularExpression('/574\.02\s+\d+\.\d+\s+Td\s+\(123\.00\)/', $plainContents);
        self::assertDoesNotMatchRegularExpression('/574\.02\s+\d+\.\d+\s+Td\s+\(123\.00\)/', $alignedContents);
    }

    public function testItCentersCheckboxMarksOnMappedCoordinates(): void
    {
        $source = $this->blankPdf(1);
        $output = $this->tempFile('checkbox-mark');

        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);
        $generator->generate(new PdfGenerationRequest($source, $output, [
            [
                'placement' => new PdfFieldPlacement(1, 202.5, 143.0, 9, amountColumn: true),
                'value' => '3000.00',
            ],
            [
                'placement' => new PdfFieldPlacement(1, 38.2, 71.5, 9),
                'value' => 'X',
            ],
        ]));

        self::assertFileExists($output);
        $contents = file_get_contents($output);
        self::assertNotFalse($contents);
        self::assertStringContainsString('3000.00', $contents);
        self::assertStringContainsString('X', $contents);
    }

    public function testItFailsWhenTheSourcePdfCannotBeRead(): void
    {
        $generator = new FpdiPdfGenerator(new LocalFileStorage());

        $this->expectException(PdfGenerationFailed::class);
        $generator->generate(new PdfGenerationRequest(
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-'.uniqid('', true).'.pdf',
            sys_get_temp_dir().DIRECTORY_SEPARATOR.'out-'.uniqid('', true).'.pdf',
            [],
        ));
    }

    public function testItFailsWhenAMappingPageIsOutsideTheTemplate(): void
    {
        $source = $this->blankPdf(1);
        $output = $this->tempFile('out');
        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);

        try {
            $generator->generate(new PdfGenerationRequest($source, $output, [
                [
                    'placement' => new PdfFieldPlacement(1, 20.0, 30.0, 11),
                    'value' => 'on page',
                ],
                [
                    'placement' => new PdfFieldPlacement(3, 10.0, 10.0),
                    'value' => 'too far',
                ],
            ]));
            self::fail('Expected PDF generation to fail when a mapping page is outside the template.');
        } catch (PdfGenerationFailed $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame(
                'PDF mapping page 3 is outside the template (1 pages).',
                $exception->getMessage(),
            );
            self::assertFileDoesNotExist($output);
        }
    }

    public function testItFailsWhenAMappedPageIsOutsideTheTemplateEvenWithoutAValue(): void
    {
        $source = $this->blankPdf(2);
        $output = $this->tempFile('out');
        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);

        try {
            $generator->generate(new PdfGenerationRequest(
                $source,
                $output,
                [],
                [1, 3],
            ));
            self::fail('Expected PDF generation to fail when a mapping page is outside the template.');
        } catch (PdfGenerationFailed $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame(
                'PDF mapping page 3 is outside the template (2 pages).',
                $exception->getMessage(),
            );
            self::assertFileDoesNotExist($output);
        }
    }

    public function testItCreatesTheOutputDirectory(): void
    {
        $source = $this->blankPdf(1);
        $output = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'sprintax-fpdi-nested-'.uniqid('', true)
            .DIRECTORY_SEPARATOR.'out.pdf';

        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);
        $generator->generate(new PdfGenerationRequest($source, $output, []));

        self::assertFileExists($output);
    }

    private function blankPdf(int $pages): string
    {
        $path = $this->tempFile('src');
        $pdf = new FPDF('P', 'mm', 'A4');

        for ($page = 0; $page < $pages; ++$page) {
            $pdf->AddPage();
        }

        $pdf->Output('F', $path);

        return $path;
    }

    private function tempFile(string $prefix): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'sprintax-fpdi-'.$prefix.'-'.uniqid('', true).'.pdf';
    }
}
