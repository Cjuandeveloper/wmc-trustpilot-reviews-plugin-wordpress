<?php
if (!defined('ABSPATH')) exit;

final class WMC_TP_Shortcodes {
    private $plugin;
    public function __construct(WMC_TP_Plugin $plugin){ $this->plugin = $plugin; }

    public function hooks(){
        // List + alias
        add_shortcode('wmc_trustpilot_reviews',      [$this, 'shortcode_output_list']);
        add_shortcode('wmc_trustpilot_reviews_list', [$this, 'shortcode_output_list']);

        // Header (Info + Resumen)
        add_shortcode('wmc_trustpilot_summary',      [$this, 'shortcode_summary_block']);

        // ✅ NUEVO: Carousel
        add_shortcode('wmc_trustpilot_reviews_carousel', [$this, 'shortcode_output_carousel']);
    }

    // Header (Info + Resumen)
    public function shortcode_summary_block($atts = []) {
        $s = get_option(WMC_TP_Plugin::OPTION_KEY);
        $profileUrl = trim((string)($s['profile_url'] ?? ''));

        $res = $this->plugin->fetcher->fetch_and_cache_reviews(false);
        if (empty($res['success'])) return '<div class="wmc-tp-error">'.esc_html($res['message'] ?? '').'</div>';

        $items = array_values((array)$res['reviews']);
        $meta  = is_array($res['meta'] ?? null) ? $res['meta'] : [];

        if (empty($items)) return '<div class="wmc-tp-empty">'.esc_html__('No hay reseñas para mostrar.','wmc-trustpilot-reviews').'</div>';

        // Fallback de resumen
        $computed = $this->plugin->render->compute_summary_from_items($items);
        if (empty($meta['rating']))        $meta['rating'] = $computed['rating'];
        if (empty($meta['total_reviews'])) $meta['total_reviews'] = $computed['total'];
        if (empty(array_sum($meta['distribution'] ?? []))) $meta['distribution'] = $computed['distribution'];
        $meta['profile_url'] = $profileUrl;

        wp_enqueue_style('wmc-tp');

        return wmc_tp_render_template('summary.php', [
            'meta'        => $meta,
            'profile_url' => $profileUrl,
            'render'      => $this->plugin->render,
            'fetcher'     => $this->plugin->fetcher,
        ]);
    }

    // Único layout: Lista (sin filtros/atributos)
    public function shortcode_output_list($atts = []) {
        $s = get_option(WMC_TP_Plugin::OPTION_KEY);
        $showAvatar = !empty($s['show_avatars']);

        $res  = $this->plugin->fetcher->fetch_and_cache_reviews(false);
        if (empty($res['success'])) return '<div class="wmc-tp-error">'.esc_html($res['message'] ?? '').'</div>';

        $items = array_values((array)$res['reviews']);
        if (empty($items)) return '<div class="wmc-tp-empty">'.esc_html__('No hay reseñas para mostrar.','wmc-trustpilot-reviews').'</div>';

        $groups = $this->plugin->render->group_reviews_by_consumer($items);

        wp_enqueue_style('wmc-tp');

        return wmc_tp_render_template('list.php', [
            'groups'     => $groups,
            'showAvatar' => $showAvatar,
            'render'     => $this->plugin->render,
        ]);
    }

    /** ✅ NUEVO: Layout Carousel (drag + autoplay, sin flechas) */
    public function shortcode_output_carousel($atts = []) {
        $s = get_option(WMC_TP_Plugin::OPTION_KEY);
        $showAvatar = !empty($s['show_avatars']);

        // Atributos: autoplay en ms (0 = off). Valor por defecto 5000.
        $atts = shortcode_atts([
            'autoplay' => '5000',
        ], $atts, 'wmc_trustpilot_reviews_carousel');

        $autoplay = is_numeric($atts['autoplay']) ? max(0, (int)$atts['autoplay']) : 5000;

        $res  = $this->plugin->fetcher->fetch_and_cache_reviews(false);
        if (empty($res['success'])) return '<div class="wmc-tp-error">'.esc_html($res['message'] ?? '').'</div>';

        $items = array_values((array)$res['reviews']);
        if (empty($items)) return '<div class="wmc-tp-empty">'.esc_html__('No hay reseñas para mostrar.','wmc-trustpilot-reviews').'</div>';

        // Reutilizamos el grouping por usuario (experiencia consistente con el layout lista)
        $groups = $this->plugin->render->group_reviews_by_consumer($items);

        // Assets
        wp_enqueue_style('wmc-tp');
        wp_enqueue_script('wmc-tp-carousel'); // ya está registrado en class-plugin

        return wmc_tp_render_template('carousel.php', [
            'groups'      => $groups,
            'showAvatar'  => $showAvatar,
            'render'      => $this->plugin->render,
            'autoplayMs'  => $autoplay,
        ]);
    }
}
