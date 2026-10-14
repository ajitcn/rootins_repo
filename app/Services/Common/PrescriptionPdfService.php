<?php

namespace App\Services\Common;

class PrescriptionPdfService
{
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    private const LEFT = 42;

    private const RIGHT = 553;

    public function generate(array $prescription, array $items): string
    {
        $pages = [];
        $page = [];
        $y = 52;
        $this->startPage($page, $prescription, $y);
        $this->line($page, self::LEFT, $y, self::RIGHT, $y, [190, 190, 190]);
        $y += 22;
        $this->text($page, self::LEFT, $y, 'PRESCRIPTION', 16, true, [31, 78, 121]);
        $this->text($page, 390, $y, 'No. ' . ($prescription['prescription_number'] ?? ''), 10, true);
        $y += 25;
        $this->box($page, self::LEFT, $y, self::RIGHT, 86, [245, 248, 251], [210, 218, 226]);
        $this->text($page, 54, $y + 18, 'PATIENT', 8, true, [90, 90, 90]);
        $patientName = $this->fullName($prescription, 'patient_');
        $this->text($page, 54, $y + 34, $patientName, 11, true);
        $this->text($page, 54, $y + 54, 'Registration No.: ' . ($prescription['registration_number'] ?? '—'), 9);
        $this->text($page, 340, $y + 18, 'DOCTOR', 8, true, [90, 90, 90]);
        $doctorName = $this->fullName($prescription, 'doctor_');
        $this->text($page, 340, $y + 34, $doctorName, 11, true);
        $doctorMeta = array_filter([$prescription['qualification'] ?? null, !empty($prescription['doctor_code']) ? 'Code: ' . $prescription['doctor_code'] : null]);
        $this->text($page, 340, $y + 52, implode(' | ', $doctorMeta) ?: '—', 9);
        $this->text($page, 340, $y + 68, 'Date: ' . ($prescription['prescription_date'] ?? '—'), 9);
        $y += 110;
        $this->text($page, self::LEFT, $y, 'MEDICINES', 10, true, [31, 78, 121]);
        $y += 17;

        foreach ($items as $number => $item) {
            $lines = $this->itemLines($item, $number + 1);
            $height = 18 + count($lines) * 14 + 10;

            if ($y + $height > 770) {
                $pages[] = $page;
                $page = [];
                $y = 52;
                $this->startPage($page, $prescription, $y);
                $this->text($page, self::LEFT, $y, 'MEDICINES (CONTINUED)', 10, true, [31, 78, 121]);
                $y += 20;
            }
            $this->box($page, self::LEFT, $y, self::RIGHT, $height, [255, 255, 255], [222, 226, 230]);
            $this->text($page, 54, $y + 17, $lines[0], 10, true);

            foreach (array_slice($lines, 1) as $offset => $line) {
                $this->text($page, 54, $y + 34 + $offset * 14, $line, 9);
            }
            $y += $height + 9;
        }

        if (!empty($prescription['notes'])) {
            if ($y + 48 > 770) {
                $pages[] = $page;
                $page = [];
                $y = 52;
                $this->startPage($page, $prescription, $y);
            }
            $this->text($page, self::LEFT, $y, 'PRESCRIPTION NOTES', 10, true, [31, 78, 121]);
            $y += 17;

            foreach ($this->wrap((string) $prescription['notes'], 92) as $line) {
                $this->text($page, self::LEFT, $y, $line, 9);
                $y += 13;
            }
        }
        $this->footer($page);
        $pages[] = $page;

        return $this->buildPdf($pages);
    }

    private function startPage(array &$page, array $prescription, int &$y): void
    {
        $clinic = $prescription['clinic_name'] ?? 'ROOTINS Clinic';
        $this->text($page, self::LEFT, $y, $clinic, 16, true, [31, 78, 121]);
        $y += 19;
        $clinicLine = array_filter([$prescription['clinic_address'] ?? null, !empty($prescription['clinic_pincode']) ? 'PIN: ' . $prescription['clinic_pincode'] : null]);
        $this->text($page, self::LEFT, $y, implode(' | ', $clinicLine) ?: '—', 8);
        $y += 13;
        $contact = array_filter([!empty($prescription['clinic_mobile']) ? 'Tel: ' . $prescription['clinic_mobile'] : null, !empty($prescription['clinic_email']) ? $prescription['clinic_email'] : null]);
        $this->text($page, self::LEFT, $y, implode(' | ', $contact), 8);
        $y += 16;
    }

    private function itemLines(array $item, int $number): array
    {
        $duration = trim(($item['duration'] ?? '') . ' ' . ($item['duration_unit'] ?? ''));
        $lines = [$number . '. ' . ($item['medicine_name'] ?? ''), 'Dosage: ' . ($item['dosage'] ?: '—') . '    Frequency: ' . ($item['frequency'] ?: '—'), 'Duration: ' . ($duration ?: '—') . '    Quantity: ' . ($item['quantity'] ?? '—')];

        foreach ($this->wrap('Instructions: ' . ($item['instructions'] ?: '—'), 92) as $line) {
            $lines[] = $line;
        }

        if (!empty($item['notes'])) {
            foreach ($this->wrap('Notes: ' . $item['notes'], 92) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function footer(array &$page): void
    {
        $this->line($page, self::LEFT, 790, self::RIGHT, 790, [210, 210, 210]);
        $this->text($page, self::LEFT, 808, 'Generated by ROOTINS', 8, false, [110, 110, 110]);
        $this->text($page, 445, 808, 'For clinical use', 8, false, [110, 110, 110]);
    }

    private function box(array &$page, int $x1, int $yTop, int $x2, int $height, array $fill, array $stroke): void
    {
        $this->color($page, $fill, false);
        $this->color($page, $stroke, true);
        $page[] = sprintf('%.2F %.2F %.2F %.2F re B', $x1, self::PAGE_HEIGHT - $yTop - $height, $x2 - $x1, $height);
    }

    private function line(array &$page, int $x1, int $y1, int $x2, int $y2, array $color): void
    {
        $this->color($page, $color, true);
        $page[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x1, self::PAGE_HEIGHT - $y1, $x2, self::PAGE_HEIGHT - $y2);
    }

    private function text(array &$page, int $x, int $y, string $value, int $size, bool $bold = false, array $color = [35, 35, 35]): void
    {
        $font = $bold ? 'F2' : 'F1';
        $page[] = sprintf('BT /%s %d Tf %.3F %.3F %.3F rg 1 0 0 1 %d %d Tm (%s) Tj ET', $font, $size, $color[0] / 255, $color[1] / 255, $color[2] / 255, $x, self::PAGE_HEIGHT - $y, $this->pdfString($value));
    }

    private function color(array &$page, array $color, bool $stroke): void
    {
        $page[] = sprintf('%.3F %.3F %.3F %s', $color[0] / 255, $color[1] / 255, $color[2] / 255, $stroke ? 'RG' : 'rg');
    }

    private function wrap(string $value, int $width): array
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '—';
        $words = preg_split('/\s+/u', $value) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            if ($line !== '' && mb_strlen($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: ['—'];
    }

    private function fullName(array $data, string $prefix): string
    {
        return trim(implode(' ', array_filter([$data[$prefix . 'first_name'] ?? '', $data[$prefix . 'middle_name'] ?? '', $data[$prefix . 'last_name'] ?? ''], static fn ($part) => $part !== null && $part !== ''))) ?: '—';
    }

    private function pdfString(string $value): string
    {
        $value = str_replace(['—', '–', '’', '“', '”'], ['-', '-', "'", '"', '"'], $value);
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', '', ' '], $value);
    }

    private function buildPdf(array $pages): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objects[] = 'PLACEHOLDER';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($pages as $commands) {
            $content = implode("\n", $commands);
            $contentObject = count($objects) + 1;
            $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $pageObject = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $kids[] = $pageObject . ' 0 R';
        }
        $objects[1] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $number + 1 . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }
}
