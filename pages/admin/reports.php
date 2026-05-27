<?php
// Admin uses the same reports page
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole(['admin']);
// Re-route to landlord reports with admin context
include __DIR__ . '/../landlord/reports.php';
