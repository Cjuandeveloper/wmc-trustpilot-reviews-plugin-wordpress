<?php
/**
 * Plugin Name: WMC Trustpilot Reviews
 * Description: Muestra las reseñas de tu perfil de Trustpilot en WordPress (List + Header/Resumen vía shortcodes).
 * Version: 0.1.0
 * Author: Webmastercol
 * Author URI: https://webmastercol.com
 * Text Domain: wmc-trustpilot-reviews
 */

if (!defined('ABSPATH')) exit;

/** Autocarga muy simple de clases del plugin */
spl_autoload_register(function($class){
    if (strpos($class, 'WMC_TP_') !== 0) return;
    $file = plugin_dir_path(__FILE__) . 'inc/' . strtolower(str_replace('WMC_TP_', 'class-', $class)) . '.php';
    if (file_exists($file)) require_once $file;
});
require_once plugin_dir_path(__FILE__) . 'inc/helpers.php';

/** Arranque */
add_action('plugins_loaded', function(){
    WMC_TP_Plugin::instance()->init(__FILE__);
});
