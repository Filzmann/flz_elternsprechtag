
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
                    <td><?php echo $teacher->get_gender_as_anrede();   ?></td>
                    <td><?php echo $teacher->name; ?></td>
                    <td><?php echo $teacher->firstName; ?></td>
                    <td><?php echo $teacher->email; ?></td>
                    <td>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="teacher_delete" value="<?php echo $teacher->id; ?>" />
                            <button type="submit">Löschen</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher->id; ?>" />
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

            <input type="hidden" name="id" value="<?php echo $selected->id; ?>" />
            <div class="form-field">
                <label>Anrede:</label>
                <input type="radio" name="gender" id="m" value="m" <?php echo $selected->gender=="m"?"checked":""; ?>><label for="m">Herr</label>
                <input type="radio" name="gender" id="f" value="f" <?php echo $selected->gender=="f"?"checked":""; ?>><label for="f">Frau</label>
                <input type="radio" name="gender" id="d" value="d" <?php echo $selected->gender==""?"checked":""; ?>><label for="d">keine Angabe</label>
            </div>
            <div class="form-field">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" value="<?php echo $selected->name?>" required />
            </div>
            <div class="form-field">
                <label for="firstName">Vorname:</label>
                <input type="text" name="firstName" id="firstName" value="<?php echo $selected->firstName?>" />
            </div>
            <div class="form-field">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" value="<?php echo $selected->email?>" required />
            </div>
            <div class="form-field">
                <button type="submit" name="submit">Speichern</button>
            </div>
        </form>
    </div>
    <div>
        <h3>Lehrer*innen Download (CSV-Datei)</h3>
        <a href="<?php echo $csvFile; ?>">Download CSV-Datei </a>
    </div>
    <div>
        <h3>Lehrer*innen Sammel-Upload (CSV-Datei)</h3>
        <p>Achtung, alle bereits vorhandenen Lehrer*innen werden gelöscht. Die Datei muss im selben Format sein, wie die csv-Datei, die man hier herunterladen kann. <br>
            Es bietet sich an, diese einfach vorher herunterzuladen und zu bearbeiten. </p>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="teacher-csv" id="teacher-csv" accept=".csv">
            <input type="submit" value="Upload" name="submit_csv">
        </form>

    </div>

</div>
