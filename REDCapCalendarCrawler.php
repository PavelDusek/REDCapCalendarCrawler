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
		$event = array(
			'event_id'   => $row['event_id'],
			'event_date' => $row['event_date'],
			'calendar_id' => $row['cal_id']
		);
		array_push( $scheduled_events, $event );
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
	echo "<h3>Events</h3>";

	$events = REDCap::getEventNames(false, true);
	$patients = get_patients( $project_id );
	$patient_link = str_replace( 'index.php', 'DataEntry/grid.php?pid=' . $project_id . '&id=', $_SERVER['PHP_SELF']);

	echo "<table border='1' style='border-collapse: separate;'>";
	echo "<tr><th>Study id</th><th>past events</th><th>Actual events</th><th>scheduled events</th><th>events to be scheduled</th></tr>";
	foreach ($patients as $patient_id) {
		$scheduled_events = get_calendar_for_patient( $project_id, $patient_id );
		$events_visited_by_patient = REDCap::getData( 'array', $patient_id );
		$events_visited_by_patient = array_keys( $events_visited_by_patient[$patient_id] );

		$future_events = array();
		$actual_events = array();
		$past_events = array();
		$to_be_scheduled = $events; //the events that are already scheduled will be removed from this array later


		foreach ( $scheduled_events as $e ) {
			//Remove this scheduled event from $to_be_scheduled
			if ( array_key_exists( $e['event_id'], $to_be_scheduled ) )
				unset( $to_be_scheduled[$e['event_id']] );
			
			//decide if the event is future or past
			$event_date = $e['event_date'];
			$event_date = strtotime( $event_date );
			if ( in_array($e['event_id'], $events_visited_by_patient ) ) {
				array_push( $past_events, $e );
			} elseif ( $event_date < date() ) {
				array_push( $actual_event, $e );
			} else {
				array_push( $future_events, $e );
			}
		}
		echo "<tr>";
		echo "<td><a href='" . $patient_link . $patient_id . "'>$patient_id</a></td>";

		//LIST PAST EVENTS
		$event_array = array();
		foreach ($past_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td>" . join(", ", $event_array ) . "</td>";

		//LIST ACTUAL EVENTS
		$event_array = array();
		foreach ($actual_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td>" . join(", ", $event_array ) . "</td>";
		
		//LIST SCHEDULED EVENTS
		$event_array = array();
		foreach ($actual_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td>" . join(", ", $event_array) . "</td>";

		//LIST UNSCHEDULED EVENTS
		//TODO LINK TO SCHEDULE THE EVENT
		echo "<td>" . join(", ", $to_be_scheduled) . "</td>";

		echo "</tr>";
	}
	echo "</table>";
	echo "<hr />";
}

if( REDCap::isLongitudinal() ) calendar_crawler( $project_id );
?>
