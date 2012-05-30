<?php

/**
 * A sample webservice for working with calendars and their events
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class CalendarWebService {
	
	public function webEnabledMethods() {
		return array(
			'createEvent'		=> 'POST',
		);
	}

	public function __construct() {
		
	}

	public function createEvent($title, $content, $parentCalendar, $startDate, $endDate=null, $canRegister = false, $doPublish = true) {
		if (!class_exists("Calendar")) {
			return;
		}
		
		$parentCalendar = (int) $parentCalendar;
		
		if (!$parentCalendar) {
			throw new Exception("Parent calendar ID expected");
		}

		if ($parentCalendar) {
			$parentCalendar = DataObject::get_by_id('Calendar', $parentCalendar);
		}

		if (!$parentCalendar || !$parentCalendar->exists()) {
			throw new Exception("Could not find parent calendar");
		}
		
		if (!$parentCalendar->canEdit()) {
			throw new WebServiceException(403, "Access denied to that calendar");
		}

		if ($canRegister && !class_exists('RegisterableEvent')) {
			throw new Exception("Event registration not supported");
		}

		$startDate = date('Y-m-d', strtotime($startDate));

		if (!$endDate) {
			$endDate = date('Y-m-d', strtotime($startDate));
		} else {
			$endDate = date('Y-m-d', strtotime($endDate));
		}

		$type = $canRegister ? 'RegisterableEvent' : 'CalendarEvent';
		$dateTimeType = $canRegister ? 'RegisterableDateTime' : 'CalendarDateTime';

		$event = new $type;
		$event->Title = $title;
		$event->Content = $content;
		$event->ParentID = $parentCalendar->ID;
		$event->write();
		
		$dateTime = new $dateTimeType;
		$dateTime->Title = $title;
		$dateTime->StartDate = $startDate;
		$dateTime->EndDate = $endDate;
		$dateTime->is_all_day = $startDate == $endDate;
		$dateTime->EventID = $event->ID;
		$dateTime->write();
		
		if ($doPublish) {
			$event->doPublish();
		}

		return $event;
	}
}
