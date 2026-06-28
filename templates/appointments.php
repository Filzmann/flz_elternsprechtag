<?php
$flzest_ui = flz_ui();
$flzest_parent_gender_options = array(
	'm' => 'Herr',
	'f' => 'Frau',
	''  => 'keine Angabe',
);

$flzest_appointment_sort_link = static function ( string $label, string $sort ) use ( $flzest_ui, $appointment_orderby, $appointment_order, $appointment_base_args ): string {
	return $flzest_ui->admin_table_sort_link(
		array(
			'label'         => $label,
			'sort'          => $sort,
			'current_sort'  => $appointment_orderby,
			'current_order' => $appointment_order,
			'url_args'      => $appointment_base_args,
		)
	);
};

$flzest_appointment_row = static function ( FlzEstAppointment $appointment ) use ( $flzest_ui, $flzest_parent_gender_options ): string {
	$teacher_label = $appointment->teacher
		? trim( $appointment->teacher->get_gender_as_anrede() . ' ' . $appointment->teacher->name )
		: '';
	$parent = $appointment->parent ?? null;
	$parent_summary = $parent
		? trim( (string) $parent->name . ', ' . (string) $parent->firstName . ' (' . (string) $parent->studentName . ' - ' . (string) $parent->studentClass . ')' )
		: 'Freier Slot';
	$clear_action = '';

	$clear_action .= $flzest_ui->action_form_button(
		array(
			'preset' => 'clear',
			'label'  => 'Termin leeren',
			'button' => array( 'type' => 'submit' ),
			'method' => 'post',
			'nonce'  => 'flzest_admin_action',
			'hidden' => array( 'appointment_empty[id]' => $appointment->id ),
		)
	);

	return $flzest_ui->editable_row(
		array(
			'id'    => 'flzest-appointment-' . (int) $appointment->id,
			'attrs' => array(
				'data-flzest-appointment-row' => true,
				'data-flzest-teacher'         => $teacher_label,
			),
			'form'  => array(
				'method' => 'post',
				'nonce'  => 'flzest_admin_action',
				'hidden' => array( 'id' => $appointment->id ),
			),
			'cells' => array(
				array( 'view' => date( 'H:i', $appointment->start ) ),
				array( 'view' => date( 'H:i', $appointment->end ) ),
				array(
					'view'  => $teacher_label,
					'attrs' => array( 'class' => 'lehrkraft' ),
				),
				array( 'view' => $parent_summary ),
			),
			'details' => array(
				'label'  => 'Buchung bearbeiten',
				'fields' => array(
					array(
						'type'    => 'select',
						'name'    => 'parent[gender]',
						'label'   => 'Anrede',
						'value'   => $parent->gender ?? '',
						'options' => $flzest_parent_gender_options,
					),
					array(
						'type'  => 'text',
						'name'  => 'parent[name]',
						'label' => 'Name',
						'value' => $parent->name ?? '',
					),
					array(
						'type'  => 'text',
						'name'  => 'parent[firstName]',
						'label' => 'Vorname',
						'value' => $parent->firstName ?? '',
					),
					array(
						'type'         => 'email',
						'name'         => 'parent[email]',
						'label'        => 'E-Mail',
						'value'        => $parent->email ?? '',
						'autocomplete' => 'email',
					),
					array(
						'type'  => 'text',
						'name'  => 'parent[studentName]',
						'label' => 'Name Schüler*in',
						'value' => $parent->studentName ?? '',
					),
					array(
						'type'  => 'text',
						'name'  => 'parent[studentClass]',
						'label' => 'Klasse Schüler*in',
						'value' => $parent->studentClass ?? '',
					),
				),
			),
			'edit'  => array( 'label' => 'Termin bearbeiten' ),
			'save'  => array(
				'label' => 'Buchung speichern',
				'attrs' => array( 'name' => 'submit' ),
			),
			'extra_actions' => $clear_action,
		)
	);
};
?>

<div class="wrap">
	<h1>Elternsprechtag</h1>
	<div>
		<h2>Neuen Elternsprechtag vorbereiten</h2>
		<?php
		$nextDate = strtotime( FlzEstSetting::get_value_by_name( 'NextParentsDay' ) );
		if ( $nextDate <= strtotime( 'tomorrow' ) ) {
			echo 'Zur Vorbereitung des nächsten Elternsprechtages definieren Sie bitte im <a href="?page=flzest_settings">Einstellungsbereich</a> ein Datum in der Zukunft!';
		} else {
			?>
			<p>
				Der nächste Elternsprechtag findet am <?php echo esc_html( flz_ui_format_date( FlzEstSetting::get_value_by_name( 'NextParentsDay' ), FlzEstSetting::get_value_by_name( 'NextParentsDay' ) ) ); ?>
				von <?php echo esc_html( FlzEstSetting::get_value_by_name( 'ParentsDayBegin' ) ); ?> Uhr
				bis <?php echo esc_html( FlzEstSetting::get_value_by_name( 'ParentsDayEnd' ) ); ?> Uhr statt.
			</p>
			<p>
				Bitte überprüfen Sie diese Einstellungen im Bereich <a href="?page=flzest_settings">"Einstellungen"</a> und passen Sie diese ggf. an bevor Sie mit dem nächsten Schritt fortfahren.<br/>
				Des Weiteren sollte die Liste der <a href="?page=flzest_teachers">Lehrer:innen</a> auf Vollständigkeit überprüft werden.
			</p>
			<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
				<?php echo $flzest_ui->button_reset( array( 'label' => 'Neuen Elternsprechtag vorbereiten', 'confirm' => 'Achtung! Sie sind dabei, alle Buchungen zurückzusetzen. Ist das erwünscht?', 'attrs' => array( 'name' => 'newEST', 'id' => 'newEST' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die CSV-Komponente inklusive URL, Labels und Formularfeldern.
			echo $flzest_ui->csv_panel(
				array(
					'title'       => 'Buchungen und Termine CSV',
					'description' => 'Download der Buchungen oder Sammel-Upload zur Vorbelegung. Lehrkräfte werden über ihre E-Mail-Adresse erkannt.',
					'format'      => 'Email Lehrer; Beginn; Name Schüler:in; Klasse Schüler:in',
					'export'      => array(
						'href'  => $csvFile,
						'label' => 'Buchungen-CSV herunterladen',
					),
					'upload'      => array(
						'nonce'        => 'flzest_admin_action',
						'file_name'    => 'appointments-csv',
						'file_id'      => 'appointments-csv',
						'button_label' => 'Termine-CSV hochladen',
					),
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<table id="appointmentTable" class="widefat striped flz-ui-admin-table">
				<thead>
					<tr>
						<th><?php echo $flzest_appointment_sort_link( 'Beginn', 'start' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
						<th><?php echo $flzest_appointment_sort_link( 'Ende', 'end' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
						<th>
							<div class="flz-ui-table-head">
								<?php echo $flzest_appointment_sort_link( 'Lehrkraft', 'teacher' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
								<form method="get" class="flz-ui-table-filter-form">
									<input type="hidden" name="page" value="flzest_appointments" />
									<input type="hidden" name="orderby" value="<?php echo esc_attr( $appointment_orderby ); ?>" />
									<input type="hidden" name="order" value="<?php echo esc_attr( $appointment_order ); ?>" />
									<?php if ( 'all' !== $appointment_booking_filter ) : ?>
										<input type="hidden" name="booking_filter" value="<?php echo esc_attr( $appointment_booking_filter ); ?>" />
									<?php endif; ?>
									<?php echo $flzest_ui->input( 'text', array( 'name' => 'teacher_filter', 'id' => 'flzest-teacher-filter', 'label' => 'Lehrkraft-Nachname filtern', 'value' => $appointment_teacher_filter, 'placeholder' => 'Nachname' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
									<?php echo $flzest_ui->button_filter( array( 'label' => 'Lehrkraftfilter anwenden' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
								</form>
							</div>
						</th>
						<th>
							<div class="flz-ui-table-head">
								<?php echo $flzest_appointment_sort_link( 'Elternteil', 'parent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
								<form method="get" class="flz-ui-table-filter-form">
									<input type="hidden" name="page" value="flzest_appointments" />
									<input type="hidden" name="orderby" value="<?php echo esc_attr( $appointment_orderby ); ?>" />
									<input type="hidden" name="order" value="<?php echo esc_attr( $appointment_order ); ?>" />
									<?php if ( '' !== $appointment_teacher_filter ) : ?>
										<input type="hidden" name="teacher_filter" value="<?php echo esc_attr( $appointment_teacher_filter ); ?>" />
									<?php endif; ?>
									<?php echo $flzest_ui->field( array( 'type' => 'select', 'name' => 'booking_filter', 'label' => 'Belegung filtern', 'value' => $appointment_booking_filter, 'options' => array( 'all' => 'alle', 'booked' => 'belegt', 'free' => 'frei' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
									<?php echo $flzest_ui->button_filter( array( 'label' => 'Belegung filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
								</form>
							</div>
						</th>
						<th>Aktionen</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $appointments as $appointment ) : ?>
						<?php echo $flzest_appointment_row( $appointment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( '' !== $appointment_teacher_filter || 'all' !== $appointment_booking_filter ) : ?>
				<p>
					<?php echo $flzest_ui->button( array( 'href' => admin_url( 'admin.php?page=flzest_appointments' ), 'label' => 'Filter zurücksetzen', 'variant' => 'secondary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
				</p>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</div>
