<?php  

class SearchControllerExtension extends DataExtension {
	
	// these statics hold the search data
	private static $query;
	private static $parameters;
	private static $types_selected;
	private static $types_available;
	private static $results;
	private static $results_url;
	
	// setup the actions to expose our engine
	private static $allowed_actions = array(
		'SearchForm',
		'search'
	);
	
	// setup the search parameters
	public static $url_handlers = array(
		'search/$Query' => 'search'
	);
	
	
	/**
	 * Getters
	 **/
	public static function get_types_available(){
		return self::$types_available;
	}
	
	public static function set_types_available( $types ){
		self::$types_available = $types;
	}
	
	public static function get_types_selected(){
		return self::$types_selected;
	}
	
	public static function set_types_selected( $types ){
		self::$types_selected = $types;
	}
	
	public static function get_query( $mysqlSafe = false ){
		$query = self::$query;
		if( $mysqlSafe ){
			$query = str_replace("'", "\'", $query);
			$query = str_replace('"', '\"', $query);
			$query = str_replace('`', '\`', $query);
		}
		return $query;
	}
	
	public static function set_query( $query ){
		self::$query = $query;
	}
	
	public static function get_parameters(){
		return self::$parameters;
	}
	
	public static function set_parameters( $parameters ){
		self::$parameters = $parameters;
	}
	
	public static function get_results(){
		return self::$results;
	}
	
	public static function set_results( $results ){
		self::$results = $results;
	}
	
	public static function get_results_url(){
		return self::$results_url;
	}
	
	public static function set_results_url( $results_url ){
		self::$results_url = $results_url;
	}
	
	
	/**
	 * This is the action that is called when we request /search/$Query, so essentially the core of our engine
	 * @param $request = HTTP_Request
	 * @return array
	 **/
	public function search( $request ){
	
		// get the parameters and variables of this request (ie the query and filters)
		$params = $request->params();
		$vars = $request->requestVars();
		
		// set the query variable
		if( isset($params['Query']) && $params['Query'] != '' ){
			SearchController::set_query( $params['Query'] );
		}
		
		// make sure we have some parameters
		if( isset($vars['params']) && $vars['params'] != '' ){
			
			// decode them from our mumbo-jumbo
			$parameters = base64_decode(urldecode( $vars['params'] ));
			$parameters = json_decode( $parameters, true );

			// echo '$parameters in search()<pre>';
			// print_r($parameters);
			// echo '</pre>';
			
			// store in our static variable for construction of our queries
			SearchController::set_parameters( $parameters );
			
			// set the types
			if( $parameters['Types'] ){
				
				// clear our classes defaults
				$typesSelected = array();
				$typesAvailable = SearchController::get_types_available();
				
				// if we've been given a restriction on our classes to search
				// you can set these within updateSearchForm() by creating a HiddenField or similar
				if( isset($parameters['Types']) ){
				
					// an array of types (ie from a CheckboxSet field)
					if( is_array($parameters['Types']) ){
						foreach( $parameters['Types'] as $name => $details ){
							if( !isset($typesAvailable[ $name ]) ){
								echo 'Trying to search type "'.$name.'" but it is not enabled for search';
								die();
							}
							$typesSelected[ $name ] = $typesAvailable[ $name ];
						}
					
					// just a string (ie HiddenField)
					}else{
						$name = $parameters['Types'];
						if( !isset($typesAvailable[ $name ]) ){
							echo 'Trying to search type "'.$name.'" but it is not enabled for search';
							die();
						}
						$typesSelected[ $name ] = $typesAvailable[ $name ];
					}
				}
				
				SearchController::set_types_selected( $typesSelected );
			}		
		}
		
		// now actually get the results
		SearchController::set_results( $this->PerformSearch() );
		
		// this lets the request proceed as per usual to template rendering 
		return array();
	}
	
	
	/**
	 * Get the search query
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Query(){
		return SearchController::get_query();
	}
	
	
	/**
	 * Get the results
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Results(){
		return SearchController::get_results();
	}
	
	
	/**
	 * Get the types searched
	 * This is just an alias to get my static variable. We then construct them as ArrayList for template use
	 * @return ArrayList
	 **/
	public function Types(){
		// $types = ArrayList::create();
		// $types->push( array('Name' => 'foo') );
		// $types->push( array('Name' => 'bar') );
		// return $types;

		$types = SearchController::get_types_selected();
		if( !$types ) return false;
		
		$completeTypes = ArrayList::create();
		foreach( $types as $type => $details ){
			$details['Name'] = $type;
			$completeTypes->push( $details );
		}
		return $completeTypes;	
	}

	/**
	 * Get the relations searched
	 * This is just an alias to get my static variable. We then construct them as ArrayList for template use
	 * @return ArrayList
	 **/
	public function Filters(){
		$parameters = SearchController::get_parameters();
		if( !$parameters ) return false;

		$filters = ArrayList::create();

		if( $parameters['Relations'] ){
			foreach( $parameters['Relations'] as $relation ){
				if( $relation['Table'] == 'Vacancy_Skills' && $relation['Values'] ){
					$skillsSelected = $relation['Values'];
				}
			}
		}

		foreach( $parameters['Properties'] as $property ){
			$filters->push( $property );
		}
		foreach( $parameters['Relations'] as $relation ){
			$filters->push( $relation );
		}
		
		return $filters;

	}
	
	
	/**
	 * Get the results_url
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function ResultsURL(){
		return SearchController::get_results_url();
	}
	
	/**
	 * Placeholder function to facilitate manipulation of the search results url form without having to
	 * clone ResultsURL method. To use, just create a method updateResultsURL( $url ) and apply your edits
	 * @param $url = str
	 * @return str
	 **/
	public function updateResultsURL( $url ){
		return $url;
	}
	
	
	/**
	 * Build the search form
	 * Use this as the base platform when creating specific search forms
	 * @return obj
	 **/
	public function SearchForm(){
		
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
		
		// default to show all available classes to search
		$fields->push( CheckboxSetField::create('Types', 'Types', $typesSource, $typesSelected) );
		
		// use this page's link as the search results URL (customise this within your page's updateSearchForm() )
		$fields->push( HiddenField::create('ResultsURL', 'ResultsURL', $this->owner->Link()) );
		
		// create the form actions (we only need a submit button)
        $actions = FieldList::create(
            FormAction::create("doSearchForm")->setTitle("Search")
        );
		
		// now build the actual form object
        $form = Form::create(
			$controller = $this->owner,
			$name = 'SearchForm', 
			$fields = $fields,
			$actions = $actions
		);
		
        $form = $this->owner->updateSearchForm( $form );
		
        return $form;
	}
	
	
	/**
	 * Placeholder function to facilitate manipulation of the search form without having to
	 * clone SearchForm method. To use, just create a method updateSearchForm( $form ) and apply your edits
	 * @param $form = obj
	 * @return obj
	 **/
	public function updateSearchForm( $form ){
		return $form;
	}
	
	
	
	/**
	 * Process the submitted search form. All we're really doing is redirecting to our structured URL
	 * @param $data = array (post data)
	 * @param $form = obj (the originating SearchForm object)
	 * @return null
	 **/
	public function doSearchForm( $data, $form ){
		
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
		
        $searchParameters = $this->owner->updateDoSearchForm( $data, $searchParameters );
		
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

		// compile our url into a json object, and encode it for a (slightly) more secure url
		$searchParametersEncoded = json_encode( $searchParameters );
		$searchParametersEncoded = urlencode(base64_encode( $searchParametersEncoded ));

		return $this->owner->redirect( $data['ResultsURL'] .'search/'. $query .'?params='. $searchParametersEncoded );		
	}
	
	
	/**
	 * Placeholder function to facilitate manipulation of the search form DOER. To use, just create a method
	 * updateDoSearchForm( $url ) and apply your edits
	 * @param $data = array (the original form $_POST data)
	 * @param $searchParameters = array
	 * @return string
	 **/
	public function updateDoSearchForm( $data, $searchParameters ){
		return $searchParameters;
	}
	
	
	/**
	 * Have a squiz through our site and find all matches
	 * @return PaginatedList
	 **/
	public function PerformSearch(){
		
		// get all our search requirements
		$query = SearchController::get_query( $mysqlSafe = true );


		$types = SearchController::get_types_selected();
		$parameters = SearchController::get_parameters();


		// echo '$query in PerformSearch()<pre>';
		// print_r($query);
		// echo '</pre>';

		// echo '$types in PerformSearch()<pre>';
		// print_r($types);
		// echo '</pre>';

		// echo '$parameters in PerformSearch()<pre>';
		// print_r($parameters);
		// echo '</pre>';
		//die();
		
		// if we haven't selected any classes, let's just search all available classes
		if( !$types ){
			$types = SearchController::get_types_available();
		}
		
		// prepare our final result object
		$allResults = ArrayList::create();
		
		// loop all the records we need to lookup
		foreach( $types as $type => $details ){
			
			$columns = $details['Columns'];
			
			$sql = '';
			$joins = '';
			$where = '';
			$sort = '';
			
			// if we ARE or we EXTEND Page
			if( is_subclass_of( singleton($details['ClassName']), 'Page' ) || $details['ClassName'] == 'Page' ){
				
				$sql.= "SELECT \"SiteTree_Live\".\"ID\" AS \"ResultObject_ID\", \"SiteTree_Live\".\"ClassName\" AS \"ResultObject_ClassName\" FROM \"".$details['Table']."\" ";
				
				// if we're not Page, then join with the Page table
				if( $details['ClassName'] != 'Page' ) $joins.= "LEFT JOIN \"Page_Live\" ON \"Page_Live\".\"ID\" = \"".$details['Table']."\".\"ID\" ";
				
				// and then join onto SiteTree
				$joins.= "LEFT JOIN \"SiteTree_Live\" ON \"SiteTree_Live\".\"ID\" = \"Page_Live\".\"ID\" ";		
			
			// any old dataobject
			}else{
				$sql.= "SELECT \"".$details['Table']."\".\"ID\" AS \"ResultObject_ID\", \"".$details['Table']."\".\"ClassName\" AS \"ResultObject_ClassName\" FROM \"".$details['Table']."\" ";
			}
			
			// handle any additional joins required
			if( isset($details['ExtraTables']) && count($details['ExtraTables']) > 0 ){
				foreach( $details['ExtraTables'] as $extraTable ){
					$joins.= "LEFT JOIN \"".$extraTable."\" ON \"".$extraTable."\".\"ID\" = \"".$details['Table']."\".\"ID\" ";		
				}
			}
			
			
			// SEARCH TERM
			// The actual keywords. Search all the configured columns for this term.
			
			$where .= ' WHERE (';
			foreach( $columns as $i => $column ){
				if( $i > 0 ){
					$where .= ' OR ';
				}
				$where .= "\"". $column ."\" LIKE CONCAT('%','". $query ."','%')";
			}
			$where.= ')';
			
			
			// EXTRA WHERE PROPERTIES
			// Facilitates extra properties on a per-Type basis. See _config.php for more details
			
			if( isset($details['ExtraWhere']) ){
				$where.= ' AND ('.$details['ExtraWhere'].')';
			}
			
			// SEARCH PROPERTIES
			// These are actually just columns that match a specific value
			
			if( count($parameters['Properties']) > 0 ){
			
				// open our wrapper
				$where.= ' AND (';
			
				foreach( $parameters['Properties'] as $i => $rule ){
					
					// run a preliminary lookup to make sure we have the appropriate relationship in this page class
					$columnExists = false;
					$tablesToCheck = array('Page_Live','SiteTree_Live',$details['Table']);
					$tableWithColumn = false;
					
					// check each of our tables
					foreach( $tablesToCheck as $table ){
						$columnExistsQuery = DB::query( "SHOW COLUMNS FROM \"".$table."\" LIKE '".$rule['Field']."'" );				

						foreach( $columnExistsQuery as $column ) $tableWithColumn = $table;
					}

					// if we didn't get a column, then we simply cannot search this class!
					if( $tableWithColumn != $details['Table'] && ( !isset($details['ExtraTables']) || !in_array( $tableWithColumn, $details['ExtraTables'] ) ) ){
						echo "<h2>Not able to find tables</h2>";
						continue 2;
					}
					
					if( $i > 0 ) $where.= ' AND ';
					
					if( is_array( $rule['Values'] ) ){
						$valuesString = '';
						foreach( $rule['Values'] as $value ){
							if( $valuesString != '' ) $valuesString.= ',';
							$valuesString.= "'".$value."'";
						}
					}elseif($rule['Operator'] == 'LIKE'){
						$valuesString = "CONCAT('%','". $rule['Values'] ."','%')";
					}else{
						$valuesString = "'".$rule['Values']."'";
					}
					
					// wrapping braces around values if we're checking an array of items
					if( $rule['Operator'] == 'IN' ) $valuesString = '('.$valuesString.')';
					
					$where.= "\"".$tableWithColumn."\".\"".$rule['Field']."\" ".$rule['Operator']." ". $valuesString ."";
				}
			
				// close our wrapper
				$where.= ')';
			}

			
			// SEARCH RELATIONS
			// Object-oriented filtering, uses joins and filters
			
			if( count($parameters['Relations']) > 0 ){
				
				$relationsSql = '';
				
				foreach( $parameters['Relations'] as $i => $relation ){
					
					// run a preliminary lookup to make sure we have the appropriate relationship in this page class
					$columnExistsQuery = DB::query("SHOW COLUMNS FROM \"".$relation['Table']."\" LIKE '".$relation['Object']."ID'");
					$columnExists = false;
					foreach( $columnExistsQuery as $column ){
						//var_dump($column);
						$columnExists = true;
					}
					
					// if we didn't get a column, then we simply cannot search this class!
					if( !$columnExists ){
						//echo "<h2>SHOW COLUMNS FROM \"".$relation['Table']."\" LIKE '".$relation['Object']."ID'</h2>";
						continue 2;
					}
					
					// join string (except for first iteration)
					if( $i > 0 ) $relationsSql .= ' AND ';

					// if there is a relation on $details['Table'], get that
					if( DB::query("SHOW COLUMNS FROM \"".$relation['Table']."\" LIKE '".$details['Table']."ID'") ){
						$column = $details['Table'];
						
					}
					// otherwise, try $details['ExtraTable']
					else{
						$column = $details['ExtraTable'];
					}
					
					// join the relationship table to our record(s)
					$joins.= "LEFT JOIN \"".$relation['Table']."\" ON \"".$relation['Table']."\".\"".$relation['Class']."ID\" = \"".$column."\".\"ID\" ";
					
					$ids = '';
					foreach( $relation['Values'] as $id ){
						if( $ids != '' ) $ids.= ',';
						$ids.= "'".$id."'";
					}
					$relationsSql.= "\"".$relation['Table']."\".\"".$relation['Object']."ID\" IN (". $ids .")";
				}
				
				if( $relationsSql != '' ){
					$where.= ' AND ('.$relationsSql.')';
				}
			}
			
			
			// EXECUTE
			// Run the damn thing!
			
			// compile our sql string
			$sql.= $joins;
			$sql.= $where;

			//echo $sql;

			// executioners enter stage left
			$results = DB::query( $sql );
			$resultIDs = array();

			// add all the result ids to our array
			foreach( $results as $result ){
				$resultIDs[ $result['ResultObject_ID'] ] = $result['ResultObject_ID'];
			}
			
			// convert our sql result into SilverStripe objects, of the appropriate class
			$resultObjects = $details['ClassName']::get()->filter('ID', $resultIDs );
			
			$allResults->merge( $resultObjects );
		}
		
		// sort our results
		$allResults->removeDuplicates('ID');
		$allResults = $allResults->Sort('PublishDate DESC');
		
		// load into a paginated list. To change the items per page, set via the template (ie Results.setPageLength(20))
		$paginatedItems = PaginatedList::create($allResults, $this->owner->request);
		$paginatedItems->setPageLength(20);
		
		return $paginatedItems;
	}
}