<?php  

class SearchFormsExtension extends DataExtension {

	private static $allowed_actions = array(
		'SearchForm',
		'AdvancedSearchForm',
		'VacancySearchForm',
		'MemberSearchForm'
	);

	/**
	 * Update the existing search form
	 * @return obj
	 **/
	public function updateSearchForm( $form ){


		// if there is a current query, add class to form
		// (used to hide/show the header search form input)
		$query = SearchController::get_query();
		if($query){
			$form->addExtraClass('open');
		}

		// update button text
		$action = $form->Actions()->fieldByName('action_doSearchForm');
		$action->setUseButtonTag(true);
		$action->setTitle('&nbsp;');
		$action->setAttribute('Title', 'Search');
		
		// figure out the results page
		$resultsURL = $this->owner->Link();

		if( $resultsPage = SearchResultsPage::get()->first() ){
			$resultsURL = $resultsPage->Link();
		}

		// add dropdown field to form
		//$form->Fields()->push( DropdownField::create('Subjects','', $subjectsSource) );
		$form->Fields()->push( HiddenField::create('ResultsURL','', $resultsURL) );
		$form->Fields()->removeByName('Types');
		
		// add css class and return form
        return $form->addExtraClass('searchsite-form');
	}
	
	
	/**
	 * Update the existing search form
	 * @return obj
	 **/
	public function AdvancedSearchForm(){
		
		// get the search parameters
		// this is used to keep the search form on screen up-to-date with our results
		$query = SearchController::get_query();
		$parameters = SearchController::get_parameters();
		
		// create our search form fields
        $fields = FieldList::create();
		
		// search keywords
		$fields->push( TextField::create('Query','',$query)->addExtraClass('query')->setAttribute('placeholder', 'Keywords') );
		
		// classes to search		
		$typesSource = array('all' => 'All types');
		foreach( SearchController::get_types_available() as $type => $details ){
			$typesSource[ $type ] = $type;
		}
		
		$typesSelected = array();
		if( $parameters['Types'] ){
			$typesSelected = $parameters['Types'];
		}

		$typesField = CheckboxSetField::create('Types', 'Types', $typesSource, $typesSelected)->addExtraClass('apply-dropdown darken');
		if( count($typesSelected) > 0 ){
			$typesField->addExtraClass('open');
		}
		
		// default to show all available classes to search
		$fields->push($typesField);
	
	
		// SUBJECTS
		// Filter content by SubjectIDs
		
		$subjectsSource = array('all' => 'All subjects');
		//foreach( ArticleSubject::get() as $subject ){
		foreach( $this->FilterTopics() as $subject ){
			$subjectsSource[ $subject->ID ] = $subject->Title;
		}
		
		$subjectsSelected = array();
		if( $parameters['Relations'] ){
			foreach( $parameters['Relations'] as $relation ){
				if( $relation['Table'] == 'Article_Tags' && $relation['Values'] ){
					$subjectsSelected = $relation['Values'];
				}
			}
		}

		$subjectsField = CheckboxSetField::create('Subjects','Subjects', $subjectsSource, $subjectsSelected)->addExtraClass('apply-dropdown darken');
		if( count($subjectsSelected) > 0 ){
			$subjectsField->addExtraClass('open');
		}
		
		$fields->push($subjectsField);
	
		// figure out the results page
		$resultsURL = $this->owner->Link();
		if( $resultsPage = SearchResultsPage::get()->first() ){
			$resultsURL = $resultsPage->Link();
		}

		$fields->push( HiddenField::create('ResultsURL', 'ResultsURL', $resultsURL) );
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doAdvancedSearchForm")->setTitle("Search")->addExtraClass('secondary-action darken')
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner->owner,
			$name = 'AdvancedSearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('advanced-search');
		
        return $form;
	}
	
	
	/**
	 * Update the existing search form DOER
	 * @return obj
	 **/
	public function doAdvancedSearchForm( $data, $searchParameters ){
		// hold the attributes for our search redirection payload
		$query = '';
		$searchParameters = array(
				'Types' => array(),
				'Properties' => array(),
				'Relations' => array()
			);
		
		if( isset($data['Query']) ){
			$query = urlencode($data['Query']);
		}
		
		if( isset($data['Types']) ){
		
			if( is_array( $data['Types'] ) ){
				// if we have selected 'all', find it and remove it from our types array
				$pos = array_search('all', $data['Types']);			
				unset($data['Types'][$pos]);
			}else{
				if( $data['Types'] == 'all' ){
					unset($data['Types']);
				}
			}
			
			$searchParameters['Types'] = $data['Types'];
		}
		
		// sanitize our properties by removing any falsy values
		foreach( $searchParameters['Properties'] as $i => $property ){			
			if( count($property['Values']) <= 0 || $property['Values'] == '' ){
				unset($searchParameters['Properties'][$i]);
			}
		}
		
		// sanitize our relations by removing any falsy values
		foreach( $searchParameters['Relations'] as $i => $property ){
			if( count($property['Values']) <= 0 || $property['Values'] == '' ){
				unset($searchParameters['Relations'][$i]);
			}
		}
		
		// SUBJECTS
		// // Filter by Tags
		
		if( isset($data['Subjects']) && $data['Subjects'] ){
			$subjectsSelected = $data['Subjects'];
			unset($subjectsSelected['all']);
			
			// after we've removed "all" only add to our url if we have anything else
			if( count($subjectsSelected) > 0 ){
				$searchParameters['Relations'][] = array(
					'Table' => 'Article_Tags', // relation table to query eg, Article_tags
					'Class' => 'Article', // left side of the relationship eg, Article
					'Object' => 'Tag', // right side of the relationship eg, Tag
					'Name' => 'Tags', // relationship name eg, Tags (used when displaying search results summary)
					'Values' => $subjectsSelected
				);
			}
		}
		
		// AUTHORS
		
		if( isset($data['Authors']) && $data['Authors'] ){
			$authorsSelected = $data['Authors'];
			unset($authorsSelected['all']);
			
			// after we've removed "all" only add to our url if we have anything else
			if( count($authorsSelected) > 0 ){
				$searchParameters['Relations'][] = array(
					'Table' => 'Story_Authors', // relation table to query eg, Article_tags
					'Class' => 'Story', // left side of the relationship eg, Article
					'Object' => 'Profile', // right side of the relationship eg, Tag
					'Name' => 'Authors', // relationship name eg, Tags (used when displaying search results summary)
					'Values' => $authorsSelected
				);
			}
		}
		

		
		
		// compile our url into a json object, and encode it for a (slightly) more secure url
		$searchParametersEncoded = json_encode( $searchParameters );
		$searchParametersEncoded = urlencode(base64_encode( $searchParametersEncoded ));
		
		return $this->owner->owner->redirect( $data['ResultsURL'] .'search/'. $query .'?params='. $searchParametersEncoded );
	}


	/**
	 * Update the existing search form
	 * @return obj
	 **/
	public function MemberSearchForm(){
		
		// get the search parameters
		// this is used to keep the search form on screen up-to-date with our results
		$query = SearchController::get_query();
		$parameters = SearchController::get_parameters();
		
		// create our search form fields
        $fields = FieldList::create();

        // classes to search		
		$fields->push( HiddenField::create('Classes', 'Classes', 'VolunteerPosting') );
		
		// search keywords
		$fields->push( TextField::create('Query','Name',$query)->addExtraClass('query')->setAttribute('placeholder', 'Member\'s name') );

		// $positionSelected = '';
		// if( $parameters['Properties'] ){
		// 	foreach( $parameters['Properties'] as $property ){
		// 		if( $property['Field'] == 'Title' && $property['Values'] ){
		// 			$positionSelected = $property['Values'];
		// 		}
		// 	}
		// }
		// $fields->push( TextField::create('Position','', $positionSelected)->setAttribute('placeholder', 'Position') );

		// CURRENT LOCATION
		$locationValue = '';
		if( $parameters['Properties'] ){
			foreach( $parameters['Properties'] as $property ){
				if( $property['Field'] == 'MemberLocation' && $property['Values'] ){
					$locationValue = $property['Values'];
				}
			}
		}
		$fields->push( TextField::create('Location','Current location', $locationValue)->setAttribute('placeholder', 'eg, Wellington') );

		// DATES
		// Filter by posting period
		$dateValue = '';
		if( $parameters['Properties'] ){
			foreach( $parameters['Properties'] as $property ){
				if( $property['Field'] == 'FromDate' && $property['Values'] ){
					$dateValue = $property['Values']; //[0];
				}
			}
		}
		$fields->push( DateField::create('PostingDate','Posting date', $dateValue)->setAttribute('placeholder', 'Date')->setConfig('showcalendar', true)  );

		
		// COUNTRIES
		// Filter content by CountryIDs
		$countriesSource = array();
		foreach( Country::get()->Filter('ShowInMenus', 1) as $country ){
			$countriesSource[ $country->ID ] = $country->Title;
		}
		$countriesSelected = array();
		if( $parameters['Properties'] ){
			foreach( $parameters['Properties'] as $property ){
				if( $property['Field'] == 'CountryID' && $property['Values'] ){
					$countriesSelected = $property['Values'];
				}
			}
		}
		$countriesField = CheckboxSetField::create('Countries','Country', $countriesSource, $countriesSelected)->addExtraClass('apply-dropdown darken');
		if( count($countriesSelected) > 0 ){
			$countriesField->addExtraClass('open');
		}
		$fields->push($countriesField );
	
		// figure out the results page
		$resultsURL = $this->owner->Link();
		if( $resultsPage = MemberDirectory::get()->first() ){
			$resultsURL = $resultsPage->Link();
		}
		$fields->push( HiddenField::create('ResultsURL', 'ResultsURL', $resultsURL) );
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doMemberSearchForm")->setTitle("Go")->addExtraClass('secondary-action')
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'MemberSearchForm', 
			$fields = $fields,
			$actions = $actions
		)->addExtraClass('advanced-search');
		
        return $form;
	}

	/**
	 * Update the existing search form DOER
	 * @return obj
	 **/
	public function doMemberSearchForm( $data, $searchParameters ){

		// hold the attributes for our search redirection payload
		$query = '';
		$searchParameters = array(
			'Classes' => 'VolunteerPosting',
			'Types' => 'Returned Volunteers',
			'Properties' => array(),
			'Relations' => array()
		);
		
		if( isset($data['Query']) ){
			$query = urlencode($data['Query']);
		}
		
		// sanitize our properties by removing any falsy values
		foreach( $searchParameters['Properties'] as $i => $property ){			
			if( count($property['Values']) <= 0 || $property['Values'] == '' ){
				unset($searchParameters['Properties'][$i]);
			}
		}
		
		// sanitize our relations by removing any falsy values
		foreach( $searchParameters['Relations'] as $i => $relation ){
			if( count($relation['Values']) <= 0 || $relation['Values'] == '' ){
				unset($searchParameters['Relations'][$i]);
			}
		}

		// POSITION
		/*if( isset($data['Position']) && $data['Position'] != '' ){
			$positionSelected = $data['Position'];
			
			$searchParameters['Properties'][] = array(
				'Field' => 'Title',
				'Operator' => 'LIKE',
				'Name' => 'Position', // property name eg, Country (used when displaying search results summary)
				'Values' => $positionSelected
			);
		}*/

		// LOCATION
		if( isset($data['Location']) && $data['Location'] != '' ){
			$locationSelected = $data['Location'];
			
			$searchParameters['Properties'][] = array(
				'Field' => 'MemberLocation',
				'Operator' => 'LIKE',
				'Name' => 'Current location', // property name eg, Country (used when displaying search results summary)
				'Values' => $locationSelected
			);
		}

		// COUNTRIES
		if( isset($data['Countries']) && !empty($data['Countries']) ){
			$countriesSelected = $data['Countries'];
			unset($countriesSelected['all']);
			
			// after we've removed "all" only add to our url if we have anything else
			if( count($countriesSelected) > 0 ){
				$searchParameters['Properties'][] = array(
					'Field' => 'CountryID',
					'Operator' => 'IN',
					'Name' => 'Country', // property name eg, Country (used when displaying search results summary)
					'Values' => $countriesSelected
				);
			}
		}

		// DATES
		if( isset($data['PostingDate']) && $data['PostingDate'] != '' ){
			$searchParameters['Properties'][] = array(
				'Field' => 'FromDate',
				'Operator' => '<', // FromDate is earlier than the supplied date
				'Name' => 'Date', // property name eg, Country (used when displaying search results summary)
				//'Values' => array( $data['PostingDate'] )
				'Values' => $data['PostingDate']
			);
			$searchParameters['Properties'][] = array(
				'Field' => 'ToDate',
				'Operator' => '>', // ToDate is later than the supplied date
				//'Values' => array( $data['PostingDate'] )
				'Values' => $data['PostingDate']
			);
		}

		// echo '<pre>';
		// print_r($searchParameters);
		// echo '</pre>';
		// die();
		
		// compile our url into a json object, and encode it for a (slightly) more secure url
		$searchParametersEncoded = json_encode( $searchParameters );
		$searchParametersEncoded = urlencode(base64_encode( $searchParametersEncoded ));
		
		return $this->owner->redirect( $data['ResultsURL'] .'search/'. $query .'?params='. $searchParametersEncoded );
	}


	function FilterTopics(){
		return Tag::get();
	}

}