
<div class="wrap">
    <h1>Lehrpersonal bearbeiten</h1>
    <div style="display: inline-block; width: 50%; overflow: auto; height: 20em;">

        <table>
            <tr>
                <th>Anrede</th>
                <th>Name</th>
                <th>Vorname</th>
                <th>Email</th>
                <th>Aktionen</th>
            </tr>
			<?php
            foreach ( $teachers as $teacher ) : ?>

                <tr>
	                    <td><?php echo esc_html( $teacher->get_gender_as_anrede() ); ?></td>
	                    <td><?php echo esc_html( $teacher->name ); ?></td>
	                    <td><?php echo esc_html( $teacher->firstName ); ?></td>
	                    <td><?php echo esc_html( $teacher->email ); ?></td>
                    <td>
                        <form method="post" style="display: inline;">
							<?php wp_nonce_field( 'flzest_admin_action' ); ?>
	                            <input type="hidden" name="teacher_delete" value="<?php echo esc_attr( $teacher->id ); ?>" />
                            <button type="submit">Löschen</button>
                        </form>
                        <form method="post" style="display: inline;">
							<?php wp_nonce_field( 'flzest_admin_action' ); ?>
	                            <input type="hidden" name="teacher_id" value="<?php echo esc_attr( $teacher->id ); ?>" />
                            <button type="submit" name="teacher_edit">Bearbeiten</button>
                        </form>
                    </td>
                </tr>
			<?php endforeach; ?>
        </table>

    </div>
    <div style="width: 40%; display: inline-block; vertical-align: top; text-align: start;">
        <h2>Lehrer hinzufügen/bearbeiten</h2>

        <form method="post">
			<?php wp_nonce_field( 'flzest_admin_action' ); ?>

	            <input type="hidden" name="id" value="<?php echo esc_attr( $selected->id ); ?>" />
            <div class="form-field">
                <label>Anrede:</label>
	                <input type="radio" name="gender" id="m" value="m" <?php checked( $selected->gender, 'm' ); ?>><label for="m">Herr</label>
	                <input type="radio" name="gender" id="f" value="f" <?php checked( $selected->gender, 'f' ); ?>><label for="f">Frau</label>
	                <input type="radio" name="gender" id="d" value="d" <?php checked( $selected->gender, '' ); ?>><label for="d">keine Angabe</label>
            </div>
            <div class="form-field">
                <label for="name">Name:</label>
	                <input type="text" name="name" id="name" value="<?php echo esc_attr( $selected->name ); ?>" required />
            </div>
            <div class="form-field">
                <label for="firstName">Vorname:</label>
	                <input type="text" name="firstName" id="firstName" value="<?php echo esc_attr( $selected->firstName ); ?>" />
            </div>
            <div class="form-field">
                <label for="email">Email:</label>
	                <input type="email" name="email" id="email" value="<?php echo esc_attr( $selected->email ); ?>" required />
            </div>
            <div class="form-field">
                <button type="submit" name="submit">Speichern</button>
            </div>
        </form>
    </div>
    <div>
        <h3>Lehrer*innen Download (CSV-Datei)</h3>
	        <a href="<?php echo esc_url( $csvFile ); ?>">Download CSV-Datei </a>
    </div>
    <div>
        <h3>Lehrer*innen Sammel-Upload (CSV-Datei)</h3>
        <p>Achtung, alle bereits vorhandenen Lehrer*innen werden gelöscht. Die Datei muss im selben Format sein, wie die csv-Datei, die man hier herunterladen kann. <br>
            Es bietet sich an, diese einfach vorher herunterzuladen und zu bearbeiten. </p>
        <form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'flzest_admin_action' ); ?>
            <input type="file" name="teacher-csv" id="teacher-csv" accept=".csv">
            <input type="submit" value="Upload" name="submit_csv">
        </form>

    </div>

</div>
