<?php
/** @var array $meta */
/** @var string $profile_url */
/** @var WMC_TP_Render $render */
?>
<section class="wmc-tp-header">
    <?php echo $render->render_business_header($meta, $profile_url); ?>
    <?php echo $render->render_summary($meta); ?>
</section>
