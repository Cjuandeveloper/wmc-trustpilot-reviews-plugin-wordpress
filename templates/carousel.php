<?php
/** @var array $groups */
/** @var bool  $showAvatar */
/** @var WMC_TP_Render $render */
/** @var int   $autoplayMs */
if (!defined('ABSPATH')) exit;
?>
<div class="wmc-tp-carousel" data-autoplay="<?php echo esc_attr($autoplayMs); ?>">
    <div class="wmc-tp-viewport">
        <!-- IMPORTANTE: no usar clase .wmc-tp aquí para evitar el grid -->
        <div class="wmc-tp-track" data-track>
            <?php foreach ($groups as $list):
                if (empty($list)) continue;
                $main  = $list[0];
                $older = array_slice($list, 1);
                $initials = $render->get_initials($main['author']); ?>
                <div class="wmc-tp-slide">
                    <article class="wmc-tp-card" itemscope itemtype="https://schema.org/Review">
                        <meta itemprop="author" content="<?php echo esc_attr($main['author']); ?>">
                        <?php if (!empty($main['date'])): ?><meta itemprop="datePublished" content="<?php echo esc_attr($main['date']); ?>"><?php endif; ?>
                        <div itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                            <meta itemprop="ratingValue" content="<?php echo esc_attr($main['rating']); ?>">
                        </div>

                        <div class="wmc-tp-card__header">
                            <div class="wmc-tp-left">
                                <?php if ($showAvatar): ?>
                                    <div class="wmc-tp-avatar" aria-hidden="<?php echo empty($main['avatar']) ? 'false' : 'true'; ?>">
                                        <?php if (!empty($main['avatar'])): ?>
                                            <img src="<?php echo esc_url($main['avatar']); ?>" alt="<?php echo esc_attr($main['author']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <span><?php echo esc_html($initials); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="wmc-tp-meta">
                                    <div class="wmc-tp-stars" aria-label="<?php echo esc_attr($main['rating']); ?> estrellas">
                                        <?php echo $render->render_stars($main['rating']); ?>
                                    </div>
                                    <span class="wmc-tp-author"><?php echo esc_html($main['author']); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($main['date'])): ?>
                                <time class="wmc-tp-date" datetime="<?php echo esc_attr($main['date']); ?>">
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($main['date']))); ?>
                                </time>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($main['title'])): ?><h3 class="wmc-tp-title"><?php echo esc_html($main['title']); ?></h3><?php endif; ?>
                        <?php if (!empty($main['text'])): ?><p class="wmc-tp-text" itemprop="reviewBody"><?php echo esc_html($main['text']); ?></p><?php endif; ?>

                        <footer class="wmc-tp-footer">
                            <?php if (!empty($main['link'])): ?>
                                <a class="wmc-tp-link" href="<?php echo esc_url($main['link']); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e('Ver en Trustpilot','wmc-trustpilot-reviews'); ?></a>
                            <?php endif; ?>
                        </footer>

                        <?php if (!empty($older)): ?>
                            <details class="wmc-tp-more">
                                <summary><?php printf(esc_html__('Ver %1$d opinión(es) más de este usuario','wmc-trustpilot-reviews'), count($older)); ?></summary>
                                <?php foreach ($older as $or): ?>
                                    <div class="wmc-tp-older" itemscope itemtype="https://schema.org/Review">
                                        <meta itemprop="author" content="<?php echo esc_attr($main['author']); ?>">
                                        <?php if (!empty($or['date'])): ?><meta itemprop="datePublished" content="<?php echo esc_attr($or['date']); ?>"><?php endif; ?>
                                        <div itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                                            <meta itemprop="ratingValue" content="<?php echo esc_attr($or['rating']); ?>">
                                        </div>

                                        <div class="wmc-tp-older__head">
                                            <div class="wmc-tp-stars" aria-label="<?php echo esc_attr($or['rating']); ?> estrellas">
                                                <?php echo $render->render_stars($or['rating']); ?>
                                            </div>
                                            <?php if (!empty($or['date'])): ?>
                                                <time class="wmc-tp-date" datetime="<?php echo esc_attr($or['date']); ?>">
                                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($or['date']))); ?>
                                                </time>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($or['title'])): ?><h4 class="wmc-tp-title"><?php echo esc_html($or['title']); ?></h4><?php endif; ?>
                                        <?php if (!empty($or['text'])): ?><p class="wmc-tp-text" itemprop="reviewBody"><?php echo esc_html($or['text']); ?></p><?php endif; ?>
                                        <?php if (!empty($or['link'])): ?><a class="wmc-tp-link" href="<?php echo esc_url($or['link']); ?>" target="_blank" rel="nofollow noopener"><?php esc_html_e('Ver en Trustpilot','wmc-trustpilot-reviews'); ?></a><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </details>
                        <?php endif; ?>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
