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
    function getYMD($dateStr)
    {
        return date('Y-m-d', strtotime($dateStr));
    }
}
if (!function_exists('_dateOnly')) {
    function _dateOnly($dateStr)
    {
        return date('d-m-Y', strtotime($dateStr));
    }
}
