<?php
/***************/
/* Version 1.0 */
/***************/

function get_calendar_for_patient( $project_id, $study_id) {
	//Search for all scheduled visits for a given project

	$sql = sprintf(
		"SELECT * FROM `redcap_events_calendar` WHERE `project_id`='%s' AND `record`='%s';",
		db_real_escape_string($project_id),
		db_real_escape_string($study_id)
	);
	$q = db_query($sql);
	$scheduled_events = array();
	while( $row = db_fetch_assoc($q) ) {
		//TODO scheduled or past
		array_push( $scheduled_events, $row['event_date']);
	}
	return $scheduled_events;
}

function get_patients( $project_id ) {
	//Get all record ids for a given project

	$events = REDCap::getEventNames(false, true);
	reset($events);
	$records = REDCap::getData($project_id, 'array', NULL, NULL, key($events)); //get data just for the first event
	$record_ids = array();
	foreach ( $records as $record_id => $data ) {
		array_push( $record_ids, $record_id );
	}
	return $record_ids;
}

function calendar_crawler( $project_id ) {
	echo "<h3>Events to be planned</h3>";

	$events = REDCap::getEventNames(false, true);
	$patients = get_patients( $project_id );
	$patient_link = str_replace( 'index.php', 'DataEntry/grid.php?pid=' . $project_id . '&id=', $_SERVER['PHP_SELF']);

	echo "<table border='1' style='border-collapse: separate;'>";
	echo "<tr><th>Study id</th><th>past events</th><th>scheduled events</th><th>events to be scheduled</th></tr>";
	foreach ($patients as $patient_id) {
		echo "<tr>";
		echo "<td><a href='" . $patient_link . $patient_id . "'>$patient_id</a></td>";

		echo "<td></td>";

		$scheduled_events = get_calendar_for_patient( $project_id, $patient_id );
		echo "<td>" . join(", ", $scheduled_events) . "</td>";

		echo "<td></td>";

		echo "</tr>";
	}
	echo "</table>";
	echo "<hr />";
}

if( REDCap::isLongitudinal() ) calendar_crawler( $project_id );
?>
