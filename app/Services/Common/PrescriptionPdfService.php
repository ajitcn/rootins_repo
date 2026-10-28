<?php

namespace App\Services\Common;

/** Shared A4 prescription document generator for already-authorized requests. */
class PrescriptionPdfService
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const LEFT = 38;
    private const RIGHT = 557;
    private const CONTENT_BOTTOM = 768;

    public function generate(array $prescription, array $items): string
    {
        $pages = [];
        $page = [];
        $y = 42;
        $this->startPage($page, $prescription, $y);
        $this->rxIdentity($page, $prescription, $y);
        $this->fields($page, $y, [
            ['Prescription reference', $this->value($prescription['prescription_number'] ?? null)],
            ['Prescription date', $this->date($prescription['prescription_date'] ?? null)],
            ['Valid until', $this->date($prescription['valid_until'] ?? null)],
            ['Consultation', $this->value($prescription['consultation_number'] ?? null)],
            ['Appointment', $this->value($prescription['appointment_number'] ?? null)],
        ]);
        $this->section($page, 'PATIENT', $y);
        $this->fields($page, $y, [
            ['Name', $this->fullName($prescription, 'patient_')],
            ['Patient reference', $this->value($prescription['registration_number'] ?? null)],
            ['Age / Gender', $this->patientAgeGender($prescription)],
            ['Contact', $this->value($prescription['patient_mobile'] ?? $prescription['mobile_number'] ?? null)],
            ['Address', $this->value($prescription['patient_address'] ?? null)],
        ]);
        $this->section($page, 'MEDICINES', $y);
        $headers = ['No.', 'Medicine name', 'Strength / form', 'Dose', 'Route', 'Frequency', 'Duration', 'Qty', 'Instructions'];
        $widths = [26, 114, 67, 57, 47, 67, 52, 36, 53];
        $this->tableHeader($page, $y, $headers, $widths);
        if ($items === []) {
            $this->tableRow($page, $y, ['', 'No prescription medicines', '', '', '', '', '', '', ''], $widths, true);
        } else {
            foreach ($items as $number => $item) {
                $row = [
                    (string) ($number + 1),
                    $this->value($item['medicine_name'] ?? $item['medicine_catalog_name'] ?? null),
                    trim($this->value($item['strength'] ?? $item['medicine_strength'] ?? null) . ' ' . $this->value($item['dosage_form'] ?? $item['medicine_form'] ?? $item['form'] ?? null)),
                    $this->value($item['dosage'] ?? null),
                    $this->value($item['route'] ?? null),
                    $this->value($item['frequency'] ?? null),
                    trim($this->value($item['duration'] ?? null) . ' ' . $this->value($item['duration_unit'] ?? null)),
                    $this->value($item['quantity'] ?? null),
                    $this->value($item['instructions'] ?? $item['notes'] ?? null),
                ];
                $height = max(24, count($this->rowLines($row, $widths)) * 11 + 8);
                if ($y + $height > self::CONTENT_BOTTOM) {
                    $pages[] = $page;
                    $page = [];
                    $y = 42;
                    $this->startPage($page, $prescription, $y);
                    $this->section($page, 'MEDICINES (CONTINUED)', $y);
                    $this->tableHeader($page, $y, $headers, $widths);
                }
                $this->tableRow($page, $y, $row, $widths);
            }
        }
        $this->textSection($page, $y, 'DOCTOR INSTRUCTIONS', $prescription['instructions'] ?? null);
        $this->textSection($page, $y, 'DOCTOR NOTES', $prescription['notes'] ?? null);
        $followUp = trim(implode(' ', array_filter([$this->date($prescription['follow_up_date'] ?? null), $this->time($prescription['follow_up_time'] ?? null), $this->value($prescription['follow_up_remarks'] ?? null)], static fn ($value): bool => $value !== '')));
        $this->textSection($page, $y, 'FOLLOW-UP', $followUp !== '' ? $followUp : null);
        if ($y + 58 > self::CONTENT_BOTTOM) {
            $pages[] = $page;
            $page = [];
            $y = 42;
            $this->startPage($page, $prescription, $y);
        }
        $this->line($page, 390, $y + 35, self::RIGHT, $y + 35, [170, 170, 170]);
        $this->text($page, 390, $y + 50, 'Doctor signature', 8, false, [95, 95, 95]);
        $pages[] = $page;
        $total = count($pages);
        foreach ($pages as $index => &$commands) {
            $this->footer($commands, $index + 1, $total);
        }
        unset($commands);
        return $this->buildPdf($pages);
    }

    private function startPage(array &$page, array $prescription, int &$y): void
    {
        $this->box($page, self::LEFT, $y - 20, self::LEFT + 30, 30, [31, 78, 121], [31, 78, 121]);
        $this->text($page, self::LEFT + 8, $y + 1, 'R', 19, true, [255, 255, 255]);
        $this->text($page, 78, $y, 'ROOTINS', 27, true, [31, 78, 121]);
        $this->text($page, 225, $y - 1, 'Healthcare and Pharmacy', 13, true, [55, 65, 78]);
        $y += 22;
        $this->text($page, self::LEFT, $y, $this->value($prescription['clinic_name'] ?? null), 13, true, [31, 78, 121]);
        $y += 14;
        $address = implode(' | ', array_filter([$this->value($prescription['clinic_address'] ?? null), $this->value($prescription['clinic_pincode'] ?? null) !== '' ? 'PIN: ' . $this->value($prescription['clinic_pincode']) : null]));
        $contact = implode(' | ', array_filter([$this->value($prescription['clinic_mobile'] ?? null) !== '' ? 'Tel: ' . $this->value($prescription['clinic_mobile']) : null, $this->value($prescription['clinic_email'] ?? null) !== '' ? $this->value($prescription['clinic_email']) : null]));
        if ($address !== '') { $this->text($page, self::LEFT, $y, $address, 10); $y += 14; }
        if ($contact !== '') { $this->text($page, self::LEFT, $y, $contact, 10); $y += 14; }
        $doctorMeta = implode(' | ', array_filter([$this->value($prescription['qualification'] ?? null), $this->value($prescription['doctor_specializations'] ?? null), $this->value($prescription['doctor_registration_number'] ?? null) !== '' ? 'Reg. No.: ' . $this->value($prescription['doctor_registration_number']) : null, $this->value($prescription['consultation_date'] ?? null) !== '' ? 'Consultation: ' . $this->date($prescription['consultation_date']) : null]));
        $doctorLine = 'Dr. ' . $this->fullName($prescription, 'doctor_') . ($doctorMeta !== '' ? '  |  ' . $doctorMeta : '');
        $this->text($page, self::LEFT, $y, $doctorLine, 10, true);
        $y += 16;
        $this->line($page, self::LEFT, $y, self::RIGHT, $y, [190, 190, 190]);
        $y += 12;
    }

    private function rxIdentity(array &$page, array $prescription, int &$y): void
    {
        $this->box($page, self::LEFT, $y, self::RIGHT, 64, [239, 247, 252], [171, 204, 222]);
        $this->text($page, self::LEFT + 12, $y + 27, 'Rx', 25, true, [31, 78, 121]);
        $this->text($page, self::LEFT + 55, $y + 19, $this->value($prescription['prescription_number'] ?? null), 15, true, [31, 78, 121]);
        $this->text($page, self::LEFT + 55, $y + 39, 'Prescription', 9, false, [80, 95, 110]);
        $this->text($page, 310, $y + 18, 'Date', 8, true, [80, 95, 110]);
        $this->text($page, 350, $y + 18, $this->date($prescription['prescription_date'] ?? null), 10);
        $this->text($page, 310, $y + 39, 'Valid until', 8, true, [80, 95, 110]);
        $this->text($page, 350, $y + 39, $this->date($prescription['valid_until'] ?? null), 10);
        $y += 78;
    }

    private function section(array &$page, string $title, int &$y): void
    {
        $this->text($page, self::LEFT, $y, $title, 11, true, [31, 78, 121]);
        $y += 17;
    }

    private function fields(array &$page, int &$y, array $fields): void
    {
        $height = (int) ceil(count($fields) / 2) * 31;
        $this->box($page, self::LEFT, $y - 3, self::RIGHT, $height, [247, 249, 251], [220, 225, 230]);
        $columnWidth = (self::RIGHT - self::LEFT) / 2;
        foreach ($fields as $index => [$label, $value]) {
            $x = (int) (self::LEFT + ($index % 2) * $columnWidth + 9);
            $rowY = $y + (int) floor($index / 2) * 31;
            $this->text($page, $x, $rowY + 9, $label, 8, true, [95, 95, 95]);
            $this->text($page, $x, $rowY + 24, $this->truncate($value, 44), 10);
        }
        $y += $height + 8;
    }

    private function tableHeader(array &$page, int &$y, array $headers, array $widths): void
    {
        $x = self::LEFT;
        $this->box($page, $x, $y, self::RIGHT, 22, [31, 78, 121], [31, 78, 121]);
        foreach ($headers as $index => $header) {
            $this->text($page, $x + 4, $y + 15, $header, 8, true, [255, 255, 255]);
            $x += $widths[$index];
        }
        $y += 22;
    }

    private function tableRow(array &$page, int &$y, array $row, array $widths, bool $muted = false): void
    {
        $lines = $this->rowLines($row, $widths);
        $height = max(24, count($lines) * 11 + 8);
        $this->box($page, self::LEFT, $y, self::RIGHT, $height, [255, 255, 255], [220, 225, 230]);
        $x = self::LEFT;
        foreach ($row as $index => $value) {
            foreach ($lines[$index] as $lineIndex => $line) {
                $this->text($page, $x + 4, $y + 15 + ($lineIndex * 13), $line, 9, $index === 1 && !$muted, $muted ? [110, 110, 110] : [35, 35, 35]);
            }
            $x += $widths[$index];
        }
        $y += $height;
    }

    private function rowLines(array $row, array $widths): array
    {
        $result = [];
        foreach ($row as $index => $value) {
            $result[] = $this->wrap($this->value($value), max(5, (int) floor($widths[$index] / 4.2)));
        }
        return $result;
    }

    private function textSection(array &$page, int &$y, string $title, mixed $value): void
    {
        $text = trim((string) $value);
        if ($text === '') {
            return;
        }
        $lines = $this->wrap($text, 105);
        if ($y + 20 + count($lines) * 11 > self::CONTENT_BOTTOM) {
            return;
        }
        $this->section($page, $title, $y);
        foreach ($lines as $line) {
            $this->text($page, self::LEFT, $y, $line, 8);
            $y += 11;
        }
        $y += 5;
    }

    private function footer(array &$page, int $pageNumber, int $total): void
    {
        $this->line($page, self::LEFT, 785, self::RIGHT, 785, [205, 205, 205]);
        $this->text($page, self::LEFT, 802, 'Digitally generated prescription | Confidential medical document | ROOTINS', 8, false, [105, 105, 105]);
        $this->text($page, 485, 802, 'Page ' . $pageNumber . ' of ' . $total, 8, false, [105, 105, 105]);
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
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?: '');
        if ($value === '') return [''];
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $value) ?: [] as $word) {
            while (mb_strlen($word) > $width) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }
            if ($line !== '' && mb_strlen($line . ' ' . $word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    private function fullName(array $data, string $prefix): string
    {
        return $this->value(trim(implode(' ', array_filter([$data[$prefix . 'first_name'] ?? '', $data[$prefix . 'middle_name'] ?? '', $data[$prefix . 'last_name'] ?? ''], static fn ($part): bool => trim((string) $part) !== ''))));
    }

    private function patientAgeGender(array $record): string
    {
        $age = '';
        if (!empty($record['patient_date_of_birth'])) {
            try { $age = (string) (new \DateTimeImmutable((string) $record['patient_date_of_birth']))->diff(new \DateTimeImmutable('today'))->y; } catch (\Throwable) { $age = ''; }
        }
        $gender = $this->value($record['patient_gender'] ?? $record['gender'] ?? null);
        return $age . ($gender !== '' ? ($age !== '' ? ' / ' : '') . PrintFormatter::label($gender) : '');
    }

    private function date(mixed $value): string { return PrintFormatter::date($value !== null ? (string) $value : null); }
    private function time(mixed $value): string { return PrintFormatter::time($value !== null ? (string) $value : null); }
    private function value(mixed $value): string { return trim((string) $value); }
    private function truncate(string $value, int $length): string { return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '...' : $value; }

    private function pdfString(string $value): string
    {
        $value = str_replace(['—', '–', '’', '“', '”'], ['-', '-', "'", '"', '"'], $value);
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function buildPdf(array $pages): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', 'PLACEHOLDER', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
        $kids = [];
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
        foreach ($objects as $number => $object) { $offsets[] = strlen($pdf); $pdf .= $number + 1 . " 0 obj\n" . $object . "\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }
}
