<?php
if (!defined('ABSPATH')) exit;

final class WMC_TP_Plugin {
    /* ====== Constantes ====== */
    const OPTION_KEY     = 'wmc_trustpilot_settings';
    const TRANSIENT_KEY  = 'wmc_trustpilot_cached_reviews';
    const NONCE_ACTION   = 'wmc_trustpilot_fetch_nonce';
    const STAR_GREEN     = '#00B67A';
    const CACHE_HOURS    = 12;

    // Límites internos de crawl
    const INTERNAL_PAGE_CAP            = 500;
    const INTERNAL_CONSUMER_CAP        = 80;
    const INTERNAL_CONSUMER_PAGE_CAP   = 6;

    // Métrica de última sync (solo para UI)
    const STATS_TRANSIENT = 'wmc_trustpilot_last_stats';

    /** Rutas candidatas en __NEXT_DATA__ (para reviews) */
    public const NEXT_DATA_PATHS = [
        ['props','pageProps','reviews'],
        ['props','pageProps','businessUnit','reviews'],
        ['props','pageProps','embeddedReviews'],
        ['props','pageProps','fallback','data'],
    ];

    /** Singleton */
    private static $inst;
    public static function instance(){ return self::$inst ?: (self::$inst = new self()); }

    /** Rutas */
    private $plugin_file;

    /** Servicios */
    public $fetcher;
    public $render;
    private $admin;
    private $shortcodes;

    public function init($plugin_file){
        $this->plugin_file = $plugin_file;

        // i18n
        add_action('init', function(){
            load_plugin_textdomain('wmc-trustpilot-reviews', false, dirname(plugin_basename($this->plugin_file)) . '/languages');
        });

        // Servicios
        $this->fetcher    = new WMC_TP_Fetcher($this);
        $this->render     = new WMC_TP_Render($this);

        // Admin
        $this->admin      = new WMC_TP_Admin($this);
        $this->admin->hooks();

        // Shortcodes
        $this->shortcodes = new WMC_TP_Shortcodes($this);
        $this->shortcodes->hooks();

        // Assets
        add_action('wp_enqueue_scripts', function () {
            wp_register_style('wmc-tp', wmc_tp_url('assets/css/wmc-tp.css'), [], '1.6.1');
            // JS del carrusel (solo se encola cuando se usa el shortcode)
            wp_register_script('wmc-tp-carousel', wmc_tp_url('assets/js/wmc-tp.js'), [], '1.0.0', true);
        });

        // Asegurar CSS (mantenemos la generación automática, ahora en assets/css/)
        add_action('init', [$this, 'ensure_css_exists']);
    }

    /* ===================== Assets ===================== */
    public function ensure_css_exists() {
        $css_dir  = wmc_tp_path('assets/css');
        $css_path = $css_dir . '/wmc-tp.css';
        if (!is_dir($css_dir)) @wp_mkdir_p($css_dir);

        $green = self::STAR_GREEN;
        $css = <<<CSS
/* ===================== */
/* WMC Trustpilot – Base (solo lista + summary) */
/* ===================== */

/* Header: tarjeta negocio + summary */
.wmc-tp-header{display:grid;grid-template-columns:1fr 360px;gap:20px;margin:8px 0 20px}
.wmc-tp-bizcard{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px}
.wmc-tp-bizcard__row{display:flex;gap:14px;align-items:flex-start}
.wmc-tp-bizcard__logo-wrap{width:72px;height:72px;border-radius:12px;overflow:hidden;border:1px solid #E5E7EB;background:#F9FAFB;flex:0 0 72px}
.wmc-tp-bizcard__logo{width:100%;height:100%;object-fit:cover;display:block}
.wmc-tp-bizcard__main{min-width:0}
.wmc-tp-bizcard__name{margin:0 0 4px 0;font-size:1.4rem;line-height:1.1}
.wmc-tp-bizcard__cat{color:#6B7280;margin-bottom:10px}
.wmc-tp-bizcard__desc{margin-top:12px;color:#374151}
.wmc-tp-bizcard__cta{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px}

/* CTA unificados */
.wmc-tp-btn{all:unset;display:inline-flex;align-items:center;gap:8px;padding:10px 14px;line-height:1;font-weight:600;cursor:pointer;border:1px solid #E5E7EB;border-radius:9999px;text-decoration:none;transition:transform .08s ease, box-shadow .12s ease, background-color .12s ease, border-color .12s ease, color .12s ease;min-height:40px}
.wmc-tp-btn__icon svg{width:18px;height:18px;fill:currentColor}
.wmc-btn-write{background:#fff;color:#111827;border-color:#E5E7EB}
.wmc-btn-write:hover{background:#F9FAFB;border-color:#E5E7EB;transform:translateY(-1px);box-shadow:0 1px 2px rgba(0,0,0,.05)}
.wmc-btn-view{background:#00B67A;border-color:#00B67A;color:#fff}
.wmc-btn-view:hover{background:#00a56e;border-color:#00a56e;transform:translateY(-1px);box-shadow:0 1px 2px rgba(0,0,0,.06)}
.wmc-tp-btn::before,.wmc-tp-btn::after,a.wmc-tp-btn[target="_blank"]::after{content:none!important}

/* Summary */
.wmc-tp-summary{display:grid;grid-template-columns:110px 1fr;gap:12px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px}
.wmc-tp-score{display:flex;flex-direction:column;gap:6px;align-items:flex-start}
.wmc-tp-score__value{font-size:40px;font-weight:800;line-height:1}
.wmc-tp-score__stars{font-size:20px}
.wmc-tp-score__count{color:#6B7280;font-size:.95rem}
.wmc-tp-bar{display:grid;grid-template-columns:38px 1fr 42px;gap:8px;align-items:center;margin:4px 0}
.wmc-tp-bar__label{color:#374151;width:38px;text-align:right}
.wmc-tp-bar__track{position:relative;height:10px;background:#F3F4F6;border-radius:6px;overflow:hidden}
.wmc-tp-bar__fill{position:absolute;top:0;left:0;height:100%;background:$green}
.wmc-tp-bar__value{color:#6B7280;text-align:right}
@media (max-width:900px){.wmc-tp-header{grid-template-columns:1fr}.wmc-tp-summary{grid-template-columns:1fr}}

/* List base */
.wmc-tp{display:grid;gap:16px}
.wmc-tp--list{grid-template-columns:1fr}

.wmc-tp-card{border:1px solid #e5e7eb;border-radius:16px;padding:16px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.wmc-tp-card__header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.wmc-tp-left{display:flex;align-items:center;gap:12px}
.wmc-tp-avatar{width:32px;height:32px;border-radius:9999px;background:#F3F4F6;color:#111827;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;border:1px solid #E5E7EB;overflow:hidden}
.wmc-tp-avatar img{width:100%;height:100%;object-fit:cover;border-radius:9999px;display:block}
.wmc-tp-meta{display:flex;flex-direction:column}
.wmc-tp-stars{font-size:16px;line-height:1}
.wmc-star{color:#D1D5DB;margin-right:1px}
.wmc-star.filled{color:$green}
.wmc-tp-title{margin:.25rem 0 .35rem;font-size:1rem}
.wmc-tp-text{margin:0 0 .75rem;color:#374151}
.wmc-tp-footer{display:flex;gap:12px;align-items:center;justify-content:flex-end}
.wmc-tp-author{font-weight:600;font-size:.95rem}
.wmc-tp-link{font-size:.9rem;text-decoration:none;color:#111827}
.wmc-tp-link:hover{text-decoration:underline}
.wmc-tp-date{color:#6B7280;font-size:.9rem}
.wmc-tp-error,.wmc-tp-empty{padding:12px;border-radius:8px;background:#fff3cd;border:1px solid #ffeeba}

/* Historial (desplegable) */
.wmc-tp-more{margin-top:10px;position:relative}
.wmc-tp-more > summary{cursor:pointer;list-style:none;display:block;color:#111827;padding:4px 0}
.wmc-tp-more > summary::-webkit-details-marker{display:none}
.wmc-tp-more .wmc-tp-older{display:none}
.wmc-tp-more[open] .wmc-tp-older{display:block!important}
.wmc-tp-older{border-top:1px dashed #e5e7eb;margin-top:8px;padding-top:8px}
.wmc-tp-older__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}

/* ===== Carousel (NUEVO) ===== */
.wmc-tp-carousel{position:relative}
.wmc-tp-carousel .wmc-tp-viewport{overflow:hidden}
.wmc-tp-carousel [data-track]{
  --wmc-gap:16px;
  display:flex !important; /* fuerza el layout en caso de colisiones */
  gap:var(--wmc-gap);
  scroll-behavior:smooth; overflow:auto;
  scrollbar-width:none; -ms-overflow-style:none;
  padding:2px;
  cursor: grab;
  touch-action: pan-y;
}
.wmc-tp-carousel [data-track]::-webkit-scrollbar{display:none}
.wmc-tp-carousel [data-track].is-dragging{ cursor: grabbing; }
.wmc-tp-carousel [data-track].is-dragging *{
  user-select:none; -webkit-user-select:none; -ms-user-select:none;
}
.wmc-tp-slide{flex:0 0 100%}
@media(min-width:640px){.wmc-tp-slide{flex-basis:50%}}
@media(min-width:1024px){.wmc-tp-slide{flex-basis:33.3333%}}
.wmc-tp-carousel .wmc-tp-card{height:100%}
CSS;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if (WP_Filesystem()) {
            $curr = $wp_filesystem->exists($css_path) ? $wp_filesystem->get_contents($css_path) : '';
            if ($curr !== $css) $wp_filesystem->put_contents($css_path, $css, FS_CHMOD_FILE);
        } else {
            $curr = @file_exists($css_path) ? @file_get_contents($css_path) : '';
            if ($curr !== $css) @file_put_contents($css_path, $css);
        }
    }
}
