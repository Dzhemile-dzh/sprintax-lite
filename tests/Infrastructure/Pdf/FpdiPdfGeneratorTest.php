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
                'placement' => new PdfFieldPlacement(2, 100.0, 180.5, 9),
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

    public function testItSkipsFieldsWhosePageIsOutsideTheTemplate(): void
    {
        $source = $this->blankPdf(1);
        $output = $this->tempFile('out');
        $generator = new FpdiPdfGenerator(new LocalFileStorage(), compressStreams: false);

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

        $contents = file_get_contents($output);
        self::assertNotFalse($contents);
        self::assertStringContainsString('on page', $contents);
        self::assertStringNotContainsString('too far', $contents);
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
