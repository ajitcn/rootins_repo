<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

final class AppointmentConsultationEligibility
{
    public const PAST_MESSAGE = 'Past appointment: consultation cannot be started for a date before today.';

    public const INVALID_MESSAGE = 'Consultation is unavailable because the appointment date is invalid.';

    private DateTimeZone $timezone;

    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone(config('App')->appTimezone);
    }

    public function appointmentDate(array $appointment): ?DateTimeImmutable
    {
        $date = trim((string) ($appointment['appointment_date'] ?? ''));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $appointmentDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($appointmentDate === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $appointmentDate;
    }

    public function isConsultable(array $appointment, ?DateTimeImmutable $now = null): bool
    {
        $appointmentDate = $this->appointmentDate($appointment);

        if ($appointmentDate === null) {
            return false;
        }

        return $appointmentDate >= $this->today($now);
    }

    public function isPast(array $appointment, ?DateTimeImmutable $now = null): bool
    {
        $appointmentDate = $this->appointmentDate($appointment);

        return $appointmentDate !== null && $appointmentDate < $this->today($now);
    }

    public function isToday(array $appointment, ?DateTimeImmutable $now = null): bool
    {
        $appointmentDate = $this->appointmentDate($appointment);

        return $appointmentDate !== null && $appointmentDate == $this->today($now);
    }

    public function unavailableReason(array $appointment, ?DateTimeImmutable $now = null): ?string
    {
        if ($this->appointmentDate($appointment) === null) {
            return self::INVALID_MESSAGE;
        }

        return $this->isPast($appointment, $now) ? self::PAST_MESSAGE : null;
    }

    private function today(?DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->applicationTime($now)->setTime(0, 0, 0);
    }

    private function applicationTime(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable('now', $this->timezone))->setTimezone($this->timezone);
    }
}
