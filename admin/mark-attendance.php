<?php
// Admin can also mark attendance — reuse teacher's mark-attendance.php
require_once __DIR__ . '/../auth.php';
requireRole(['admin','principal'], '../index.php');
// Override role check in teacher file by including directly
include __DIR__ . '/../teacher/mark-attendance.php';
