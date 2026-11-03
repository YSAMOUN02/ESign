<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
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

        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetTitle($this->latin1($document->title));

        $pageCount = $pdf->setSourceFile($disk->path($document->original_path));
        $fieldsByPage = $document->fields->groupBy('page');

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
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
        $disk->put($path, $pdf->Output('S'));

        return $path;
    }

    protected function placeImage(Fpdi $pdf, string $file, float $x, float $y, float $w, float $h): void
    {
        [$iw, $ih] = getimagesize($file);
        $scale = min($w / $iw, $h / $ih);
        $dw = $iw * $scale;
        $dh = $ih * $scale;
        $pdf->Image($file, $x + ($w - $dw) / 2, $y + ($h - $dh) / 2, $dw, $dh, 'PNG');
    }

    protected function placeText(Fpdi $pdf, string $text, float $x, float $y, float $w, float $h): void
    {
        $text = $this->latin1($text);
        // Font size in points from box height in mm (1pt = 0.3528mm), leaving some padding.
        $size = max(6, min(16, ($h / 0.3528) * 0.7));
        $pdf->SetFont('Helvetica', '', $size);
        while ($size > 5 && $pdf->GetStringWidth($text) > $w - 1) {
            $size -= 0.5;
            $pdf->SetFontSize($size);
        }
        $pdf->SetTextColor(20, 20, 60);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $text, 0, 0, 'L');
    }

    protected function appendCertificate(Fpdi $pdf, Document $document): void
    {
        $pdf->AddPage('P', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetXY(15, 15);
        $pdf->Cell(0, 10, 'Certificate of Completion', 0, 1);

        $pdf->SetFont('Helvetica', '', 9);
        $rows = [
            'Document' => $document->title,
            'Envelope ID' => 'DOC-'.str_pad((string) $document->id, 8, '0', STR_PAD_LEFT),
            'Sender' => $document->owner->name.' <'.$document->owner->email.'>',
            'Original file' => $document->original_name,
            'Original SHA-256' => $document->original_hash,
            'Sent' => optional($document->sent_at)->toDayDateTimeString().' UTC',
            'Completed' => now()->toDayDateTimeString().' UTC',
        ];
        foreach ($rows as $label => $value) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(35, 6, $label, 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 6, $this->latin1((string) $value), 0, 1);
        }

        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Signers', 0, 1);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(235, 238, 245);
        foreach ([['Name', 45], ['Email', 55], ['Signed at (UTC)', 40], ['IP address', 40]] as [$h, $w]) {
            $pdf->Cell($w, 6, $h, 1, 0, 'L', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 8);
        foreach ($document->signers as $signer) {
            $pdf->Cell(45, 6, $this->latin1($signer->name), 1);
            $pdf->Cell(55, 6, $this->latin1($signer->email), 1);
            $pdf->Cell(40, 6, optional($signer->signed_at)->format('Y-m-d H:i:s') ?? '-', 1);
            $pdf->Cell(40, 6, $signer->ip_address ?? '-', 1);
            $pdf->Ln();
        }

        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Audit trail', 0, 1);
        foreach ($document->auditLogs as $log) {
            if ($pdf->GetY() > 275) {
                $pdf->AddPage('P', 'A4');
            }
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell(35, 5, $log->created_at->format('Y-m-d H:i:s'), 0, 0);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell(0, 5, $this->latin1($log->description.($log->ip_address ? '  (IP '.$log->ip_address.')' : '')));
        }

        $pdf->SetY(-20);
        $pdf->SetFont('Helvetica', 'I', 7);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell(0, 4, 'This certificate was generated by '.$this->latin1(config('app.name')).'. Electronic signatures were captured with the consent of each signer.');
    }

    protected function latin1(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);

        return $converted === false ? $text : $converted;
    }
}
