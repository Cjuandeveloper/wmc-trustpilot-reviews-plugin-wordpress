<?php
if (!defined('ABSPATH')) exit;

final class WMC_TP_Admin {
    private $plugin;
    public function __construct(WMC_TP_Plugin $plugin){ $this->plugin = $plugin; }

    public function hooks(){
        add_action('admin_menu',  [$this, 'admin_menu']);
        add_action('admin_init',  [$this, 'register_settings']);
        add_action('wp_ajax_wmc_trustpilot_fetch', [$this, 'ajax_fetch_reviews']);
    }

    public function admin_menu() {
        add_menu_page(
            __('Trustpilot Reviews', 'wmc-trustpilot-reviews'),
            __('Trustpilot Reviews', 'wmc-trustpilot-reviews'),
            'manage_options',
            'wmc-trustpilot-reviews',
            [$this, 'settings_page'],
            'dashicons-star-filled',
            56
        );
    }

    public function register_settings() {
        register_setting(WMC_TP_Plugin::OPTION_KEY, WMC_TP_Plugin::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [
                'profile_url'   => '',
                'show_avatars'  => 1,
            ],
        ]);

        add_settings_section(
            'wmc_main',
            __('General Settings', 'wmc-trustpilot-reviews'),
            function () {
                echo '<p>' . esc_html__(
                    'Pega la URL pública de tu perfil de Trustpilot (ej.: https://es.trustpilot.com/review/tu-dominio.com)',
                    'wmc-trustpilot-reviews'
                ) . '</p>';
            },
            WMC_TP_Plugin::OPTION_KEY
        );

        $s = get_option(WMC_TP_Plugin::OPTION_KEY);

        add_settings_field(
            'profile_url',
            __('Business Profile URL', 'wmc-trustpilot-reviews'),
            function () use ($s) {
                printf(
                    '<input type="url" class="regular-text" name="%s[profile_url]" value="%s" placeholder="https://es.trustpilot.com/review/tu-dominio.com" />',
                    esc_attr(WMC_TP_Plugin::OPTION_KEY),
                    isset($s['profile_url']) ? esc_url($s['profile_url']) : ''
                );
            },
            WMC_TP_Plugin::OPTION_KEY,
            'wmc_main'
        );

        add_settings_field(
            'show_avatars',
            __('Mostrar avatares en reseñas', 'wmc-trustpilot-reviews'),
            function () use ($s) {
                $val = !empty($s['show_avatars']) ? 1 : 0;
                printf(
                    '<label><input type="checkbox" name="%s[show_avatars]" value="1" %s /> %s</label>',
                    esc_attr(WMC_TP_Plugin::OPTION_KEY),
                    checked($val,1,false),
                    esc_html__('Si hay foto en Trustpilot la mostramos; si no, iniciales.', 'wmc-trustpilot-reviews')
                );
            },
            WMC_TP_Plugin::OPTION_KEY,
            'wmc_main'
        );
    }

    /** @param array<string,mixed> $input */
    public function sanitize_settings($input) {
        $out = [];
        $out['profile_url']  = isset($input['profile_url']) ? esc_url_raw($input['profile_url']) : '';
        $out['show_avatars'] = !empty($input['show_avatars']) ? 1 : 0;

        $prev = get_option(WMC_TP_Plugin::OPTION_KEY);
        if (!empty($prev) && isset($prev['profile_url']) && $prev['profile_url'] !== $out['profile_url']) {
            delete_transient(WMC_TP_Plugin::TRANSIENT_KEY);
        }
        return $out;
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        $stats = get_transient(WMC_TP_Plugin::STATS_TRANSIENT); ?>
        <div class="wrap">
            <h1><?php esc_html_e('WMC Trustpilot Reviews','wmc-trustpilot-reviews'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(WMC_TP_Plugin::OPTION_KEY);
                do_settings_sections(WMC_TP_Plugin::OPTION_KEY);
                submit_button(__('Guardar cambios','wmc-trustpilot-reviews'));
                ?>
            </form>

            <hr/>
            <h2><?php esc_html_e('Shortcodes (layouts disponibles)','wmc-trustpilot-reviews'); ?></h2>
            <p><?php esc_html_e('Usa el siguiente shortcode.','wmc-trustpilot-reviews'); ?></p>
            <ul id="wmc-sc-list">
                <li><strong>List</strong> — <code>[wmc_trustpilot_reviews_list]</code> <button class="button button-small" data-copy="[wmc_trustpilot_reviews_list]"><?php esc_html_e('Copiar','wmc-trustpilot-reviews'); ?></button></li>
                <li><strong>Header (Info + Resumen)</strong> — <code>[wmc_trustpilot_summary]</code> <button class="button button-small" data-copy='[wmc_trustpilot_summary]'><?php esc_html_e('Copiar','wmc-trustpilot-reviews'); ?></button></li>
                <!-- NUEVO -->
                <li><strong>Carousel</strong> — <code>[wmc_trustpilot_reviews_carousel autoplay="5000"]</code> <button class="button button-small" data-copy='[wmc_trustpilot_reviews_carousel autoplay="5000"]'><?php esc_html_e('Copiar','wmc-trustpilot-reviews'); ?></button></li>
            </ul>

            <hr/>
            <h2><?php esc_html_e('Sincronizar reseñas','wmc-trustpilot-reviews'); ?></h2>
            <p><?php esc_html_e('Trae/actualiza las reseñas desde tu perfil y regenera la caché.','wmc-trustpilot-reviews'); ?></p>
            <button id="wmc-fetch" class="button button-primary"><?php esc_html_e('Fetch Your Reviews','wmc-trustpilot-reviews'); ?></button>
            <span id="wmc-fetch-status" style="margin-left:8px;"></span>

            <?php if (is_array($stats)): ?>
                <div style="margin-top:10px;color:#555">
                    <em>
                        <?php
                        printf(
                            esc_html__('Última sync: %1$s · páginas negocio: %2$d · perfiles usuario: %3$d · reseñas totales: %4$d (agregadas por usuario: %5$d)','wmc-trustpilot-reviews'),
                            esc_html(date_i18n(get_option('date_format').' H:i', intval($stats['time']??time()))),
                            intval($stats['pages']??0),
                            intval($stats['consumers']??0),
                            intval($stats['total']??0),
                            intval($stats['extra_from_consumers']??0)
                        );
                        ?>
                    </em>
                </div>
            <?php endif; ?>
        </div>
        <script>
        (function(){
          const btn = document.getElementById('wmc-fetch');
          const status = document.getElementById('wmc-fetch-status');
          if (btn){
            btn.addEventListener('click', function(e){
              e.preventDefault();
              status.textContent = 'Sincronizando...';
              fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                  action: 'wmc_trustpilot_fetch',
                  _wpnonce: '<?php echo esc_js(wp_create_nonce(WMC_TP_Plugin::NONCE_ACTION)); ?>'
                })
              }).then(r => r.json()).then(data => {
                status.textContent = data.success ? ('OK ('+(data.count ?? 'reseñas actualizadas')+')') : ('Error: ' + (data.message || 'Desconocido'));
              }).catch(() => { status.textContent = 'Error de red'; });
            });
          }
          document.querySelectorAll('#wmc-sc-list [data-copy]').forEach(function(el){
            el.addEventListener('click', function(){
              const t = el.getAttribute('data-copy') || '';
              navigator.clipboard.writeText(t).then(function(){
                el.textContent = 'Copiado';
                setTimeout(()=>{ el.textContent = '<?php echo esc_js(__('Copiar','wmc-trustpilot-reviews')); ?>'; }, 1200);
              });
            });
          });
        })();
        </script><?php
    }

    public function ajax_fetch_reviews() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permisos insuficientes'], 403);
        check_ajax_referer(WMC_TP_Plugin::NONCE_ACTION);
        $result = $this->plugin->fetcher->fetch_and_cache_reviews(true);
        if (!empty($result['success'])) {
            wp_send_json_success(['count' => isset($result['reviews']) ? count((array)$result['reviews']) : 0]);
        }
        wp_send_json_error(['message' => $result['message'] ?? 'Fallo al sincronizar']);
    }
}
