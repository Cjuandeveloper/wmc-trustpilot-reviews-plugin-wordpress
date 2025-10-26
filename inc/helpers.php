<?php
if (!defined('ABSPATH')) exit;

function wmc_tp_path($file = '') {
    static $base;
    if (!$base) $base = dirname(__DIR__);
    return $base . ($file ? '/' . ltrim($file, '/') : '');
}

function wmc_tp_url($file = '') {
    static $base;
    if (!$base) $base = plugins_url('', dirname(__FILE__));
    return $base . ($file ? '/' . ltrim($file, '/') : '');
}

/** Carga segura de plantillas */
function wmc_tp_render_template($_template, array $_vars = []) {
    $path = wmc_tp_path('templates/' . ltrim($_template, '/'));
    if (!file_exists($path)) return '';
    extract($_vars, EXTR_SKIP);
    ob_start();
    include $path;
    return ob_get_clean();
}
