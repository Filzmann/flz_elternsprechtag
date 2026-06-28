
<div class="wrap">
    <p>
        Der nächste Elternsprechtag findet am <?php
	        echo esc_html( flz_ui_format_date( $nextEST, $nextEST ) );
        ?> statt.
    </p>
    <div class="flzest-frontend-form">
        <?php
	        echo step1( $selected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- step1 nutzt flz_ui-Renderer und escaped dynamische Texte selbst.
        ?>

    </div>
</div>
