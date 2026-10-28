<?php

namespace Tests\Unit;

use App\Services\Common\PrescriptionPdfService;
use PHPUnit\Framework\TestCase;

final class PrescriptionDocumentTest extends TestCase
{
    public function testOnePagePrescriptionContainsCoreDocumentData(): void
    {
        $pdf = (new PrescriptionPdfService())->generate($this->prescription(), [$this->item('Paracetamol', 1)]);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('ROOTINS', $pdf);
        self::assertStringContainsString('RXP-TEST-0001', $pdf);
        self::assertStringContainsString('PAT-TEST-0001', $pdf);
        self::assertStringContainsString('Paracetamol', $pdf);
        self::assertStringContainsString('Page 1 of 1', $pdf);
        self::assertStringNotContainsString('Cart Sync', $pdf);
        self::assertStringNotContainsString('variant_id', $pdf);
    }

    public function testLongPrescriptionCreatesNumberedMultiplePages(): void
    {
        $items = [];
        for ($index = 1; $index <= 35; $index++) {
            $items[] = $this->item('Long medicine name ' . $index, $index);
        }

        $pdf = (new PrescriptionPdfService())->generate($this->prescription(), $items);

        self::assertStringContainsString('MEDICINES \\(CONTINUED\\)', $pdf);
        self::assertStringContainsString('Page 1 of ', $pdf);
        self::assertGreaterThan(1, substr_count($pdf, '/Type /Page'));
    }

    public function testEmptyPrescriptionShowsSafeEmptyState(): void
    {
        $pdf = (new PrescriptionPdfService())->generate($this->prescription(), []);

        self::assertStringContainsString('No prescription medicines', $pdf);
        self::assertStringNotContainsString('undefined', strtolower($pdf));
        self::assertStringNotContainsString('null', strtolower($pdf));
    }

    public function testModalUsesTheCanonicalPrescriptionTemplateContract(): void
    {
        $template = file_get_contents(ROOTPATH . 'app/Views/templates/prescription_document.php');
        self::assertIsString($template);
        foreach (['rootins-prescription-letterhead', 'rootins-prescription-identity', 'rootins-prescription-medicines', 'Strength/Form', 'Doctor signature'] as $marker) {
            self::assertStringContainsString($marker, $template);
        }
        self::assertStringNotContainsString('cart_sync_status', $template);
        self::assertStringNotContainsString('variant_id', $template);
    }

    private function prescription(): array
    {
        return [
            'prescription_number' => 'RXP-TEST-0001',
            'status' => 'completed',
            'prescription_date' => '2026-10-28',
            'valid_until' => '2026-11-28',
            'consultation_number' => 'CON-TEST-0001',
            'appointment_number' => 'APT-TEST-0001',
            'clinic_name' => 'ROOTINS Clinic',
            'clinic_address' => '1 Health Street',
            'clinic_mobile' => '9000000000',
            'clinic_email' => 'clinic@example.test',
            'doctor_first_name' => 'Asha',
            'doctor_last_name' => 'Doctor',
            'qualification' => 'MBBS',
            'doctor_registration_number' => 'REG-001',
            'patient_first_name' => 'Test',
            'patient_last_name' => 'Patient',
            'patient_date_of_birth' => '1990-01-01',
            'patient_gender' => 'female',
            'patient_mobile' => '9111111111',
            'registration_number' => 'PAT-TEST-0001',
            'notes' => 'Take medicines as directed.',
        ];
    }

    private function item(string $name, int $quantity): array
    {
        return [
            'medicine_name' => $name,
            'strength' => '500 mg',
            'dosage' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '5',
            'duration_unit' => 'days',
            'quantity' => $quantity,
            'instructions' => 'Take after food and keep hydrated.',
        ];
    }
}
