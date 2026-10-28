<?php

namespace Admin\Services;

use DateTimeImmutable;
use DateTimeZone;

class DashboardService
{
    private const DEFAULT_RANGE = 'last_7_days';
    private const RANGES = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'custom' => 'Custom range',
    ];

    private DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
    }

    public function rangeOptions(): array
    {
        return self::RANGES;
    }

    /**
     * Normalize a dashboard value collection before aggregate operations.
     *
     * Dashboard data can be empty, scalar, or an array depending on the
     * query/result shape. Keep only numeric values so callers never pass an
     * invalid value (or a scalar as the sole max() argument) to max().
     */
    public static function normalizeNumericValues(mixed $values): array
    {
        if ($values === null) {
            return [];
        }

        $values = is_array($values) ? $values : [$values];
        $values = array_filter($values, static fn (mixed $value): bool => is_numeric($value));

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $values));
    }

    public static function maximumNumericValue(mixed $values, int $default = 0): int
    {
        $normalized = self::normalizeNumericValues($values);

        return $normalized === [] ? $default : max($normalized);
    }

    public function normalizeRange(?string $requested, ?string $from = null, ?string $to = null): array
    {
        $key = strtolower(trim((string) $requested));
        $aliases = ['7d' => 'last_7_days', 'last7' => 'last_7_days', '30d' => 'last_30_days', 'last30' => 'last_30_days', 'month' => 'this_month'];
        $key = $aliases[$key] ?? $key;
        $now = new DateTimeImmutable('now', $this->timezone);
        $today = $now->setTime(0, 0);

        if ($key === 'custom') {
            $fromDate = $this->parseDate($from);
            $toDate = $this->parseDate($to);
            if ($fromDate !== null && $toDate !== null && $fromDate <= $toDate) {
                return $this->makeRange('custom', $fromDate, $toDate);
            }
            $key = self::DEFAULT_RANGE;
        }

        if (!isset(self::RANGES[$key]) || $key === 'custom') {
            $key = self::DEFAULT_RANGE;
        }

        $start = $today;
        $end = $today;
        switch ($key) {
            case 'yesterday':
                $start = $today->modify('-1 day'); $end = $start;
                break;
            case 'last_7_days':
                $start = $today->modify('-6 days');
                break;
            case 'last_30_days':
                $start = $today->modify('-29 days');
                break;
            case 'this_month':
                $start = $today->modify('first day of this month');
                break;
            case 'last_month':
                $start = $today->modify('first day of last month'); $end = $today->modify('last day of last month');
                break;
            case 'this_year':
                $start = $today->setDate((int) $today->format('Y'), 1, 1);
                break;
        }

        return $this->makeRange($key, $start, $end);
    }

    public function data(array $input = []): array
    {
        $range = $this->normalizeRange($input['range'] ?? null, $input['from'] ?? null, $input['to'] ?? null);
        $db = db_connect();
        $from = $range['from'];
        $to = $range['to'];
        $fromDateTime = $from . ' 00:00:00';
        $toDateTime = $to . ' 23:59:59';

        $count = static function (string $table, string $dateColumn, array $where = []) use ($db, $fromDateTime, $toDateTime): int {
            $query = $db->table($table)->where($dateColumn . ' >=', $fromDateTime)->where($dateColumn . ' <=', $toDateTime);
            foreach ($where as $column => $value) $query->where($column, $value);
            return (int) $query->countAllResults();
        };

        $appointmentsByDay = $db->table('appointments')->select('appointment_date day, COUNT(*) total')
            ->where('appointment_date >=', $from)->where('appointment_date <=', $to)
            ->groupBy('appointment_date')->orderBy('appointment_date', 'ASC')->get()->getResultArray();
        $appointmentTotals = array_map(
            static fn (array $row): mixed => $row['total'] ?? null,
            $appointmentsByDay
        );
        $statuses = $db->table('appointments')->select('status, COUNT(*) total')
            ->where('appointment_date >=', $from)->where('appointment_date <=', $to)
            ->groupBy('status')->get()->getResultArray();
        $revenue = $db->table('invoices')->selectSum('total')->where('status', 'finalized')
            ->where('created_at >=', $fromDateTime)->where('created_at <=', $toDateTime)->get()->getRow('total');
        $activity = $db->table('audit_logs')->select('action, route, occurred_at, clinic_id')
            ->where('occurred_at >=', $fromDateTime)->where('occurred_at <=', $toDateTime)
            ->orderBy('occurred_at', 'DESC')->limit(10)->get()->getResultArray();

        $appointmentStatuses = [];
        foreach ($statuses as $status) $appointmentStatuses[(string) $status['status']] = (int) $status['total'];
        return [
            'range' => $range,
            'metrics' => [
                'clinics' => $count('clinics', 'created_at', ['status' => 1]),
                'doctors' => $count('doctors', 'created_at', ['status' => 1]),
                'patients' => $count('patients', 'created_at', ['status' => 1]),
                'appointments' => (int) $db->table('appointments')->where('appointment_date >=', $from)->where('appointment_date <=', $to)->countAllResults(),
                'revenue' => (float) ($revenue ?? 0),
                'payments' => $count('invoices', 'created_at', ['payment_status' => 'paid']),
                'orders' => $count('medicine_orders', 'created_at'),
                'audit_alerts' => (int) $db->table('audit_logs')->where('occurred_at >=', $fromDateTime)->where('occurred_at <=', $toDateTime)->groupStart()->like('action', 'failed')->orLike('action', 'denied')->groupEnd()->countAllResults(),
            ],
            'charts' => ['appointments_by_day' => $appointmentsByDay, 'appointment_statuses' => $appointmentStatuses],
            'chart_maxima' => ['appointments_by_day' => max(1, self::maximumNumericValue($appointmentTotals))],
            'summaries' => ['total_appointments' => array_sum($appointmentStatuses), 'completed_appointments' => $appointmentStatuses['completed'] ?? 0, 'cancelled_appointments' => $appointmentStatuses['cancelled'] ?? 0],
            'tables' => ['recent_activity' => $activity],
        ];
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function makeRange(string $key, DateTimeImmutable|string $start, DateTimeImmutable|string $end): array
    {
        $from = $start instanceof DateTimeImmutable ? $start->format('Y-m-d') : $start;
        $to = $end instanceof DateTimeImmutable ? $end->format('Y-m-d') : $end;
        $fromDisplay = new DateTimeImmutable($from . ' 00:00:00', $this->timezone);
        $toDisplay = new DateTimeImmutable($to . ' 00:00:00', $this->timezone);
        return ['key' => $key, 'label' => self::RANGES[$key] ?? self::RANGES[self::DEFAULT_RANGE], 'from' => $from, 'to' => $to, 'from_display' => $fromDisplay->format('d M Y'), 'to_display' => $toDisplay->format('d M Y')];
    }
}
