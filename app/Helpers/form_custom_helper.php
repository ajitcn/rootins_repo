<?php

if (!function_exists('display_form_error')) {
    function display_form_error($validation, $field)
    {
        if (isset($validation)) {
            if (isset($validation[$field])) {
                return $validation[$field];
            }else{
                return false;
            }
        }
    }
}