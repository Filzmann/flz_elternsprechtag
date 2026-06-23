<?php

function flzest_settings_page(): void
{
	if ( isset( $_POST['settings'] ) )
	{
		foreach ($_POST['settings'] as $id => $value)
		{
			$value = sanitize_text_field($value);
			$setting=FlzEstSetting::get_by_id($id);
			$setting->value=$value;
			$setting->save();
		}
	}
	$settings = FlzEstSetting::get_all();
	include( plugin_dir_path( __FILE__ ) . '../templates/settings.php' );
}