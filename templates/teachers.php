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

$flzest_teacher_row = static function ( FlzEstTeacher $teacher ) use ( $flzest_ui, $flzest_gender_options ): string {
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

	return $flzest_ui->editable_row(
		array(
			'id'         => $row_id,
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
	<p>
		<?php echo $flzest_ui->button_new( array( 'label' => 'Neue Lehrkraft anlegen', 'attrs' => array( 'data-flz-ui-show-new-row' => 'flzest-teacher-new' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
	</p>

	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die CSV-Komponente inklusive URL, Labels und Formularfeldern.
	echo $flzest_ui->csv_panel(
		array(
			'title'       => 'Lehrkräfte CSV',
			'description' => 'Versionierter Export und obligatorischer Dry-Run. Vorhandene Lehrkräfte werden über ihre E-Mail-Adresse aktualisiert; nicht aufgeführte Lehrkräfte bleiben erhalten.',
			'format'      => 'format_version; record_type; gender; last_name; first_name; email',
			'export'      => array(
				'href'  => $teacher_csv_export_url,
				'label' => 'Lehrkräfte-CSV herunterladen',
			),
			'upload'      => array(
				'nonce'        => 'flzest_admin_action',
				'file_name'    => 'teacher-csv',
				'file_id'      => 'teacher-csv',
				'button_label' => 'Lehrkräfte-CSV prüfen (Dry-Run)',
				'submit_name'  => 'submit_csv_dry_run',
			),
		)
	);
	echo $flzest_ui->csv_panel(
		array(
			'title'       => 'Geprüfte Lehrkräfte-CSV importieren',
			'description' => 'Nach einem erfolgreichen Dry-Run dieselbe unveränderte Datei erneut auswählen. Der Prüfnachweis gilt 15 Minuten.',
			'upload'      => array(
				'nonce'        => 'flzest_admin_action',
				'file_name'    => 'teacher-csv',
				'file_id'      => 'teacher-csv-import',
				'button_label' => 'Geprüfte Lehrkräfte-CSV importieren',
				'submit_name'  => 'submit_csv',
			),
		)
	);
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

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
			<?php echo $flzest_teacher_row( new FlzEstTeacher( array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
			<?php foreach ( $teachers as $teacher ) : ?>
				<?php echo $flzest_teacher_row( $teacher ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Tabellenzeile. ?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( '' !== $teacher_search ) : ?>
		<p>
			<?php echo $flzest_ui->button( array( 'href' => admin_url( 'admin.php?page=flzest_teachers' ), 'label' => 'Filter zurücksetzen', 'variant' => 'secondary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		</p>
	<?php endif; ?>
</div>
