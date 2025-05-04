<?php

namespace PlasticStudio\Search;

use Exception;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;

class SearchControllerExtension extends DataExtension {
	
	private static $allowed_actions = array(
		'SearchForm',
		'AdvancedSearchForm'
	);
	

	/**
	 * Default form (ie in menus and footers)
	 *
	 * @return Form
	 **/
	public function SearchForm(){
		
		// create our search form fields
        $fields = FieldList::create();
		
		$placeholder_text = 'Keywords';
		if (Config::inst()->get('PlasticStudio\Search\SearchPageController', 'search_form_placeholder_text')) {
			$placeholder_text = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'search_form_placeholder_text');
		}
		$fields->push( TextField::create('query','',SearchPageController::get_query())->addExtraClass('query')->setAttribute('placeholder', $placeholder_text) );
		
		// create the form actions (we only need a submit button)
		$submit_button_text = 'Search';
		if (Config::inst()->get('PlasticStudio\Search\SearchPageController', 'submit_button_text')) {
			$submit_button_text = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'submit_button_text');
		}
		// don't do action here, set below for 404 error page fix
		// fix breaks pagination, reinstating
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle($submit_button_text)
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'SearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('search-form')
		->disableSecurityToken();

		// $page = SearchPage::get()->first();
		// $form->setFormAction($page->Link());
		
        return $form;
	}
	

	/**
	 * Build the advanced search form (ie results page)
	 *
	 * @return Form
	 **/
	public function AdvancedSearchForm(){
		
		// create our search form fields
        $fields = FieldList::create();
		
		// search keywords
		$placeholder_text = 'Keywords';
		if (Config::inst()->get('PlasticStudio\Search\SearchPageController', 'search_form_placeholder_text')) {
			$placeholder_text = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'search_form_placeholder_text');
		}
		$fields->push( TextField::create('query','',SearchPageController::get_query())->addExtraClass('query')->setAttribute('placeholder', $placeholder_text) );
			
		// classes to search		
		if ($types_available = SearchPageController::get_types_available()){

			if ($types = SearchPageController::get_types()){
				$value = $types;
				$select_all_types = false;
			} else {
				$value = [];
				$select_all_types = true;
			}

			// Construct the array of options for the field
			foreach ($types_available as $key => $type){
				$source[$key] = $type['Label'];

				if ($select_all_types){
					$value[] = $key;
				}
			}

			$fields->push(CheckboxSetField::create('types', 'Types', $source, $value));
		}
		
		// Filters that we need to map
		if ($filters_available = SearchPageController::get_filters_available()){

			// Grab our already-set filters
			$filters = SearchPageController::get_filters();

			foreach ($filters_available as $key => $filter){

				// Identify any existing values (ie if we're on the results page with values already set)
				$value = null;
				if (isset($filters[$key])){
					$value = $filters[$key];
				}

				switch ($filter['Structure']){
					
					/**
					 * Plain column value field
					 **/
					case 'db':
						if (isset($filter['Field'])){
							$field = $filter['Field'];
						} else {			
							$field = "SilverStripe\Forms\TextField";				
						}
						
						$fields->push($field::create($key, $filter['Label'], $value));
						break;

					/**
					 * Simple relation field
					 **/
					case 'has_one':
						$source = $filter['ClassName']::get();

						// We need to apply a filter to the displayed relational options (based on config)
						if (isset($filter['Filters'])){
							$source = $source->filter($filter['Filters']);
						}

						$empty_string = 'All '.$filter['Label'];
						if (substr($empty_string, -1) != 's'){
							$empty_string.= 's';
						}

						$fields->push(DropdownField::create($key, $filter['Label'], $source->map('ID','Title','All'), $value)->setEmptyString($empty_string));
						break;

					/**
					 * Complex relational field
					 **/
					case 'many_many':
						$source = $filter['ClassName']::get();

						// We need to apply a filter to the displayed relational options (based on config)
						if (isset($filter['Filters'])){
							$source = $source->filter($filter['Filters']);
						}

						if ($value == null) {
                            $default = '';
                        } else {
                            $default = explode(',', $value);
                        }
						$fields->push(CheckboxSetField::create($key, $filter['Label'], $source->map('ID','Title','All'), $default)->addExtraClass('chosen-select'));

						break;
				}
			}
		}
		
		// Sorting rules	
		if ($sorts_available = SearchPageController::get_sorts_available()){

			// Default to the first option
			$source = [];

			// Construct the array of options for the field
			foreach ($sorts_available as $key => $type){
				$source[$key] = $type['Label'];
			}

			$fields->push(DropdownField::create('sort', 'Sort', $source, SearchPageController::get_mapped_sort()['Key']));
		}
		
		// create the form actions (we only need a submit button)
		$submit_button_text = 'Search';
		if (Config::inst()->get('PlasticStudio\Search\SearchPageController', 'submit_button_text')) {
			$submit_button_text = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'submit_button_text');
		}
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle($submit_button_text)
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'AdvancedSearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('search-form advanced-search-form')
		->disableSecurityToken();
		
        return $form;
	}
	
	
	
	/**
	 * Process the submitted search form. All we're really doing is redirecting to our structured URL
	 * @param $data = array (post data)
	 * @param $form = obj (the originating SearchForm object)
	 * @return HTTPRedirect
	 **/
	public function doSearchForm($data, $form){

		$page = SearchPage::get()->first();
		if (!$page){
			throw new Exception("The required SearchPage record does not exist");
			die();
		}

		$filters_available = SearchPageController::get_filters_available();

		$vars = '';
		foreach ($data as $key => $value){

			// Make sure we only carry configured filters
			// This begins to protect us against malicious use :-)
			if ((isset($filters_available[$key]) || $key == 'query' || $key == 'types' || $key == 'sort') && $value && $value !== ''){

				// Concat into a URL string
				if ($vars == ''){
					$vars .= '?'.$key.'=';
				} else {
					$vars .= '&'.$key.'=';
				}

				// And merge any arrays into comma-separated values
				if (is_array($value)){
					$vars .= join(',',$value);
				} else {
					$vars .= $value;
				}
			}
		}

		return $this->owner->redirect($page->Link().$vars);		
	}
}