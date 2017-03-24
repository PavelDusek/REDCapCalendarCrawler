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
						var scheduledEvents = document.getElementById('CalCrawl-td-scheduled-'+record_id);
						var thisEvent = document.getElementById('CalCrawl-event-'+record_id+'-'+event_id);
						thisEvent.innerHTML = thisEvent.innerHTML.replace( /\(\d\d\d\d-\d\d-\d\d\)/, '' );
						thisEvent.onclick = function() {};
						scheduledEvents.appendChild(thisEvent);
						var popup = document.getElementById('CalCrawl-popup-'+record_id+'-'+event_id);
						popup.parentNode.removeChild(popup);
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
			var inputDate = document.getElementById('CalCrawl-dateinput-' + record_id + '-' + event_id).value;
			var inputTime = document.getElementById('CalCrawl-timeinput-' + record_id + '-' + event_id).value;
			if ( (/^(\d){4}-(\d){1,2}-(\d){1,2}$/.exec(inputDate)) && (/^(\d){1,2}:(\d){2}$/.exec(inputTime)) ) { //Validate (
				schedule_event_ajax( record_id, project_id, event_id, inputDate, inputTime, '1' );
			} else {
				alert( 'Date and time must be in YYYY-MM-DD,mm:hh format.');
			}
		}
	</script>
	<style>
		th {
			padding: 5px;
		}
		td {
			padding: 5px;
		}
		.overlay{ 
			position: fixed;
			top: 0;
			bottom: 0;
			left: 0;
			right: 0;
			background: rgba(0, 0, 0, 0.7);
			transition: opacity 500ms;
			visibility: hidden;
			opacity: 0;
		}
		.overlay:target{
			visibility: visible;
			opacity: 1;
		}
		.popup{
			margin: 70px auto;
			padding: 20px;
			background: #fff;
			border-radius: 5px;
			width: 30%;
			position: relative;
			transition: all 5s ease-in-out;
		}
		.schedule{
			text-decoration: none;
			transition: all 200 ms;
			font-weight: bold;
			color: #333;
		}
		.schedule:hover{
			color: #06D85F;
		}
		.calCrawlClose{
			position: absolute;
			top: 20px;
			right: 30px;
			transition: all 200 ms;
			font-size: 12em;
			font-weight: bold;
			text-decoration:none;
			color: #000000;
			
		}
		.calCrawlClose:hover{
			color: #06D85F;
		}
	</style>
	<h3>Events</h3>

	<?php
	$events = REDCap::getEventNames(false, true);
	$event_offsets = array();
	foreach ($events as $event_id => $event_name) {
		$event_offsets[$event_id] = get_event_offset( $event_id );
	}
	$patients = get_patients( $project_id );
	$patient_link = str_replace( 'index.php', 'DataEntry/grid.php?pid=' . $project_id . '&id=', $_SERVER['PHP_SELF']);

	echo "<table border='1' style='border-collapse: separate;'>\n";
	echo "\t<tr><th>Study id</th><th>Past events</th><th>Actual events</th><th>Scheduled events</th><th>Events to be scheduled</th></tr>\n";
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
		echo "\t<tr>";
		echo "<td><a href='" . $patient_link . $patient_id . "'>$patient_id</a></td>";

		//LIST PAST EVENTS
		$event_array = array();
		foreach ($past_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td id='CalCrawl-td-past-$patient_id'>" . join(", ", $event_array ) . "</td>";

		//LIST ACTUAL EVENTS
		$event_array = array();
		foreach ($actual_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td id='CalCrawl-td-actual-$patient_id'>" . join(", ", $event_array ) . "</td>";
		
		//LIST SCHEDULED EVENTS
		$event_array = array();
		foreach ($future_events as $e) {
			$event_name = 'Ad hoc';
			if (!is_null($e['event_id']) )
				$event_name = $events[$e['event_id']];
			array_push( $event_array, $e['event_date'] . "(" . $event_name . ")" );
		}
		echo "<td id='CalCrawl-td-scheduled-$patient_id'>" . join(", ", $event_array) . "</td>";

		//LIST UNSCHEDULED EVENTS
		$event_array = array();
		$highlight = false;
		$baseline_date = NULL;
		
		if ( empty($scheduled_events) ) {
			//no events have been scheduled, so we definitely need one
			$highlight = true;
			$baseline_date = NULL;
		} else {
			//use the first event that the patient visited as the baseline
			$scheduled_events_dates = array();
			foreach( $scheduled_events as $e ) array_push($scheduled_events_dates, $e['event_date']);
			asort($scheduled_events_dates);
			$baseline_date = strtotime(array_shift($scheduled_events_dates));
		}
		$secsInDay = 60 * 60 * 24;
		foreach ($to_be_scheduled as $key => $eventname ) {
			//Decide if the event is in near future (90 days). If yes, than highlight it.
			$event_offset = (int) $event_offsets[$key]; //in days
			if (isset($baseline_date)) { 
				$expectedEventTime = $baseline_date+($secsInDay*$event_offset);
				$untilExpectedEventTime = ($expectedEventTime - time());
				if ( ($untilExpectedEventTime/$secsInDay) < 90 ) {
					//the event is expected to happen in less than 90 days
					$highlight = true;
				}
			}
			//Create event link.
			$html = "<a id='CalCrawl-event-$patient_id-$key' href='#CalCrawl-popup-$patient_id-$key'>";
			$html.= $eventname;
			if( isset($expectedEventTime) ) {
				$expectedEventDate = date('Y-m-d', $expectedEventTime); 
				$html .= " ($expectedEventDate)";
			}
			$html.= "</a>";
			//If it should be scheduled asap (either the first event or event in near future), highlight that.
			if ( $highlight ) {
				$html = "<b>$html</b>";
			}

			//Print out html linked to javascript function schedule_event_ajax that runs this script as plugin (POST method)
			//and calls the function schedule event (inputs the event into the database).
			$html .="<div id='CalCrawl-popup-$patient_id-$key' class='overlay'>";
			$html .= "<div class='popup'>";
			$html .=  "<label for='CalCrawl-dateinput-$patient_id-$key'>Date (YYYY-MM-DD):</label>";
			$html .=  "<input ";
			$html .=   "type='text' ";
			$html .=   "id='CalCrawl-dateinput-$patient_id-$key' ";
			$html .=   "name='CalCrawl-dateinput-$patient_id-$key' ";
			if (isset($expectedEventDate) ) {
				$html .=   "value='$expectedEventDate' size='8' /><br />";
			} else {
				$html .=   "value='" . date('Y-m-d') . "' size='8' /><br />";
			}
			$html .=  "<label for='CalCrawl-timeinput-$patient_id-$key'>Time (mm:hh):</label>";
			$html .=  "<input ";
			$html .=   "type='text' ";
			$html .=   "id='CalCrawl-timeinput-$patient_id-$key' ";
			$html .=   "name='CalCrawl-timeinput-$patient_id-$key' ";
			$html .=   "value='08:00' size='4' /><br />";
			$html .=  "<a href='#' class='calCrawlClose'>&times;</a>";
			$html .=  "<a href='#' class='schedule' onClick=\"schedule_event('$patient_id','$project_id','$key');\">Schedule</a>";
			$html .= "</div>";
			$html .="</div>";
			array_push( $event_array, $html);
			$highlight = false;
		}
		echo "<td id='CalCrawl-td-unscheduled-$patient_id'>" . join(", ", $event_array) . "</td>";

		echo "</tr>\n";
	}
	echo "</table>";
	echo "<hr />";
}

function get_event_offset( $event_id ) {
	//Gets day_offset for events in REDCap database.

	$sql = sprintf( "SELECT `day_offset` FROM `redcap_events_metadata` WHERE `event_id`='%s';", db_real_escape_string($event_id) );
	$q = db_query($sql);

	$offsets = array();
	while( $row = db_fetch_assoc($q) ) {
		array_push( $offsets, $row['day_offset']);
	}
	return $offsets[0];
}

function schedule_event( $record_id, $project_id, $event_id, $event_date, $event_time, $event_status ) {
	//Schedules an event into redcap calendar via MySQL query.
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
