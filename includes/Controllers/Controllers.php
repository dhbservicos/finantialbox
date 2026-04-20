<?php
/**
 * Controllers.php — compatibilidade retroativa.
 * As classes foram movidas para arquivos individuais (padrão PSR-4).
 * Este arquivo garante que caso seja incluído diretamente, as classes existam.
 */
namespace FFB\Controllers;
defined('ABSPATH') || exit;

// Cada classe em seu próprio arquivo (carregadas pelo autoloader PSR-4)
// Este stub evita "Class not found" se alguém incluir este arquivo diretamente.
require_once __DIR__ . '/DashboardController.php';
require_once __DIR__ . '/TransactionController.php';
require_once __DIR__ . '/AccountController.php';
require_once __DIR__ . '/CategoryController.php';
require_once __DIR__ . '/BillController.php';
require_once __DIR__ . '/BudgetController.php';
require_once __DIR__ . '/ReportController.php';
require_once __DIR__ . '/SettingsController.php';
