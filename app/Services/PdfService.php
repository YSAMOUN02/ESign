<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Throwable;

class PdfService
{
    /**
     * Ensure the PDF can be parsed by FPDI and return its page count.
     * If parsing fails and Ghostscript is configured, the file is rewritten
     * as PDF 1.4 in place and parsed again.
     */
    public function prepare(string $absolutePath): int
    {
        try {
            return $this->countPages($absolutePath);
        } catch (Throwable $e) {
            if (! $this->downgrade($absolutePath)) {
                throw new RuntimeException(
                    'This PDF uses a compression format the free PDF parser cannot read. '.
                    'Re-save it as PDF 1.4 (e.g. "Print → Save as PDF") or install Ghostscript and set GHOSTSCRIPT_PATH.',
                    previous: $e
                );
            }

            return $this->countPages($absolutePath);
        }
    }

    public function countPages(string $absolutePath): int
    {
        return (new Fpdi)->setSourceFile($absolutePath);
    }

    protected function downgrade(string $absolutePath): bool
    {
        $gs = config('esign.ghostscript_path');
        if (! $gs || ! is_executable($gs)) {
            return false;
        }

        $tmp = $absolutePath.'.gs.pdf';
        $process = new Process([$gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-sOutputFile='.$tmp, $absolutePath]);
        $process->setTimeout(120)->run();

        if (! $process->isSuccessful() || ! is_file($tmp)) {
            @unlink($tmp);

            return false;
        }

        return rename($tmp, $absolutePath);
    }

    /**
     * Stamp every filled field onto the original PDF, append an audit
     * certificate page, store the result and return its storage path.
     */
    public function buildSignedPdf(Document $document): string
    {
        $disk = Storage::disk('local');
        $document->loadMissing(['fields.signer', 'signers', 'auditLogs.signer', 'owner']);

        // mPDF is used here because it shapes Khmer text correctly; FPDF cannot.
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'tempDir' => storage_path('framework/cache/mpdf'),
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetTitle($document->title);
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($disk->path($document->original_path));
        $fieldsByPage = $document->fields->groupBy('page');

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);
            // mPDF swaps the two sides itself for landscape, so it always wants short side first.
            $pdf->AddPageByArray([
                'orientation' => $size['orientation'],
                'sheet-size' => [min($size['width'], $size['height']), max($size['width'], $size['height'])],
                'mgl' => 0, 'mgr' => 0, 'mgt' => 0, 'mgb' => 0, 'mgh' => 0, 'mgf' => 0,
            ]);
            $pdf->useTemplate($tpl);

            foreach ($fieldsByPage->get($pageNo, []) as $field) {
                if ($field->value === null || $field->value === '') {
                    continue;
                }

                $x = $field->x * $size['width'];
                $y = $field->y * $size['height'];
                $w = $field->width * $size['width'];
                $h = $field->height * $size['height'];

                if ($field->isImage()) {
                    $this->placeImage($pdf, $disk->path($field->value), $x, $y, $w, $h);
                } else {
                    $this->placeText($pdf, $field->value, $x, $y, $w, $h);
                }
            }
        }

        $this->appendCertificate($pdf, $document);

        $path = 'documents/signed/'.$document->id.'-'.now()->format('YmdHis').'.pdf';
        $disk->put($path, $pdf->Output('', Destination::STRING_RETURN));

        return $path;
    }

    protected function placeImage(Mpdf $pdf, string $file, float $x, float $y, float $w, float $h): void
    {
        [$iw, $ih] = getimagesize($file);
        $scale = min($w / $iw, $h / $ih);
        $dw = $iw * $scale;
        $dh = $ih * $scale;
        $pdf->Image($file, $x + ($w - $dw) / 2, $y + ($h - $dh) / 2, $dw, $dh, 'png', '', true, false);
    }

    protected function placeText(Mpdf $pdf, string $text, float $x, float $y, float $w, float $h): void
    {
        // Font size in points from box height in mm (1pt = 0.3528mm), leaving some padding.
        $size = max(6, min(16, ($h / 0.3528) * 0.7));
        // Keeps one line of text in the vertical middle of a tall box.
        $offset = max(0, ($h - $size * 0.3528 * 1.4) / 2);
        // Signers type this text, so it must never be read as HTML.
        $pdf->WriteFixedPosHTML('<div style="color:#14143c;font-size:'.$size.'pt">'.e($text).'</div>', $x, $y + $offset, $w, $h - $offset, 'auto');
    }

    protected function appendCertificate(Mpdf $pdf, Document $document): void
    {
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPageByArray([
            'orientation' => 'P',
            'sheet-size' => 'A4',
            'mgl' => 15, 'mgr' => 15, 'mgt' => 15, 'mgb' => 15, 'mgh' => 0, 'mgf' => 0,
        ]);
        $pdf->WriteHTML(view('pdf.certificate', ['document' => $document])->render());
    }
}
