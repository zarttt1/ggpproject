<?php
// app/helpers.php

function formatDateRange($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = (!empty($end_date) && $end_date != '0000-00-00') ? strtotime($end_date) : $start;

    if (date('Y-m-d', $start) === date('Y-m-d', $end)) {
        return date('M d, Y', $start);
    }

    if (date('Y', $start) === date('Y', $end)) {
        if (date('M', $start) === date('M', $end)) {
            return date('M d', $start) . ' - ' . date('d, Y', $end);
        }
        return date('M d', $start) . ' - ' . date('M d, Y', $end);
    }

    return date('M d, Y', $start) . ' - ' . date('M d, Y', $end);
}
?>