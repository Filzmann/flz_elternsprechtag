<script>
    function filterLehrkraft() {
        const input = document.getElementById("lehrkraftInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("appointmentTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 0; i < tr.length; i++) {
            let td = tr[i].getElementsByClassName("lehrkraft")[0];
            if (td) {
                let txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>


<div class="wrap">
    <h1>Elternsprechtag</h1>
    <div>
        <h2>Neuen Elternsprechtag vorbereiten</h2>
        <?php
        $nextDate  =strtotime(FlzEstSetting::get_value_by_name("NextParentsDay"));
        if ($nextDate <= strtotime("tomorrow"))
            echo 'Zur Vorbereitung des nächsten Elternsprechtages definieren Sie bitte im <a href="?page=flzest_settings">Einstellungsbereich</a> ein Datum in der Zukunft!';
        else{

        ?>
            <p>
                Der nächste Elternsprechtag findet am <?php echo FlzEstSetting::get_value_by_name("NextParentsDay") ?>
                von <?php echo FlzEstSetting::get_value_by_name("ParentsDayBegin") ?> Uhr
                bis  <?php echo FlzEstSetting::get_value_by_name("ParentsDayEnd") ?> Uhr statt.
            </p>
            <p>
                Bitte überprüfen Sie diese Einstellungen im Bereich <a href="?page=flzest_settings">"Einstellungen"</a> und passen Sie diese ggf. an bevor Sie mit dem nächsten Schritt fortfahren.<br/>
                Des Weiteren sollte die Liste der <a href="?page=flzest_teachers">Lehrer:innen</a> auf Vollständigkeit überprüft werden.
            </p>
            <form method="post">
                <input type="submit" name="newEST" id="newEST" value="Neuen Elternsprechtag vorbereiten. (Achtung, alle Buchungen werden zurückgesetzt)" onclick="confirm('Achtung! Sie sind dabei, alle Bookings zurückzusetzen. Ist das erwünscht?');" >
            </form>
            <div style="width: 50%; display: inline-block;  ">
                <table id="appointmentTable" border="1" style="width:100%;">
                    <thead style="display:block; width: 100%;">
                        <tr style="width: 100%;">
                            <th style="width:50px;">Beginn</th>
                            <th style="width:50px;">Ende</th>
                            <th style="width:100px;"><input type="text" id="lehrkraftInput" onkeyup="filterLehrkraft()" placeholder="Lehrkraft" style="width:95px;">
                            </th>
                            <th style="width:200px;">Elternteil</th>
                            <th style="width:150px;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody  style="display:block; overflow: auto; height: 20em; width:600px;">
                    <?php foreach ( $appointments as $appointment ) : ?>
                        <tr style="width: 550px;">
                            <td style="width:50px;"><?php echo date("H:i", $appointment->start)?></td>
                            <td style="width:50px;"><?php echo date("H:i", $appointment->end)?></td>
							
                            <td class="lehrkraft" style="width:100px;"><?php
			if($appointment->teacher){
                                echo $appointment->teacher->get_gender_as_anrede()." "
                                     .$appointment->teacher->name; 
			}  ?>
                            </td>
                            <td style="width:200px;"><?php

                                if(isset($appointment->parent)) {
                                    echo $appointment->parent->name . ", ".
                                        $appointment->parent->firstName . " "
                                         ."(". $appointment->parent->studentName. " - "
                                         . $appointment->parent->studentClass.")";
                                }
                                else echo "Freier Slot";?>
                            </td>
                            <td style="width:150px;">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="appointment_empty[id]" value="<?php echo $appointment->id; ?>" />
                                    <button type="submit">Leeren</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment->id; ?>" />
                                    <button type="submit" name="appointment_edit">Bearbeiten</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
               <?php if(isset($_POST['appointment_edit'])): ?>

            <div style="width: 40%; display: inline-block; vertical-align: top; text-align: start;">
                <h2>Buchung bearbeiten</h2>
                Slot von <?php echo date("H:i", $selected->start)?>
                bis <?php echo date("H:i", $selected->end)?> bei <?php
			            echo $selected->teacher->get_gender_as_anrede()." "
			                 .$selected->teacher->name;   ?>
                   <?php

			            if(isset($selected->parent)) {
				            echo $selected->parent->name . ", ".
				                 $selected->parent->firstName . " "
				                 ."(". $selected->parent->studentName. " - "
				                 . $selected->parent->studentClass.")";
			            }
			            else echo "Freier Slot";?>

                <form method="post">

                    <input type="hidden" name="id" value="<?php echo $selected->id; ?>" />
                    <div class="form-field">
                        <label>Anrede:</label>
                        <input type="radio" name="parent[gender]" id="m" value="m" <?php echo $selected->parent->gender=="m"?"checked":""; ?>><label for="m">Herr</label>
                        <input type="radio" name="parent[gender]" id="f" value="f" <?php echo $selected->parent->gender=="f"?"checked":""; ?>><label for="f">Frau</label>
                        <input type="radio" name="parent[gender]" id="d" value="d" <?php echo $selected->parent->gender==""?"checked":""; ?>><label for="d">keine Angabe</label>
                    </div>
                    <div class="form-field">
                        <label for="parent[name]">Name:</label>
                        <input type="text" name="parent[name]" id="parent[name]" value="<?php echo $selected->parent->name?>"  />
                    </div>
                    <div class="form-field">
                        <label for="parent[firstname]">Vorname:</label>
                        <input type="text" name="parent[firstname]" id="parent[firstname]" value="<?php echo $selected->parent->firstName?>" />
                    </div>
                    <div class="form-field">
                        <label for="parent[email]">Email:</label>
                        <input type="email" name="parent[email]" id="parent[email]" value="<?php echo $selected->parent->email?>"  />
                    </div>
                    <div class="form-field">
                        <label for="parent[studentName]">Name Schüler*in:</label>
                        <input type="text" name="parent[studentName]" id="parent[studentName]" value="<?php echo $selected->parent->studentName?>" />
                    </div>
                    <div class="form-field">
                        <label for="parent[studentClass]">Klasse Schüler*in:</label>
                        <input type="text" name="parent[studentClass]" id="parent[studentClass]" value="<?php echo $selected->parent->studentClass?>" />
                    </div>
                    <div class="form-field">
                        <button type="submit" name="submit">Speichern</button>
                    </div>
                </form>
            </div>
                   <?php endif;  ?>
            <div>
                <h3>Buchungen Download (CSV-Datei)</h3>
                <a href="<?php echo $csvFile; ?>">Download CSV-Datei </a>
            </div>
            <div>
                <h3>Termine Sammel-Upload (CSV-Datei)</h3>
                <p>Achtung, die eindeutige identifikation der Lehrer*innen geschieht über die E-Mail-Adresse. Die Datei muss in folgendem Format sein: <br>
                    <code>Email Lehrer; Beginn; Name Schüler:in; Klasse Schüler:in
                    +</code><br/>
                    Es bietet sich an, diese einfach vorher herunterzuladen und zu bearbeiten. </p>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="appointments-csv" id="appointments-csv" accept=".csv">
                    <input type="submit" value="Upload" name="submit_csv">
                </form>

            </div>
        <?php

        } //else
        ?>
    </div>
</div>
