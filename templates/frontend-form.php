<?php defined('ABSPATH') || exit; ?>
<div class="wrap">
    <p>
        Der nächste Elternsprechtag findet am <?php
	        echo esc_html( flz_ui_format_date( $nextEST, $nextEST ) );
        ?> statt.
    </p>
    <div class="flzest-frontend-form">
        <?php
	        echo flzest_render_step1( $selected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template nutzt flz_ui-Renderer und escaped dynamische Texte selbst.
        ?>

    </div>
</div>
