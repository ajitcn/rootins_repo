<?php

if (!function_exists('display_session_msg')) {
    function display_session_msg()
    {
        if (session()->has('status')) {
            if (session()->get('status') === true) {
                return '<div class="alert alert-success text-center">' . session()->get('msg') . '</div>';
            } else {
                return '<div class="alert alert-danger text-center" style="max-height:150px; overflow:auto;">' . session()->get('msg') . '</div>';
            }
        } else {
            return false;
        }
    }
}

if (!function_exists('getDayName')) {
    function getDayName(int $day): string
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        return $days[$day] ?? 'Unknown';
    }
}

if (!function_exists('getYMD')) {
    function getYMD($dateStr): string
    {
        return rootins_parse_date($dateStr) ?? '';
    }
}

if (!function_exists('_dateOnly')) {
    function _dateOnly($dateStr): string
    {
        return rootins_format_date($dateStr);
    }
}

if (!function_exists('rootins_parse_date')) {
    /** Normalize an exact user/API date to the database ISO format. */
    function rootins_parse_date(mixed $value, bool $allowIso = true): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = $allowIso ? ['!d-m-Y', '!Y-m-d'] : ['!d-m-Y'];

        foreach ($formats as $format) {
            if (($format === '!d-m-Y' && ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $value))
                || ($format === '!Y-m-d' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format === '!d-m-Y' ? 'd-m-Y' : 'Y-m-d') === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}

if (!function_exists('rootins_format_date')) {
    /** Format a stored/API date as the user-facing dd-mm-yyyy value. */
    function rootins_format_date(mixed $value): string
    {
        $iso = rootins_parse_date($value);

        if ($iso === null) {
            return '';
        }

        return substr($iso, 8, 2) . '-' . substr($iso, 5, 2) . '-' . substr($iso, 0, 4);
    }
}

if (!function_exists('rootins_format_time')) {
    /** Format a stored/API time as a user-facing 12-hour value. */
    function rootins_format_time(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $format = preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value) ? '!H:i:s' : '!H:i';
        $time = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($time === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return $value;
        }

        return $time->format('h:i A');
    }
}

if (!function_exists('rootins_parse_time')) {
    /** Normalize a user/API time to the database HH:mm:ss format. */
    function rootins_parse_time(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $formats = [];
        if (preg_match('/^(?:0[1-9]|1[0-2]):[0-5]\d [AP]M$/', $value)) {
            $formats[] = '!h:i A';
        } elseif (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            $formats[] = strlen($value) === 8 ? '!H:i:s' : '!H:i';
        } else {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat($formats[0], $value, new DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get())));
        $errors = DateTimeImmutable::getLastErrors();

        if ($time === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $time->format('H:i:s');
    }
}

if (!function_exists('patient_source_label')) {
    function patient_source_label(mixed $source): string
    {
        return match ((string) $source) {
            'self' => 'Self',
            'shopify' => 'Shopify',
            default => 'Unknown',
        };
    }
}

if (!function_exists('patient_source_badge_class')) {
    function patient_source_badge_class(mixed $source): string
    {
        return match ((string) $source) {
            'self' => 'badge-secondary',
            'shopify' => 'badge-primary',
            default => 'badge-light',
        };
    }
}

if (!function_exists('display_role_label')) {
    /**
     * Translate legacy stored role labels into the current public terminology.
     * Role codes, permission keys, and authorization checks remain unchanged.
     */
    function display_role_label(mixed $label, mixed $roleCode = null): string
    {
        $text = trim((string) $label);

        if (strtoupper(trim((string) $roleCode)) === 'PHARMACY_ASSISTANT') {
            return 'Pharmacy Assistant';
        }

        return $text;
    }
}

if (!function_exists('display_permission_label')) {
    function display_permission_label(mixed $label): string
    {
        $text = trim((string) $label);

        return $text;
    }
}

if (!function_exists('shopify_status_normalize')) {
    function shopify_status_normalize(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        $status = (string) preg_replace('/[\s\-\/]+/', '_', $status);
        $status = (string) preg_replace('/[^a-z0-9_]+/', '', $status);

        return match ($status) {
            'order_fulfilled' => 'fulfilled',
            'partial_refund' => 'partially_refunded',
            'notfulfilled' => 'not_fulfilled',
            default => $status,
        };
    }
}

if (!function_exists('shopify_status_tone')) {
    function shopify_status_tone(string $group, mixed $value): string
    {
        $status = shopify_status_normalize($value);
        $tones = [
            'payment' => ['paid' => 'green', 'pending' => 'amber', 'failed' => 'red', 'refunded' => 'purple', 'partially_refunded' => 'orange', 'cancelled' => 'red'],
            'import' => ['processed' => 'green', 'imported' => 'blue', 'ready_to_import' => 'blue', 'pending_mapping' => 'amber', 'quarantined' => 'orange', 'failed' => 'red', 'received' => 'gray', 'processing' => 'blue'],
            'prescription' => ['not_required' => 'gray', 'uploaded' => 'green', 'available' => 'green', 'awaiting_review' => 'amber', 'missing' => 'red', 'rejected' => 'red', 'approved' => 'green'],
            'delivery' => ['not_fulfilled' => 'gray', 'processing' => 'blue', 'packed' => 'blue', 'shipped' => 'indigo', 'delivered' => 'green', 'fulfilled' => 'green', 'cancelled' => 'red', 'returned' => 'purple', 'failed' => 'red'],
        ];
        return $tones[$group][$status] ?? 'gray';
    }
}

if (!function_exists('shopify_status_badge_class')) {
    function shopify_status_badge_class(string $group, mixed $value): string
    {
        $tone = shopify_status_tone($group, $value);
        $semantic = ['green' => 'status-success', 'blue' => 'status-info', 'indigo' => 'status-info', 'red' => 'status-danger', 'gray' => 'status-neutral', 'purple' => 'status-purple', 'orange' => 'status-warning', 'amber' => 'status-warning'][$tone] ?? 'status-neutral';
        return 'badge badge-status status-badge ri-status ri-status--' . $tone . ' ri-status-dot ' . $semantic;
    }
}

if (!function_exists('shopify_status_label')) {
    function shopify_status_label(string $group, mixed $value): string
    {
        $status = shopify_status_normalize($value);
        if ($group === 'delivery' && $status === '') return 'Not fulfilled';
        return $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status));
    }
}
