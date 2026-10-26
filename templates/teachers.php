<?php
$flzest_ui = flz_ui();
$flzest_gender_options = array(
	'm' => 'Herr',
	'f' => 'Frau',
	''  => 'keine Angabe',
);

$flzest_teacher_sort_link = static function ( string $label, string $sort ) use ( $flzest_ui, $teacher_orderby, $teacher_order, $teacher_base_args ): string {
	return $flzest_ui->admin_table_sort_link(
		array(
			'label'         => $label,
			'sort'          => $sort,
			'current_sort'  => $teacher_orderby,
			'current_order' => $teacher_order,
			'url_args'      => $teacher_base_args,
		)
	);
};

$flzest_teacher_csv_statuses = array();
if ( ! empty( $teacher_csv_notice['removable_missing_teacher_ids'] ) && is_array( $teacher_csv_notice['removable_missing_teacher_ids'] ) ) {
	foreach ( $teacher_csv_notice['removable_missing_teacher_ids'] as $teacher_id ) {
		$flzest_teacher_csv_statuses[ (int) $teacher_id ] = array(
			'class' => 'flzest-teacher-csv-removable',
			'icon'  => 'dashicons-trash',
			'label' => 'Löschkandidat aus letzter CSV-Prüfung',
		);
	}
}
if ( ! empty( $teacher_csv_notice['protected_missing_teacher_ids'] ) && is_array( $teacher_csv_notice['protected_missing_teacher_ids'] ) ) {
	foreach ( $teacher_csv_notice['protected_missing_teacher_ids'] as $teacher_id ) {
		$flzest_teacher_csv_statuses[ (int) $teacher_id ] = array(
			'class' => 'flzest-teacher-csv-protected',
			'icon'  => 'dashicons-lock',
			'label' => 'Geschützt wegen eines künftigen oder höchstens 30 Tage zurückliegenden Termins',
		);
	}
}

$flzest_teacher_row = static function ( FlzEstTeacher $teacher, int $row_index ) use ( $flzest_ui, $flzest_gender_options, $flzest_teacher_csv_statuses ): string {
	$is_new = empty( $teacher->id );
	$row_id = $is_new ? 'flzest-teacher-new' : 'flzest-teacher-' . (int) $teacher->id;
	$delete_action = '';
	$csv_status = ! $is_new && isset( $flzest_teacher_csv_statuses[ (int) $teacher->id ] ) ? $flzest_teacher_csv_statuses[ (int) $teacher->id ] : null;
	$name_view = esc_html( (string) ( $teacher->name ?? '' ) );
	if ( is_array( $csv_status ) ) {
		$name_view .= sprintf(
			' <span class="flzest-teacher-csv-status %1$s"><span class="dashicons %2$s" aria-hidden="true"></span><span>%3$s</span></span>',
			esc_attr( (string) $csv_status['class'] ),
			esc_attr( (string) $csv_status['icon'] ),
			esc_html( (string) $csv_status['label'] )
		);
	}

	if ( ! $is_new ) {
		$delete_action .= $flzest_ui->action_form_button(
			array(
				'preset' => 'delete',
				'label'  => 'Lehrkraft löschen',
				'method' => 'post',
				'nonce'  => 'flzest_admin_action',
				'hidden' => array( 'teacher_delete' => $teacher->id ),
			)
		);
	}

	$details = array();
	if ( ! $is_new ) {
		$details = array(
			'label' => 'Hinweis im Anmeldeprozess',
			'editor' => array(
				'id'    => 'flzest-teacher-' . (int) $teacher->id . '-registration-note',
				'name'  => 'registration_note',
				'label' => 'Hinweis nach Auswahl dieser Lehrkraft',
				'value' => (string) ( $teacher->registration_note ?? '' ),
				'settings' => array(
					'textarea_rows' => 5,
					'media_buttons' => false,
					'teeny' => true,
					'tinymce' => array( 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink' ),
					'quicktags' => true,
				),
			),
		);
	}

	return $flzest_ui->editable_row(
		array(
			'id'         => $row_id,
			'class'      => trim( ( 0 === $row_index % 2 ? 'alternate ' : '' ) . ( is_array( $csv_status ) ? (string) $csv_status['class'] : '' ) ),
			'new'        => $is_new,
			'row_hidden' => $is_new,
			'form'       => array(
				'method' => 'post',
				'nonce'  => 'flzest_admin_action',
				'hidden' => array( 'id' => $teacher->id ?? '' ),
			),
			'cells'      => array(
				array(
					'view'  => $teacher->get_gender_as_anrede(),
					'field' => array(
						'type'    => 'select',
						'name'    => 'gender',
						'label'   => 'Anrede',
						'value'   => $teacher->gender ?? '',
						'options' => $flzest_gender_options,
					),
				),
				array(
					'view_html' => $name_view,
					'field' => array(
						'type'     => 'text',
						'name'     => 'name',
						'label'    => 'Name',
						'value'    => $teacher->name ?? '',
						'required' => true,
					),
				),
				array(
					'view'  => $teacher->firstName ?? '',
					'field' => array(
						'type'  => 'text',
						'name'  => 'firstName',
						'label' => 'Vorname',
						'value' => $teacher->firstName ?? '',
					),
				),
				array(
					'view'  => $teacher->email ?? '',
					'field' => array(
						'type'         => 'email',
						'name'         => 'email',
						'label'        => 'E-Mail',
						'value'        => $teacher->email ?? '',
						'required'     => true,
						'autocomplete' => 'email',
					),
				),
			),
			'edit'       => array( 'label' => 'Lehrkraft bearbeiten' ),
			'details'    => $details,
			'save'       => array(
				'label' => 'Lehrkraft speichern',
				'attrs' => array( 'name' => 'submit' ),
			),
			'extra_actions' => $delete_action,
		)
	);
};
?>
<div class="wrap">
	<h1>Lehrpersonal bearbeiten</h1>
	<?php if ( ! empty( $teacher_csv_notice ) ) : ?>
		<?php echo $flzest_ui->notice( $teacher_csv_notice['message'], $teacher_csv_notice['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php if ( $teacher_csv_stage_ready ) : ?>
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action', 'hidden' => array( 'confirm_teacher_csv_import' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ! empty( $teacher_csv_notice['deletable_missing'] ) ) : ?>
				<?php echo $flzest_ui->field( array( 'type' => 'checkbox', 'name' => 'delete_missing_teachers', 'label' => sprintf( '%d löschbare, nicht in der CSV enthaltene Lehrkräfte mitsamt ausschließlich mehr als 30 Tage vergangenen Terminen löschen.', (int) $teacher_csv_notice['deletable_missing'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( ! empty( $teacher_csv_notice['protected_missing_teachers'] ) && is_array( $teacher_csv_notice['protected_missing_teachers'] ) ) : ?>
				<fieldset class="flzest-teacher-csv-protected-deletions">
					<legend>Geschützte Lehrkräfte einschließlich aller ihrer Termine endgültig löschen</legend>
					<p>Wählen Sie jede Lehrkraft einzeln aus. Nicht ausgewählte geschützte Lehrkräfte bleiben erhalten.</p>
					<?php foreach ( $teacher_csv_notice['protected_missing_teachers'] as $protected_teacher ) : ?>
						<?php echo $flzest_ui->field( array( 'type' => 'checkbox', 'name' => 'delete_protected_missing_teachers[]', 'checkbox_value' => (string) $protected_teacher['id'], 'label' => (string) $protected_teacher['label'] . ': einschließlich aller Termine endgültig löschen.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<?php echo $flzest_ui->button_save( array( 'label' => 'Geprüfte Lehrkräfte-CSV jetzt importieren', 'hide_label' => false, 'confirm' => 'Die geprüfte Lehrkräfte-CSV jetzt importieren? Gewählte Löschoptionen entfernen Lehrkräfte und gegebenenfalls ihre Termine endgültig.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<p>
		<?php echo $flzest_ui->button_new( array( 'label' => 'Neue Lehrkraft anlegen', 'attrs' => array( 'data-flz-ui-show-new-row' => 'flzest-teacher-new' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	</p>

	<table class="widefat striped flz-ui-admin-table">
		<thead>
			<tr>
				<th><?php echo $flzest_teacher_sort_link( 'Anrede', 'gender' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
				<th>
					<div class="flz-ui-table-head">
						<?php echo $flzest_teacher_sort_link( 'Name', 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?>
						<form method="get" class="flz-ui-table-filter-form">
							<input type="hidden" name="page" value="flzest_teachers" />
							<input type="hidden" name="orderby" value="<?php echo esc_attr( $teacher_orderby ); ?>" />
							<input type="hidden" name="order" value="<?php echo esc_attr( $teacher_order ); ?>" />
							<?php echo $flzest_ui->input( 'text', array( 'name' => 'teacher_search', 'label' => 'Lehrkräfte nach Nachname filtern', 'value' => $teacher_search, 'placeholder' => 'Nachname' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
							<?php echo $flzest_ui->button_filter( array( 'label' => 'Lehrkräfte filtern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
						</form>
					</div>
				</th>
				<th><?php echo $flzest_teacher_sort_link( 'Vorname', 'firstName' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
				<th><?php echo $flzest_teacher_sort_link( 'E-Mail', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped den Sortierlink. ?></th>
				<th>Aktionen</th>
			</tr>
		</thead>
		<tbody>
			<?php echo $flzest_teacher_row( new FlzEstTeacher( array() ), -1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
			<?php foreach ( $teachers as $row_index => $teacher ) : ?>
				<?php echo $flzest_teacher_row( $teacher, $row_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( '' !== $teacher_search ) : ?>
		<p>
			<?php echo $flzest_ui->button( array( 'href' => admin_url( 'admin.php?page=flzest_teachers' ), 'label' => 'Filter zurücksetzen', 'variant' => 'secondary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		</p>
	<?php endif; ?>
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die CSV-Komponente inklusive URL, Labels und Formularfeldern.
	echo $flzest_ui->csv_panel( array( 'title' => 'Lehrkräfte CSV', 'description' => 'Excel-freundlicher Export ohne technische Metadatenspalten. Jede hochgeladene Datei wird zuerst geprüft. Nach erfolgreicher Prüfung werden Datensätze anhand ihrer dienstlichen E-Mail-Adresse aktualisiert oder neu angelegt; nicht aufgeführte Lehrkräfte bleiben erhalten. Der Prüfnachweis gilt für dieselbe unveränderte Datei 15 Minuten.', 'format' => 'gender; last_name; first_name; email', 'format_description' => '<strong>gender</strong>: Anrede/Geschlecht. <strong>last_name</strong>: Nachname. <strong>first_name</strong>: Vorname. <strong>email</strong>: eindeutige dienstliche E-Mail-Adresse.', 'export' => array( 'href' => $teacher_csv_export_url, 'label' => 'Lehrkräfte-CSV herunterladen' ), 'upload' => array( 'nonce' => 'flzest_admin_action', 'file_name' => 'teacher-csv', 'file_id' => 'teacher-csv', 'button_label' => 'Lehrkräfte-CSV prüfen', 'button' => array( 'hide_label' => false, 'confirm' => 'Lehrkräfte-CSV jetzt prüfen? Es werden dabei noch keine Daten geändert.' ), 'submit_name' => 'submit_csv' ) ) );
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>
