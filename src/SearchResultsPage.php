<?php

class SearchResultsPage extends Page {

	private static $db = array(
	);

	private static $has_one = array(
	);
	
	private static $has_many = array(
	);
	
	private static $many_many = array(
	);
	
	public function getCMSFields(){
		$fields = parent::getCMSFields();	
		return $fields;
	}

}

class SearchResultsPage_Controller extends Page_Controller {

	private static $allowed_actions = array (
	);	
	
}