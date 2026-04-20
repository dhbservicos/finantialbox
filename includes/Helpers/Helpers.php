<?php
/**
 * Helpers.php — ponto de entrada unificado para os helpers do plugin.
 * As classes Validator e Paginator estão em arquivos dedicados para:
 *   1. Compatibilidade com o autoloader PSR-4 (FFB\Helpers\Validator → Validator.php)
 *   2. require_once explícito no entry point do plugin
 *
 * Este arquivo apenas garante que ambas estejam carregadas
 * quando alguém incluir Helpers.php diretamente.
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Paginator.php';
