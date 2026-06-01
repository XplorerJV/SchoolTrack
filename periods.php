<?php
// 9 Periods: 8AM-5PM, 1hr each
// Break 1: after Period 3 (11:00-11:30)
// Break 2: after Period 6 (14:30-15:00)
// Periods 7-9 continue after break 2

define('PERIOD_TIMES', [
    1 => ['label' => 'Period 1', 'time' => '08:00–09:00', 'start' => '08:00'],
    2 => ['label' => 'Period 2', 'time' => '09:00–10:00', 'start' => '09:00'],
    3 => ['label' => 'Period 3', 'time' => '10:00–11:00', 'start' => '10:00'],
    // Break 1: 11:00–11:30
    4 => ['label' => 'Period 4', 'time' => '11:30–12:30', 'start' => '11:30'],
    5 => ['label' => 'Period 5', 'time' => '12:30–13:30', 'start' => '12:30'],
    6 => ['label' => 'Period 6', 'time' => '13:30–14:30', 'start' => '13:30'],
    // Break 2: 14:30–15:00
    7 => ['label' => 'Period 7', 'time' => '15:00–16:00', 'start' => '15:00'],
    8 => ['label' => 'Period 8', 'time' => '16:00–17:00', 'start' => '16:00'],
    9 => ['label' => 'Period 9', 'time' => '17:00–18:00', 'start' => '17:00'],
]);
