
<div class="wrap">
    <p>
        Der nächste Elternsprechtag findet am <?php
	        echo esc_html( $nextEST );
        ?> statt.
    </p>
    <div style="display: inline-block; width: 100%; overflow: auto; ">
        <?php
	        echo wp_kses_post( step1( $selected ) );
        ?>

    </div>
</div>
