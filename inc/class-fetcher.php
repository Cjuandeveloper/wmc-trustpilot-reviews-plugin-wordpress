<?php
if (!defined('ABSPATH')) exit;

final class WMC_TP_Fetcher {
    private $plugin;
    public function __construct(WMC_TP_Plugin $plugin){ $this->plugin = $plugin; }

    public function fetch_and_cache_reviews($force = false) {
        $cached = get_transient(WMC_TP_Plugin::TRANSIENT_KEY);
        if ($cached && !$force) {
            if (is_array($cached) && isset($cached['reviews'])) {
                return ['success' => true, 'reviews' => $cached['reviews'], 'meta' => ($cached['meta'] ?? [])];
            }
            return ['success' => true, 'reviews' => (array)$cached, 'meta' => []];
        }

        $s = get_option(WMC_TP_Plugin::OPTION_KEY);
        $baseUrl  = trim((string)($s['profile_url'] ?? ''));
        if ($baseUrl === '') {
            return ['success' => false, 'message' => 'Configura la Business Profile URL primero.'];
        }

        $all = [];
        $ids = [];
        $consumersToVisit = [];
        $businessSlug = $this->extract_business_slug($baseUrl);

        $parts  = wp_parse_url($baseUrl);
        $qsBase = [];
        if (!empty($parts['query'])) parse_str((string)$parts['query'], $qsBase);
        $base   = (isset($parts['scheme'])?$parts['scheme'].'://':'') . ($parts['host']??'') . ($parts['path']??'');

        $meta = [];
        $stats_pages = 0;
        $stats_consumers = 0;
        $stats_extra_from_consumers = 0;

        for ($page = 1; $page <= WMC_TP_Plugin::INTERNAL_PAGE_CAP; $page++) {
            $qs = $qsBase;
            if ($page > 1) $qs['page'] = $page;
            $url = $base . ( $qs ? ('?' . http_build_query($qs) ) : '' );

            $body = $this->http_get_body($url, '1.4');
            if ($body === null) { if ($page === 1) return ['success'=>false,'message'=>'No se pudo leer Trustpilot']; break; }

            $stats_pages++;

            if ($page === 1) {
                $meta = $this->parse_business_meta_from_html($body);
            }

            $parsed = $this->parse_reviews_from_html($body, true, $businessSlug);
            if (!empty($parsed['consumer_profiles'])) {
                foreach ($parsed['consumer_profiles'] as $u) {
                    if (count($consumersToVisit) >= WMC_TP_Plugin::INTERNAL_CONSUMER_CAP) break;
                    $consumersToVisit[$u] = true;
                }
            }

            if (empty($parsed['reviews'])) break;

            foreach ($parsed['reviews'] as $r) {
                $rid = $r['id'] ?: md5(($r['title'] ?? '').'|'.($r['text'] ?? '').'|'.($r['date'] ?? '').'|'.($r['author'] ?? ''));
                if (isset($ids[$rid])) continue;
                $ids[$rid] = true;
                $all[] = $this->normalize_review($r);
            }

            if (!$this->html_has_next($body, $page)) break;
        }

        if (!empty($consumersToVisit) && $businessSlug) {
            $visited = 0;
            foreach (array_keys($consumersToVisit) as $consumerUrl) {
                if ($visited >= WMC_TP_Plugin::INTERNAL_CONSUMER_CAP) break;
                $visited++;
                $stats_consumers++;

                for ($p=1; $p<=WMC_TP_Plugin::INTERNAL_CONSUMER_PAGE_CAP; $p++) {
                    $url = $consumerUrl . ($p>1 ? ( (strpos($consumerUrl,'?')!==false?'&':'?') . 'page='.$p ) : '' );
                    $body = $this->http_get_body($url, '1.4C');
                    if ($body === null) break;

                    $extra = $this->parse_consumer_reviews_for_business($body, $businessSlug, $consumerUrl);
                    if (empty($extra)) break;

                    $added_now = 0;
                    foreach ($extra as $r) {
                        $rid = $r['id'] ?: md5(($r['title'] ?? '').'|'.($r['text'] ?? '').'|'.($r['date'] ?? '').'|'.($r['author'] ?? ''));
                        if (isset($ids[$rid])) continue;
                        $ids[$rid] = true;
                        $all[] = $this->normalize_review($r);
                        $added_now++;
                    }

                    $stats_extra_from_consumers += $added_now;
                    if (count($extra) < 2) break;
                }
            }
        }

        if (empty($all)) return ['success' => false, 'message' => 'No fue posible leer reseñas.'];

        $payload = ['reviews' => $all, 'meta' => $meta];
        set_transient(WMC_TP_Plugin::TRANSIENT_KEY, $payload, WMC_TP_Plugin::CACHE_HOURS * HOUR_IN_SECONDS);

        set_transient(WMC_TP_Plugin::STATS_TRANSIENT, [
            'time' => time(),
            'pages' => $stats_pages,
            'consumers' => $stats_consumers,
            'total' => count($all),
            'extra_from_consumers' => $stats_extra_from_consumers,
        ], 6 * HOUR_IN_SECONDS);

        return ['success' => true, 'reviews' => $all, 'meta' => $meta];
    }

    /* ===================== Helpers (idénticos a tu versión) ===================== */

    public function normalize_review(array $r) : array {
        $profileRaw = isset($r['consumer_profile']) ? (string)$r['consumer_profile'] : '';
        $profileNorm = $this->canonicalize_consumer_profile($profileRaw);

        return [
            'id'               => isset($r['id']) ? (string)$r['id'] : '',
            'title'            => wp_strip_all_tags((string)($r['title'] ?? '')),
            'text'             => wp_strip_all_tags((string)($r['text'] ?? '')),
            'rating'           => max(0, min(5, intval($r['rating'] ?? 0))),
            'date'             => substr((string)($r['date'] ?? ''), 0, 10),
            'author'           => wp_strip_all_tags((string)($r['author'] ?? 'Usuario de Trustpilot')),
            'link'             => esc_url_raw((string)($r['link'] ?? '')),
            'avatar'           => esc_url_raw((string)($r['avatar'] ?? '')),
            'consumer_profile' => $profileNorm,
        ];
    }

    private function http_get_body($url, $ver_tag = '0.8') {
        $attempts = 0; $max = 4; $delay = 0.6;
        while ($attempts <= $max) {
            $resp = wp_remote_get($url, [
                'timeout' => 25,
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (WMC Trustpilot Reviews/'.$ver_tag.'; +https://webmastercol.com)',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Referer'         => 'https://es.trustpilot.com/'
                ]
            ]);
            if (is_wp_error($resp)) return null;
            $code = wp_remote_retrieve_response_code($resp);
            if ($code === 200) return wp_remote_retrieve_body($resp);
            if (in_array($code,[429,500,502,503,504],true) && $attempts<$max) {
                $sleep = $delay + (mt_rand(0, 200) / 1000);
                usleep((int)($sleep*1e6));
                $delay *= 1.35;
                $attempts++;
                continue;
            }
            break;
        }
        return null;
    }

    public function extract_business_slug($profileUrl) {
        $p = wp_parse_url($profileUrl);
        $path = $p['path'] ?? '';
        if (preg_match('#/review/([^/]+)#', (string)$path, $m)) return $this->normalize_slug($m[1]);
        return '';
    }
    private function normalize_slug(string $slug) : string { return preg_replace('/^www\./', '', strtolower($slug)); }
    private function slugs_match(string $a, string $b) : bool { return $this->normalize_slug($a) === $this->normalize_slug($b); }
    private function html_has_next(string $html, int $currentPage) : bool {
        if (preg_match('#rel=["\']next["\']#i', $html)) return true;
        if (preg_match('#aria-label=["\']Next#i', $html)) return true;
        if (preg_match('#\?page='.(intval($currentPage)+1).'(\D|["\'])#', $html)) return true;
        return false;
    }

    private function is_valid_consumer_profile(string $url) : bool {
        return (bool)preg_match('#https?://[^/]*trustpilot\\.[^/]+/users/[a-z0-9]#i', $url)
            || (bool)preg_match('#^/users/[a-z0-9]#i', $url);
    }
    private function canonicalize_consumer_profile(string $url) : string {
        if (!$this->is_valid_consumer_profile($url)) return '';
        $p = wp_parse_url($this->normalize_trustpilot_url($url));
        $path = $p['path'] ?? '';
        if (preg_match('#/users/([^/?#]+)#i', $path, $m)) return '/users/' . strtolower($m[1]);
        return '';
    }

    private function parse_business_meta_from_html(string $html) : array {
        $meta = [];

        // JSON-LD
        if (preg_match_all('#<script type="application/ld\\+json">(.*?)</script>#s', $html, $blocks)) {
            foreach ($blocks[1] as $b) {
                $data = json_decode(trim($b), true);
                if (!$data) continue;

                $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : (is_array($data) ? [$data] : []);
                foreach ($nodes as $n) {
                    if (!is_array($n)) continue;
                    $type = $n['@type'] ?? '';

                    if (in_array($type, ['Organization','LocalBusiness','Corporation','Product','WebSite','WebPage'], true)) {
                        if (!empty($n['name']) && empty($meta['name'])) $meta['name'] = (string)$n['name'];
                        if (!empty($n['description']) && empty($meta['description'])) $meta['description'] = (string)$n['description'];
                        if (!empty($n['category']) && empty($meta['category'])) $meta['category'] = (string)$n['category'];

                        if (!empty($n['url']) && empty($meta['url'])) $meta['url'] = (string)$n['url'];

                        if (!empty($n['email']) && empty($meta['email'])) $meta['email'] = (string)$n['email'];
                        if (!empty($n['telephone']) && empty($meta['telephone'])) $meta['telephone'] = (string)$n['telephone'];

                        $addr = $n['address'] ?? [];
                        if (is_array($addr)) {
                            $parts = [];
                            foreach (['streetAddress','addressLocality','addressRegion','postalCode','addressCountry'] as $k) {
                                if (!empty($addr[$k]) && is_string($addr[$k])) $parts[] = trim($addr[$k]);
                            }
                            if (!empty($parts) && empty($meta['address'])) $meta['address'] = implode(', ', array_filter($parts));
                        } elseif (is_string($addr) && empty($meta['address'])) {
                            $meta['address'] = trim($addr);
                        }

                        if (!empty($n['logo'])) {
                            if (is_string($n['logo']) && empty($meta['logo'])) $meta['logo'] = esc_url_raw($n['logo']);
                            if (is_array($n['logo']) && !empty($n['logo']['url']) && empty($meta['logo'])) $meta['logo'] = esc_url_raw($n['logo']['url']);
                        }
                        if (!empty($n['image']) && empty($meta['logo'])) {
                            if (is_string($n['image'])) $meta['logo'] = esc_url_raw($n['image']);
                            if (is_array($n['image']) && !empty($n['image']['url'])) $meta['logo'] = esc_url_raw($n['image']['url']);
                        }
                    }
                }
            }
        }

        // __NEXT_DATA__
        if (preg_match('#<script id="__NEXT_DATA__"[^>]*>(.+?)</script>#s', $html, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                $bu = $this->find_business_unit_node($json);
                if (is_array($bu)) {
                    if (empty($meta['name']) && !empty($bu['displayName']))  $meta['name'] = (string)$bu['displayName'];
                    if (empty($meta['name']) && !empty($bu['name']))         $meta['name'] = (string)$bu['name'];

                    if (empty($meta['description']) && !empty($bu['about']))        $meta['description'] = (string)$bu['about'];
                    if (empty($meta['description']) && !empty($bu['description']))  $meta['description'] = (string)$bu['description'];

                    if (empty($meta['category']) && !empty($bu['categories']) && is_array($bu['categories'])) {
                        $c0 = $bu['categories'][0] ?? [];
                        if (is_array($c0)) {
                            if (!empty($c0['displayName'])) $meta['category'] = (string)$c0['displayName'];
                            elseif (!empty($c0['name']))    $meta['category'] = (string)$c0['name'];
                        } elseif (is_string($c0)) {
                            $meta['category'] = $c0;
                        }
                    }

                    if (empty($meta['logo'])) {
                        $logo =
                            $this->array_path_get($bu, ['imageUrls','logo']) ??
                            $this->array_path_get($bu, ['profileImage']) ??
                            $this->array_path_get($bu, ['images','logo']) ??
                            '';
                        if (is_array($logo) && !empty($logo['url'])) $logo = $logo['url'];
                        if (is_string($logo) && $logo) $meta['logo'] = esc_url_raw($logo);
                    }

                    if (empty($meta['url'])) {
                        $url = $this->array_path_get($bu, ['websiteUrl']) ?? $this->array_path_get($bu, ['links','website','href']);
                        if (is_string($url) && $url) $meta['url'] = esc_url_raw($url);
                    }

                    $contact = $bu['contact'] ?? [];
                    if (is_array($contact)) {
                        if (empty($meta['email']) && !empty($contact['email'])) $meta['email'] = (string)$contact['email'];
                        if (empty($meta['telephone']) && !empty($contact['phoneNumber'])) $meta['telephone'] = (string)$contact['phoneNumber'];
                    }

                    $loc = $bu['location'] ?? [];
                    if (empty($meta['address']) && is_array($loc)) {
                        $parts = [];
                        foreach (['street','address','streetAddress','city','region','state','postalCode','country'] as $k) {
                            if (!empty($loc[$k]) && is_string($loc[$k])) $parts[] = trim($loc[$k]);
                        }
                        $addr = implode(', ', array_filter($parts));
                        if ($addr) $meta['address'] = $addr;
                    }
                }
            }
        }
        return $meta;
    }

    private function find_business_unit_node(array $json) {
        $candidates = [
            ['props','pageProps','businessUnit'],
            ['props','pageProps','profilePageProps','businessUnit'],
            ['query','businessUnit'],
        ];
        foreach ($candidates as $p) {
            $n = $this->array_path_get($json, $p);
            if (is_array($n) && (!empty($n['displayName']) || !empty($n['name']))) return $n;
        }
        $stack = [$json]; $depthGuard = 0;
        while ($stack && $depthGuard < 20000) {
            $node = array_pop($stack); $depthGuard++;
            if (!is_array($node)) continue;
            if (isset($node['businessUnit']) && is_array($node['businessUnit'])) {
                $bu = $node['businessUnit'];
                if (!empty($bu['displayName']) || !empty($bu['name']) || !empty($bu['about']) || !empty($bu['categories'])) {
                    return $bu;
                }
            }
            foreach ($node as $v) if (is_array($v)) $stack[] = $v;
        }
        return null;
    }

    private function parse_reviews_from_html($html, $captureConsumerUrls = false, $businessSlug = '') : array {
        $reviews = [];
        $consumer_profiles = [];

        if (preg_match('#<script id="__NEXT_DATA__"[^>]*>(.+?)</script>#s', $html, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                foreach (WMC_TP_Plugin::NEXT_DATA_PATHS as $path) {
                    $node = $this->array_path_get($json, $path);
                    if (is_array($node) && !empty($node)) {
                        foreach ($node as $r) {
                            if (!is_array($r)) continue;
                            $consumer = isset($r['consumer']) && is_array($r['consumer']) ? $r['consumer'] : [];
                            $avatar   = $consumer['imageUrl'] ?? ($consumer['profileImage'] ?? ($consumer['image']['url'] ?? ''));
                            $profileRaw  = $this->extract_consumer_profile_link($r, $consumer);
                            $profileUrl  = $this->normalize_trustpilot_url((string)$profileRaw);
                            $profileCanon = $this->canonicalize_consumer_profile($profileUrl);

                            if ($captureConsumerUrls) {
                                if ($this->is_valid_consumer_profile($profileUrl)) $consumer_profiles[] = $profileUrl;
                                $cid = $this->find_first_string($consumer, ['id','consumerId','profileId','userId']);
                                if (!$profileCanon && $cid) {
                                    $faux = '/users/' . strtolower(preg_replace('/[^a-z0-9\-_.]/i','',$cid));
                                    $consumer_profiles[] = $this->normalize_trustpilot_url($faux);
                                    $profileCanon = $this->canonicalize_consumer_profile($faux);
                                }
                            }

                            $baseReview = [
                                'id'               => $r['id'] ?? ($r['reviewId'] ?? ''),
                                'title'            => $r['title'] ?? '',
                                'text'             => $r['text'] ?? ($r['reviewText'] ?? ''),
                                'rating'           => intval($r['rating'] ?? ($r['stars'] ?? 0)),
                                'date'             => $r['createdAt'] ?? ($this->array_path_get($r,['dates','publishedDate']) ?? ''),
                                'author'           => $consumer['displayName'] ?? ($r['consumerName'] ?? 'Usuario de Trustpilot'),
                                'link'             => $this->array_path_get($r, ['links','reviewUrl','href']) ?? ($r['reviewUrl'] ?? ''),
                                'avatar'           => $avatar,
                                'consumer_profile' => $profileCanon,
                            ];
                            $reviews[] = $baseReview;

                            $previous = $this->collect_prev_reviews_from_node($r, $consumer, $baseReview['link'], $profileCanon);
                            if (!empty($previous)) foreach ($previous as $pr) $reviews[] = $pr;
                        }
                    }
                }
                if ($captureConsumerUrls) {
                    $moreProfiles = $this->collect_consumer_profiles_from_json($json, $businessSlug);
                    foreach ($moreProfiles as $u) $consumer_profiles[] = $u;
                }
            }
        }

        if (preg_match_all('#<script type="application/ld\\+json">(.*?)</script>#s', $html, $blocks)) {
            foreach ($blocks[1] as $b) {
                $data = json_decode(trim($b), true);
                if (!$data) continue;
                $list = (is_array($data) && isset($data['@type'])) ? [$data]
                      : ((isset($data['@graph']) && is_array($data['@graph'])) ? $data['@graph'] : []);
                foreach ($list as $node) {
                    if (!is_array($node) || ($node['@type'] ?? '') !== 'Review') continue;
                    $reviews[] = [
                        'id'     => $node['@id'] ?? '',
                        'title'  => $node['name'] ?? '',
                        'text'   => $node['reviewBody'] ?? '',
                        'rating' => intval($node['reviewRating']['ratingValue'] ?? 0),
                        'date'   => $node['datePublished'] ?? '',
                        'author' => is_array($node['author']) ? ($node['author']['name'] ?? 'Usuario de Trustpilot') : ($node['author'] ?? 'Usuario de Trustpilot'),
                        'link'   => $node['url'] ?? '',
                        'avatar' => '',
                        'consumer_profile' => '',
                    ];
                }
            }
        }

        if ($captureConsumerUrls) {
            if (preg_match_all('#href=["\'](/users/[a-z0-9][^"\']*)["\']#i', $html, $mm)) {
                foreach ($mm[1] as $rel) $consumer_profiles[] = $this->normalize_trustpilot_url($rel);
            }
            if (preg_match_all('#href=["\']https?://[^"\']*/users/[a-z0-9][^"\']*["\']#i', $html, $ma)) {
                foreach ($ma[0] as $tag) if (preg_match('#href=["\'](https?://[^"\']*/users/[a-z0-9][^"\']*)["\']#i', $tag, $m2)) $consumer_profiles[] = $this->normalize_trustpilot_url($m2[1]);
            }
        }

        $consumer_profiles = array_values(array_unique(array_filter($consumer_profiles)));
        return ['reviews' => $reviews, 'consumer_profiles' => $consumer_profiles];
    }

    private function extract_consumer_profile_link(array $reviewNode, array $consumerNode) : string {
        $cands = [
            $consumerNode['profileUrl'] ?? null,
            $this->array_path_get($consumerNode, ['links','profileUrl','href']),
            $this->array_path_get($reviewNode, ['links','consumer','profile','href']),
            $this->array_path_get($reviewNode, ['links','consumer','href']),
            $this->array_path_get($reviewNode, ['links','profileUrl','href']),
        ];
        foreach ($cands as $c) if (is_string($c) && $c) return $c;
        return '';
    }

    private function collect_prev_reviews_from_node(array $node, array $consumer, string $fallbackLink = '', string $consumerProfileCanon = '') : array {
        $acc = [];
        $lists = [];

        foreach (['previousReviews','olderReviews','reviewsHistory','history'] as $k) {
            if (!empty($node[$k]) && is_array($node[$k])) $lists[] = $node[$k];
        }
        $maybe = $this->array_path_get($node, ['reviewContext','previousReviews']);
        if (is_array($maybe)) $lists[] = $maybe;
        foreach ($node as $v) if (is_array($v) && $this->is_probably_reviews_list($v)) $lists[] = $v;

        foreach ($lists as $list) {
            foreach ($list as $r) {
                if (!$this->looks_like_review($r)) continue;
                $acc[] = [
                    'id'               => $r['id'] ?? ($r['reviewId'] ?? ''),
                    'title'            => $r['title'] ?? '',
                    'text'             => $r['text'] ?? ($r['reviewText'] ?? ($r['content'] ?? ($r['reviewBody'] ?? ''))),
                    'rating'           => intval($r['rating'] ?? ($r['stars'] ?? ($this->array_path_get($r,['reviewRating','ratingValue']) ?? 0))),
                    'date'             => $r['createdAt'] ?? ($this->array_path_get($r,['dates','publishedDate']) ?? ''),
                    'author'           => $consumer['displayName'] ?? 'Usuario de Trustpilot',
                    'link'             => $this->array_path_get($r,['links','reviewUrl','href']) ?? ($r['reviewUrl'] ?? $fallbackLink),
                    'avatar'           => $consumer['imageUrl'] ?? ($consumer['profileImage'] ?? ($consumer['image']['url'] ?? '')),
                    'consumer_profile' => $consumerProfileCanon,
                ];
            }
        }
        return $acc;
    }

    private function is_probably_reviews_list(array $arr) : bool {
        $found = 0; $checked = 0;
        foreach ($arr as $it) { if (!is_array($it)) continue; $checked++; if ($this->looks_like_review($it)) $found++; if ($checked>=6) break; }
        return $found >= 2;
    }
    private function looks_like_review($n) : bool {
        if (!is_array($n)) return false;
        $hasText   = isset($n['text']) || isset($n['reviewText']) || isset($n['content']) || isset($n['reviewBody']);
        $hasRating = isset($n['rating']) || isset($n['stars']) || (isset($n['reviewRating']['ratingValue']));
        return $hasText && $hasRating;
    }

    public function normalize_trustpilot_url($url) {
        if (strpos($url, '//') === false) {
            $url = 'https://www.trustpilot.com' . (strpos($url,'/')===0 ? '' : '/') . $url;
        } elseif (strpos($url, 'http') !== 0) {
            $url = 'https:' . (strpos($url,'//')===0 ? '' : '//') . $url;
        }
        return preg_replace('#https://www\\.trustpilot\\.com#','https://es.trustpilot.com',(string)$url);
    }

    private function parse_consumer_reviews_for_business($html, $businessSlug, $consumerUrl = '') : array {
        $out = [];
        if (!$businessSlug) return $out;
        $consumerCanon = $this->canonicalize_consumer_profile($consumerUrl);

        if (preg_match('#<script id="__NEXT_DATA__"[^>]*>(.+?)</script>#s', $html, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                $bucket = [];
                $this->json_collect_business_reviews($json, $businessSlug, $bucket, $consumerCanon);
                if (!empty($bucket)) return $bucket;
            }
        }

        if (preg_match_all('#<script type="application/ld\\+json">(.*?)</script>#s', $html, $blocks)) {
            foreach ($blocks[1] as $b) {
                $data = json_decode(trim($b), true);
                if (!$data) continue;
                $list = (isset($data['@graph']) && is_array($data['@graph'])) ? $data['@graph'] : [$data];
                foreach ($list as $node) {
                    if (!is_array($node) || ($node['@type'] ?? '') !== 'Review') continue;
                    if (!$this->node_mentions_business($node, $businessSlug)) continue;

                    $out[] = [
                        'id'               => $node['@id'] ?? '',
                        'title'            => $node['name'] ?? '',
                        'text'             => $node['reviewBody'] ?? '',
                        'rating'           => intval($node['reviewRating']['ratingValue'] ?? 0),
                        'date'             => $node['datePublished'] ?? '',
                        'author'           => is_array($node['author']) ? ($node['author']['name'] ?? 'Usuario de Trustpilot') : ($node['author'] ?? 'Usuario de Trustpilot'),
                        'link'             => $node['url'] ?? '',
                        'avatar'           => '',
                        'consumer_profile' => $consumerCanon,
                    ];
                }
            }
            if (!empty($out)) return $out;
        }

        $pattern = '#<a[^>]+href="([^"]*/review/(?:www\.)?'.preg_quote($businessSlug,'#').'[^"]*)"[^>]*>.*?</a>(.{0,1400})#si';
        if (preg_match_all($pattern, $html, $mm)) {
            foreach ($mm[2] as $i => $near) {
                $bizLink = $this->normalize_trustpilot_url($mm[1][$i]);
                $rating = 0;
                if (preg_match('#([0-5])\s*estrell#i', $near, $mr)) $rating = intval($mr[1]);
                $title = '';
                if (preg_match('#<a[^>]*>([^<]{4,160})</a>#si', $near, $mt)) $title = trim(wp_strip_all_tags($mt[1]));
                $text = trim(wp_strip_all_tags(strip_tags($near)));
                if (!$text && preg_match('#<p[^>]*>(.*?)</p>#si', $near, $mp)) $text = trim(wp_strip_all_tags($mp[1]));

                $out[] = [
                    'id'               => '',
                    'title'            => $title,
                    'text'             => $text,
                    'rating'           => $rating,
                    'date'             => '',
                    'author'           => 'Usuario de Trustpilot',
                    'link'             => $bizLink,
                    'avatar'           => '',
                    'consumer_profile' => $consumerCanon,
                ];
            }
        }
        return $out;
    }

    private function json_collect_business_reviews($node, string $businessSlug, array &$acc, string $consumerCanon = '') {
        if (is_array($node)) {
            if ($this->looks_like_review($node) && $this->node_mentions_business($node, $businessSlug)) {
                $consumer = $node['consumer'] ?? [];
                $avatar   = is_array($consumer) ? ($consumer['imageUrl'] ?? ($consumer['profileImage'] ?? ($consumer['image']['url'] ?? ''))) : '';
                $acc[] = [
                    'id'               => $node['id'] ?? ($node['reviewId'] ?? ''),
                    'title'            => $node['title'] ?? '',
                    'text'             => $node['text'] ?? ($node['reviewText'] ?? ($node['content'] ?? ($node['reviewBody'] ?? ''))),
                    'rating'           => intval($node['rating'] ?? ($node['stars'] ?? ($this->array_path_get($node,['reviewRating','ratingValue']) ?? 0))),
                    'date'             => $node['createdAt'] ?? ($this->array_path_get($node,['dates','publishedDate']) ?? ''),
                    'author'           => is_array($consumer) ? ($consumer['displayName'] ?? 'Usuario de Trustpilot') : 'Usuario de Trustpilot',
                    'link'             => $this->array_path_get($node, ['links','reviewUrl','href']) ?? ($node['reviewUrl'] ?? ''),
                    'avatar'           => $avatar,
                    'consumer_profile' => $consumerCanon,
                ];
            }
            foreach ($node as $v) $this->json_collect_business_reviews($v, $businessSlug, $acc, $consumerCanon);
        }
    }

    private function node_mentions_business(array $node, string $slug) : bool {
        $slug = $this->normalize_slug($slug);

        $allStrings = $this->collect_all_strings($node);
        if ($this->strings_mention_slug($allStrings, $slug)) return true;

        $cands = [
            $this->array_path_get($node, ['links','reviewUrl','href']),
            $this->array_path_get($node, ['links','companyProfileUrl','href']),
            $this->array_path_get($node, ['businessUnit','urls','profileUrl']),
            $this->array_path_get($node, ['businessUnit','profileUrl']),
            $this->array_path_get($node, ['company','profileUrl']),
        ];
        foreach ($cands as $u) {
            if (is_string($u) && $u && strpos(strtolower($u), '/review/') !== false) {
                $p = wp_parse_url($u);
                if (!empty($p['path']) && preg_match('#/review/([^/?#]+)#i', $p['path'], $m)) {
                    if ($this->slugs_match($m[1], $slug)) return true;
                }
            }
        }
        return false;
    }

    private function collect_all_strings($node, int $depth = 0, int $maxDepth = 8, array &$out = []) : array {
        if ($depth > $maxDepth) return $out;
        if (is_string($node)) { $out[] = $node; return $out; }
        if (is_array($node)) foreach ($node as $v) $this->collect_all_strings($v, $depth+1, $maxDepth, $out);
        return $out;
    }
    private function strings_mention_slug(array $strings, string $slug) : bool {
        $slug = $this->normalize_slug($slug);
        $re = '#/review/(?:www\.)?'.preg_quote($slug,'#').'(\b|[/?&"\'#])#i';
        foreach ($strings as $s) {
            if (!is_string($s) || $s === '') continue;
            $ls = strtolower($s);
            if (strpos($ls, $slug) !== false) return true;
            if (preg_match($re, $ls)) return true;
        }
        return false;
    }
    private function array_path_get($array, array $path) {
        $n = $array;
        foreach ($path as $p) { if (is_array($n) && array_key_exists($p,$n)) { $n = $n[$p]; } else { return null; } }
        return $n;
    }
    private function find_first_string(array $a, array $keys) : string {
        foreach ($keys as $k) if (!empty($a[$k]) && is_string($a[$k])) return (string)$a[$k];
        return '';
    }

    private function collect_consumer_profiles_from_json(array $json, string $businessSlug) : array {
        $out = [];
        $stack = [$json];
        while ($stack) {
            $n = array_pop($stack);
            if (!is_array($n)) continue;

            if ($this->looks_like_review($n) && (isset($n['consumer']) || isset($n['reviewer']))) {
                $consumer = is_array($n['consumer'] ?? null) ? $n['consumer'] : (is_array($n['reviewer'] ?? null) ? $n['reviewer'] : []);
                $profile  = $this->extract_consumer_profile_link($n, $consumer);
                if ($profile && $this->is_valid_consumer_profile($profile)) {
                    $out[] = $this->normalize_trustpilot_url($profile);
                } else {
                    $cid = $this->find_first_string($consumer, ['id','consumerId','profileId','userId']);
                    if ($cid) $out[] = $this->normalize_trustpilot_url('/users/'.strtolower(preg_replace('/[^a-z0-9\-_.]/i','',$cid)));
                }
            }
            foreach ($n as $v) if (is_array($v)) $stack[] = $v;
        }
        return array_values(array_unique(array_filter($out)));
    }
}
