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

	//Script to enable schedule events
	?>
	<script type="text/javascript">
		function schedule_event_ajax( record_id, project_id, event_id, event_date, event_time, event_status ) {
			var xhttp = new XMLHttpRequest();
			var params = "scheduleEvent=1";
			params += "&record_id=" + encodeURIComponent( record_id );
			params += "&project_id=" + encodeURIComponent( project_id );
			params += "&event_id=" + encodeURIComponent( event_id );
			params += "&event_date=" + encodeURIComponent( event_date );
			params += "&event_time=" + encodeURIComponent( event_time );
			params += "&event_status=" + encodeURIComponent( event_status );
			var url = window.location.href;
			url = url.replace( /redcap_v.*$/, 'hooks/REDCapCalendarCrawler.php' );

			xhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {
					if (this.responseText == '1') { //SQL query successful
						var scheduledEvents = document.getElementById('CalenderCrawler-td-scheduled-'+record_id);
						var thisEvent = document.getElementById('CalenderCrawler-event-'+record_id+'-'+event_id);
						thisEvent.onclick = function() {};
						scheduledEvents.appendChild(thisEvent);
					} else {
						alert('Error while updating database.');
					}
				}
			};
			xhttp.open("POST", url , false);
			xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xhttp.send(params);
		}
		function schedule_event( record_id, project_id, event_id ) {
			//var inputDate = '';
			//while (! /(\d){4}-(\d){2,3}-(\d){2,3}/.exec(inputDate) ) { //Validate 
			//var inputDate = prompt( 'Date of the event (YYYY-MM-DD)?', '2017-04-01' );
			//var inputTime = prompt( 'Time of the event (mm:hh)?', '10:00' );
			//}
			//TODO overlay
			schedule_event_ajax( record_id, project_id, event_id, inputDate, inputTime, '1' );
		}
		
	</script>
	<?php

	$events = REDCap::getEventNames(false, true);
	$patients = get_patients( $project_id );
	$patient_link = str_replace( 'index.php', 'DataEntry/grid.php?pid=' . $project_id . '&id=', $_SERVER['PHP_SELF']);

	echo "<table border='1' style='border-collapse: separate;'>";
	echo "<tr><th>Study id</th><th>past events</th><th>Actual events</th><th>Scheduled events</th><th>events to be scheduled</th></tr>";
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
			} elseif ( $event_date < time() ) {
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
		echo "<td id='CalenderCrawler-td-past-$patient_id'>" . join(", ", $event_array ) . "</td>";

		//LIST ACTUAL EVENTS
		$event_array = array();
		foreach ($actual_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td id='CalenderCrawler-td-actual-$patient_id'>" . join(", ", $event_array ) . "</td>";
		
		//LIST SCHEDULED EVENTS
		$event_array = array();
		foreach ($future_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td id='CalenderCrawler-td-scheduled-$patient_id'>" . join(", ", $event_array) . "</td>";

		//LIST UNSCHEDULED EVENTS
		$event_array = array();
		foreach ($to_be_scheduled as $key => $eventname ) {
			array_push(
				$event_array,
				"<a ".
				"id='CalenderCrawler-event-$patient_id-$key'".
				"href='#' onClick=\"schedule_event('$patient_id','$project_id','$key');\">$eventname</a>"
			);
		}
		echo "<td id='CalenderCrawler-td-unscheduled-$patient_id'>" . join(", ", $event_array) . "</td>";

		echo "</tr>";
	}
	echo "</table>";
	echo "<hr />";
}


function schedule_event( $record_id, $project_id, $event_id, $event_date, $event_time, $event_status ) {
	$sql = sprintf(
		"INSERT INTO `redcap_events_calendar` (`record`, `project_id`, `event_id`, `event_date`, `event_time`, `event_status`) values ('%s', '%s', '%s', '%s', '%s', '%s');",
		db_real_escape_string($record_id),
		db_real_escape_string($project_id),
		db_real_escape_string($event_id),
		db_real_escape_string($event_date),
		db_real_escape_string($event_time),
		db_real_escape_string($event_status)
	);
	$q = db_query($sql);
	echo $q;
}


if( isset($_POST['scheduleEvent']) ) {
	//This file is called by AJAX as a plugin to schedule an event

	// Call the REDCap Connect file in the main "redcap" directory to check if user is logged in.
	require_once "../redcap_connect.php";

	if (
		isset( $_POST['record_id']) &&
		isset( $_POST['project_id']) &&
		isset( $_POST['event_id']) &&
		isset( $_POST['event_date']) &&
		isset( $_POST['event_time']) &&
		isset( $_POST['event_status'])
	) {
		//run the scheduling:
		schedule_event(
			$_POST['record_id'],
			$_POST['project_id'],
			$_POST['event_id'],
			$_POST['event_date'],
			$_POST['event_time'],
			$_POST['event_status']
		);
	} else {
		echo "Insufficient data to schedule an event.";
	}
} else {
	//This file is called as a hook, display the unscheduled events:
	if( REDCap::isLongitudinal() ) calendar_crawler( $project_id );
}
?>
