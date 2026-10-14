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
