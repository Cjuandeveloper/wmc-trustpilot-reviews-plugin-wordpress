<?php
if (!defined('ABSPATH')) exit;

final class WMC_TP_Render {
    private $plugin;
    public function __construct(WMC_TP_Plugin $plugin){ $this->plugin = $plugin; }

    /* ===== Helpers de datos ===== */

    /** Agrupa reviews por perfil canónico (/users/<id>) o por nombre si no lo hay */
    public function group_reviews_by_consumer(array $items) : array {
        $buckets = [];
        foreach ($items as $r) {
            $key = (!empty($r['consumer_profile'])) ? ('cp:' . $r['consumer_profile'])
                 : ('name:' . preg_replace('/\s+/', ' ', mb_strtolower(trim($r['author']))));
            if (!isset($buckets[$key])) $buckets[$key] = [];
            $buckets[$key][] = $r;
        }
        foreach ($buckets as &$list) {
            usort($list, function($a,$b){
                $da = strtotime($a['date'] ?: '1970-01-01');
                $db = strtotime($b['date'] ?: '1970-01-01');
                return $db <=> $da;
            });
        }
        return $buckets;
    }

    public function compute_summary_from_items(array $items) : array {
        $total = count($items);
        $sum = 0;
        $dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
        foreach ($items as $r) {
            $rating = max(0,min(5,intval($r['rating'] ?? 0)));
            if ($rating>=1 && $rating<=5) $dist[$rating]++;
            $sum += $rating;
        }
        $rating = $total ? round($sum / $total, 1) : null;
        return ['rating'=>$rating, 'total'=>$total, 'distribution'=>$dist];
    }

    /* ===== Helpers UI ===== */

    public function render_stars($rating) {
        $rating = max(0, min(5, intval($rating)));
        $out = '';
        for ($i=1; $i<=5; $i++) {
            $filled = $i <= $rating ? 'filled' : '';
            $out .= '<span class="wmc-star '.$filled.'" aria-hidden="true">★</span>';
        }
        return $out;
    }
    public function get_initials($name) {
        $parts = preg_split('/\s+/', trim((string)$name));
        $i = '';
        if (!empty($parts[0])) $i .= mb_strtoupper(mb_substr($parts[0],0,1));
        if (!empty($parts[1])) $i .= mb_strtoupper(mb_substr($parts[1],0,1));
        return $i ?: 'U';
    }

    /* ===== Fragmentos de UI reutilizados ===== */

    public function render_business_header(array $meta, string $profileUrl) : string {
        $name   = $meta['name'] ?? '';
        $cat    = $meta['category'] ?? '';
        $desc   = $meta['description'] ?? '';
        $logo   = $meta['logo'] ?? '';

        $email = $meta['email'] ?? '';
        $tel   = $meta['telephone'] ?? '';
        $addr  = $meta['address'] ?? '';

        $evaluateUrl = $this->build_write_review_url($profileUrl);
        $viewUrl     = $this->normalize_trustpilot_url($profileUrl);

        ob_start(); ?>
        <div class="wmc-tp-bizcard">
            <div class="wmc-tp-bizcard__row">
                <?php if ($logo): ?>
                    <div class="wmc-tp-bizcard__logo-wrap">
                        <img class="wmc-tp-bizcard__logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name ?: 'Logo'); ?>" loading="lazy">
                    </div>
                <?php endif; ?>
                <div class="wmc-tp-bizcard__main">
                    <?php if ($name): ?><h2 class="wmc-tp-bizcard__name"><?php echo esc_html($name); ?></h2><?php endif; ?>
                    <?php if ($cat): ?><div class="wmc-tp-bizcard__cat"><?php echo esc_html($cat); ?></div><?php endif; ?>

                    <div class="wmc-tp-bizcard__cta">
                        <?php if ($evaluateUrl): ?>
                            <a class="wmc-tp-btn wmc-btn-write" href="<?php echo esc_url($evaluateUrl); ?>" target="_blank" rel="nofollow noopener">
                                <span class="wmc-tp-btn__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                      <path d="M3 17.25V21h3.75L17.81 9.94a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0L3 17.25zM20.71 7.04a2.5 2.5 0 0 0 0-3.54l-1.21-1.21a2.5 2.5 0 0 0-3.54 0L14.78 3.47l4.75 4.75 1.18-1.18z"/>
                                    </svg>
                                </span>
                                <?php esc_html_e('Escribir una opinión','wmc-trustpilot-reviews'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($viewUrl): ?>
                            <a class="wmc-tp-btn wmc-btn-view" href="<?php echo esc_url($viewUrl); ?>" target="_blank" rel="nofollow noopener">
                                <span class="wmc-tp-btn__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false">
                                      <path d="M12 2l2.92 5.92L21 9.27l-4.5 4.38L17.84 21 12 17.77 6.16 21l1.34-7.35L3 9.27l6.08-1.35L12 2z"/>
                                    </svg>
                                </span>
                                <?php esc_html_e('Ver en Trustpilot','wmc-trustpilot-reviews'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($desc): ?>
                        <p class="wmc-tp-bizcard__desc"><?php echo esc_html($desc); ?></p>
                    <?php endif; ?>

                    <?php if ($email || $tel || $addr): ?>
                        <ul class="wmc-tp-bizcard__contact">
                            <?php if ($email): ?><li><strong><?php esc_html_e('Email:','wmc-trustpilot-reviews'); ?></strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li><?php endif; ?>
                            <?php if ($tel): ?><li><strong><?php esc_html_e('Teléfono:','wmc-trustpilot-reviews'); ?></strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/','',$tel)); ?>"><?php echo esc_html($tel); ?></a></li><?php endif; ?>
                            <?php if ($addr): ?><li><strong><?php esc_html_e('Dirección:','wmc-trustpilot-reviews'); ?></strong> <?php echo esc_html($addr); ?></li><?php endif; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_summary(array $meta) : string {
        $rating = isset($meta['rating']) ? floatval($meta['rating']) : null;
        $total  = isset($meta['total_reviews']) ? intval($meta['total_reviews']) : null;
        $dist   = $meta['distribution'] ?? [5=>0,4=>0,3=>0,2=>0,1=>0];
        $sum    = array_sum($dist);
        if ($total && !$sum) $sum = $total;

        ob_start(); ?>
        <div class="wmc-tp-summary">
            <div class="wmc-tp-score">
                <div class="wmc-tp-score__value"><?php echo $rating !== null ? esc_html(number_format($rating,1)) : '-'; ?></div>
                <div class="wmc-tp-score__stars" aria-label="<?php echo $rating !== null ? esc_attr($rating) : ''; ?> estrellas">
                    <?php echo $this->render_stars(round($rating ?? 0)); ?>
                </div>
                <?php if ($total !== null): ?>
                    <div class="wmc-tp-score__count"><?php echo esc_html($total); ?> <?php esc_html_e('opiniones','wmc-trustpilot-reviews'); ?></div>
                <?php endif; ?>
            </div>
            <div class="wmc-tp-bars">
                <?php for ($star=5; $star>=1; $star--):
                    $c = intval($dist[$star] ?? 0);
                    $pct = $sum > 0 ? round($c * 100 / $sum) : 0; ?>
                    <div class="wmc-tp-bar">
                        <span class="wmc-tp-bar__label"><?php echo esc_html($star); ?>★</span>
                        <span class="wmc-tp-bar__track"><span class="wmc-tp-bar__fill" style="width:<?php echo esc_attr($pct); ?>%"></span></span>
                        <span class="wmc-tp-bar__value"><?php echo esc_html($c); ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function build_write_review_url(string $profileUrl) : string {
        $slug = $this->plugin->fetcher->extract_business_slug($profileUrl);
        if ($slug) return 'https://es.trustpilot.com/evaluate/' . rawurlencode($slug);
        return $this->normalize_trustpilot_url($profileUrl);
    }

    public function normalize_trustpilot_url($url) {
        if (strpos($url, '//') === false) {
            $url = 'https://www.trustpilot.com' . (strpos($url,'/')===0 ? '' : '/') . $url;
        } elseif (strpos($url, 'http') !== 0) {
            $url = 'https:' . (strpos($url,'//')===0 ? '' : '//') . $url;
        }
        return preg_replace('#https://www\\.trustpilot\\.com#','https://es.trustpilot.com',(string)$url);
    }
}
