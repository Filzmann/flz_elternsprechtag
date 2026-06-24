<?php


class FlzEstTeacher extends FlzPerson
{
	public function __construct(array $data = []) {
		parent::__construct($data);
	}

	protected static function get_table_schema(): string {
		return "(
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            firstName VARCHAR(255) NOT NULL,
            gender CHAR NOT NULL,
            email VARCHAR(255) NOT NULL,
            PRIMARY KEY (id))";
	}

	public function errors(): array {
		return [];
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => sanitize_text_field($this->name),
			'firstName' => sanitize_text_field($this->firstName),
			'gender' => sanitize_text_field($this->gender),
			'email' => sanitize_email($this->email),
		];
	}





	protected static function afterCreate(): void {
		//insert defaults

		$example_teachers=[
			["Frau", "Baganz", "ines.baganz@schule.berlin.de"],
			["Herr", "Barz", "t.barz@tagore-gymnasium.de"],
			["Frau", "Benkert", "sabine.benkert@schule.berlin.de"],
			["Frau", "Berthel", "teresa.berthel@schule.berlin.de"],
			["Herr", "Best", "s.best@tagore-gymnasium.de"],
			["Herr", "Beyer", "toralf.beyer@schule.berlin.de"],
			["Herr", "Birk", "k.birk@tagore-gymnasium.de"],
			["Herr", "Blum", "martin.blum@schule.berlin.de"],
			["Frau", "Brandt", "annelie.brandt@schule.berlin.de"],
			["Frau", "Dettmer", "christin.dettmer@schule.berlin.de"],
			["Frau", "Dittrich", "a.dittrich@tagore-gymnasium.de"],
			["Herr", "Dolecki", "k.dolecki@taogre-gymnasium.de"],
			["Herr", "Edelmann", "johann.edelmann@schule.berlin.de"],
			["Herr", "Eichner", "bernd.eichner@schule.berlin.de"],
			["Herr", "Engel", "m.engel@tagore-gymnasium.de"],
			["Herr", "Engelmann", "ronald.engelmann@schule.berlin.de"],
			["Frau", "Ewert", "j.ewert@tagore-gymnasium.de"],
			["Herr", "Finsterwalder", "f.finsterwalder@tagore-gymnasium.de"],
			["Frau", "Fischer", "s.fischer@tagore-gymnasium.de"],
			["Herr", "Fröhlich", "vinzent.froehlich@schule.berlin.de"],
			["Frau", "Gerlach", "anne.gerlach@schule.berlin.de"],
			["Frau", "Goerke", "katrin.goerke@schule.berlin.de"],
			["Frau", "Große", "anke.grosse@schule.berlin.de"],
			["Frau", "Günzl", "heike.guenzl@schule.berlin.de"],
			["Frau", "Hartmann", "karin.hartmann@schule.berlin.de"],
			["Herr", "Hiller", "s.hiller@tagore-gymnasium.de"],
			["Frau", "Hoffmann", "e.hoffmann@tagore-gymnasium.de"],
			["Frau", "Jentsch", "katrin.jentsch@schule.berlin.de"],
			["Frau", "Jochinke", "janina.jochinke@schule.berlin.de"],
			["Herr", "Jörns", "friedemann.joerns@schule.berlin.de"],
			["Frau", "Katsoupi", "eleni.katsoupi@schule.berlin.de"],
			["Herr", "Keller", "sebastian.keller@schule.berlin.de"],
			["Frau", "Keyser", "nadja.keyser@schule.berlin.de"],
			["Frau", "Killig", "christine.killig@schule.berlin.de"],
			["Herr", "Koschmieder", "felix.koschmieder@schule.berlin.de"],
			["Frau", "Krüger", "s.krueger@tagore-gymnasium.de"],
			["Herr", "Ledrich", "heiko.ledrich@schule.berlin.de"],
			["Frau", "Lutter", "gerlinde.lutter@schule.berlin.de"],
			["Herr", "Melde", "d.melde@tagore-gymnasium.de"],
			["Frau", "Menze", "e.menze@tagore-gymnasium.de"],
			["Frau", "Mönch", "friederike.moench@schule.berlin.de"],
			["Herr", "Mösche", "gunar.moesche@schule.berlin.de"],
			["Herr", "Mohnke", "c.mohnke@tagore-gymnasium.de"],
			["Frau", "Mohnke", "a.mohnke@tagore-gymnasium.de"],
			["Frau", "Müller", "anke.mueller1@schule.berlin.de"],
			["Frau", "Näke", "katrin.naeke@schule.berlin.de"],
			["Frau", "Neidhard", "r.neidhard@tagore-gymnasium.de"],
			["Frau", "Nigrin", "kathrin.nigrin@schule.berlin.de"],
			["Frau", "Pahlke", "kathrin.pahlke@schule.berlin.de"],
			["Herr", "Paschmann", "jens.paschmann@schule.berlin.de"],
			["Frau", "Pierschel", "undine.pierschel@schule.berlin.de"],
			["Herr", "Piksa", "m.piksa@tagore-gymnasium.de"],
			["Frau", "Pinkernell", "m.pinkernell@tagore-gymnasium.de"],
			["Frau", "Preidel", "belinda.preidel@schule.berlin.de"],
			["Frau", "Reimann", "ramona.reimann@schule.berlin.de"],
			["Frau", "Renken", "j.renken@tagore-gymnasium.de"],
			["Frau", "Röstel", "kathrin.roestel@schule.berlin.de"],
			["Frau", "Schärfen", "k.schaerfen@tagore-gymnasium.de"],
			["Frau", "Schaich", "janine.schaich@schule.berlin.de"],
			["Herr", "Schaer", "m.schaer@tagore-gymnasium.de"],
			["Herr", "Siegl", "c.siegl@tagore-gymnasium.de"],
			["Herr", "Steiner", "janis.steiner@schule.berlin.de"],
			["Herr", "Stramm", "benjamin.stramm@schule.berlin.de"],
			["Frau", "Trotzki", "heike.trotzki@schule.berlin.de"],
			["Herr", "Tschakert", "s.tschakert@tagore-gymnasium.de"],
			["Frau", "Urban", "marleen.urban@schule.berlin.de"],
			["Frau", "Walther", "ulrike.walther1@schule.berlin.de"],
			["Frau", "Weiße", "lisa.weisse@schule.berlin.de"],
			["Frau", "Weser-Remus", "a.weser@tagore-gymnasium.de"],
			["Herr", "Wolff", "m.wolff@tagore-gymnasium.de"],
			["Frau", "Zinke", "k.zinke@tagore-gymnasium.de"],
			["Herr", "Zwick", "f.zwick@tagore-gymnasium.de"],
			["Herr", "Danz", "d.danz@tagore-gymnasium.de"]
		];

		foreach ( $example_teachers as $example_teacher ) {
			$data=array(
				'name'=> $example_teacher[1],
				'gender'=> $example_teacher[0]=="Herr"?"m":"f",
				'email'=> $example_teacher[2]
			);
			$teacher=new FlzEstTeacher($data);
			$teacher->save();

		}

	}
	public function getCsvLine(): string {
		return implode( ';', [
			$this->gender,
			$this->name,
			$this->firstName,
			$this->email,
		] );

	}
	public function createTeachersAppointments($slots=array()): void {
		$slots=empty($slots)?
			(
				function_exists( "getAppointmentsSlots" ) ?
					getAppointmentsSlots()
					: $slots
			):$slots;
		foreach ( $slots as $slot ) {
			$data = [
				'start' => $slot['start'],
				'end' => $slot['end'],
				'teacher' => $this,
				'parent' => null
			];
			$appointment = new flzEstAppointment( $data );
			$appointment->save();
		}
	}
	protected static function afterInsert(): void {
		global $wpdb;
		$teachersId=$wpdb->insert_id;
		//echo "TeachersId: $teachersId";
		$teacher=FlzEstTeacher::get_by_id($teachersId);
		$teacher->createTeachersAppointments();
	}
}
