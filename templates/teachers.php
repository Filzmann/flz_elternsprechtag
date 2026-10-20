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

$flzest_teacher_row = static function ( FlzEstTeacher $teacher, int $row_index ) use ( $flzest_ui, $flzest_gender_options ): string {
	$is_new = empty( $teacher->id );
	$row_id = $is_new ? 'flzest-teacher-new' : 'flzest-teacher-' . (int) $teacher->id;
	$delete_action = '';

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
			'class'      => 0 === $row_index % 2 ? 'alternate' : '',
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
					'view'  => $teacher->name ?? '',
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
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $flzest_ui->button( array( 'label' => 'Geprüfte Lehrkräfte-CSV jetzt importieren', 'attrs' => array( 'name' => 'confirm_teacher_csv_import', 'value' => '1', 'type' => 'submit' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	echo $flzest_ui->csv_panel( array( 'title' => 'Lehrkräfte CSV', 'description' => 'Excel-freundlicher Export ohne technische Metadatenspalten. Die Datei wird standardmäßig nur geprüft; zum Import die Prüfoption erst nach einem erfolgreichen Dry-Run deaktivieren. Der Prüfnachweis gilt für dieselbe unveränderte Datei 15 Minuten.', 'format' => 'gender; last_name; first_name; email', 'format_description' => '<strong>gender</strong>: Anrede/Geschlecht. <strong>last_name</strong>: Nachname. <strong>first_name</strong>: Vorname. <strong>email</strong>: eindeutige dienstliche E-Mail-Adresse.', 'export' => array( 'href' => $teacher_csv_export_url, 'label' => 'Lehrkräfte-CSV herunterladen' ), 'upload' => array( 'nonce' => 'flzest_admin_action', 'file_name' => 'teacher-csv', 'file_id' => 'teacher-csv', 'fields' => array( array( 'type' => 'checkbox', 'name' => 'csv_dry_run', 'label' => 'Datei nur prüfen – es werden noch keine Lehrkräfte geändert.', 'checked' => true ) ), 'button_label' => 'Lehrkräfte-CSV prüfen oder importieren', 'button' => array( 'hide_label' => false, 'confirm' => 'CSV jetzt verarbeiten? Mit aktivierter Prüfoption werden keine Daten geändert; ohne sie ist nur eine zuvor erfolgreich geprüfte, unveränderte Datei importierbar.' ), 'submit_name' => 'submit_csv' ) ) );
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>
