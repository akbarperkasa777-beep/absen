<?php
/**
 * Shared attendance schedule rules.
 */

function attendance_minutes_late(string $checkInTime): int {
    $actual = strtotime('1970-01-01 ' . $checkInTime);
    $scheduled = strtotime('1970-01-01 ' . ATTENDANCE_START_TIME . ':00');
    if ($actual === false || $scheduled === false) {
        throw new InvalidArgumentException('Format waktu presensi tidak valid.');
    }

    $difference = $actual - $scheduled;
    return $difference > 0 ? (int)ceil($difference / 60) : 0;
}

function attendance_checkout_is_early(string $time): bool {
    $actual = strtotime('1970-01-01 ' . $time);
    $earliest = strtotime('1970-01-01 ' . EARLIEST_CHECKOUT_TIME . ':00');
    if ($actual === false || $earliest === false) {
        throw new InvalidArgumentException('Format waktu pulang tidak valid.');
    }

    return $actual < $earliest;
}

function attendance_time_reached(string $now, string $target): bool {
    $current = strtotime('1970-01-01 ' . $now);
    $scheduled = strtotime('1970-01-01 ' . $target . ':00');
    if ($current === false || $scheduled === false) {
        throw new InvalidArgumentException('Format waktu jadwal tidak valid.');
    }

    return $current >= $scheduled;
}
