<?php  

namespace PlasticStudio\Search;

use PageController;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Config\Config;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Director;

class SearchPageController extends PageController {
	
	// these statics hold the search data
	private static $query;
	private static $types;
	private static $filters;
	private static $sort;
	private static $defaults;
	private static $results;
	
	public function index($request){
		
		if (Director::isLive()){
			Requirements::css('plasticstudio/search:client/Search.min.css');
			Requirements::javascript('plasticstudio/search:client/Search.min.js');
		} else {
			Requirements::css('plasticstudio/search:client/Search.css');
			Requirements::javascript('plasticstudio/search:client/Search.js');
		}
		
		// get the parameters and variables of this request (ie the query and filters)
		$vars = $request->requestVars();
		
		if (isset($vars['query']) && $vars['query'] != ''){
			self::set_query($vars['query']);
			unset($vars['query']);
		}
		
		if (isset($vars['types']) && $vars['types'] != ''){
			self::set_types(explode(',',$vars['types']));
			unset($vars['types']);
		}
		
		if (isset($vars['sort']) && $vars['sort'] != ''){
			self::set_sort($vars['sort']);
			unset($vars['sort']);
		}

		self::set_filters($vars);
		self::set_results($this->PerformSearch());
		
		return [];
	}
	
	
	/**
	 * Getters
	 **/

	public static function get_types_available(){
		$types = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'types');
		$array = [];

		if ($types){
			foreach ($types as $key => $value){
				$value['Key'] = $key;
				$array[$key] = $value;
			}
		}

		return $array;
	}

	public static function get_filters_available(){
		$filters = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'filters');
		$array = [];

		if ($filters){
			foreach ($filters as $key => $value){
				$value['Key'] = $key;
				$array[$key] = $value;
			}
		}

		return $array;
	}

	public static function get_sorts_available(){
		$sorts = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'sorts');
		$array = [];

		if ($sorts){
			foreach ($sorts as $key => $value){
				$value['Key'] = $key;
				$array[$key] = $value;
			}
		}

		return $array;
	}

	public static function get_defaults_available(){
		$defaults = Config::inst()->get('PlasticStudio\Search\SearchPageController', 'defaults');
		$array = [];

		if ($defaults){
			foreach ($defaults as $key => $value){
				$array[$key] = $value;
			}
		}

		return $array;
	}
	
	public static function get_types(){
		return self::$types;
	}
	
	public static function set_types($types){
		self::$types = $types;
	}
	
	public static function get_mapped_types(){
		$types_available = self::get_types_available();
		$mapped_types = [];
		if ($types = self::get_types()){
			foreach (self::get_types() as $key){
				if (isset($types_available[$key])){
					$mapped_types[] = $types_available[$key];
				}
			}
		} else {
			$mapped_types = $types_available;
		}
		return $mapped_types;
	}
	
	public static function get_filters(){
		return self::$filters;
	}
	
	public static function set_filters($filters){
		self::$filters = $filters;
	}
	
	public static function get_mapped_filters(){
		$filters_available = self::get_filters_available();
		$mapped_filters = [];

		foreach (self::get_filters() as $key => $value){
			if (isset($filters_available[$key])){
				$filter = $filters_available[$key];
				$filter['Value'] = $value;
				$mapped_filters[] = $filter;
			}
		}
		return $mapped_filters;
	}
	
	public static function get_query($mysqlSafe = false){
		$query = self::$query;
		if ($query) {
			if ($mysqlSafe) {
				$query = str_replace("'", "\'", $query);
				$query = str_replace('"', '\"', $query);
				$query = str_replace('`', '\`', $query);
			}
		}
		return $query;
	}
	
	public static function set_query($query = null){
		self::$query = $query;
	}
	
	public static function get_sort(){
		return self::$sort;
	}
	
	public static function set_sort($sort){
		self::$sort = $sort;
	}
	
	public static function get_mapped_sort(){
		$sorts_available = self::get_sorts_available();
		$sort = self::get_sort();

		// If no sort, assume the first item
		if (!$sort){
			return reset($sorts_available);
		} else {
			return $sorts_available[$sort];
		}
	}
	
	public static function get_defaults(){
		return self::$defaults;
	}
	
	public static function set_defaults($defaults){
		self::$defaults = $defaults;
	}
	
	public static function get_mapped_defaults(){
		return self::get_defaults_available();
	}
	
	public static function get_results(){
		return self::$results;
	}
	
	public static function set_results($results){
		self::$results = $results;
	}
	
	/**
	 * Get the search query
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Query(){
		return self::get_query();
	}
	
	
	/**
	 * Get the results
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Results(){
		return self::get_results();
	}

	
	/**
	 * Get the search query
	 * This is just an alias to get my static variable
	 * @return ArrayList
	 **/
	public function Sort(){
		return self::get_sort();
	}
	
	
	/**
	 * Get the types searched
	 * This is just an alias to get my static variable. We then construct them as ArrayList for template use
	 * @return ArrayList
	 **/
	public function Types(){
		$types = self::get_types();
		$types_available = self::get_types_available();
		if (!$types){
			return false;
		}

		$completeTypes = ArrayList::create();
		foreach ($types as $type){
			if (isset($types_available[$type])){
				$completeTypes->push($types_available[$type]);
			}
		}

		return $completeTypes;	
	}
	
	
	/**
	 * Have a squiz through our site and find all matches
	 * @return PaginatedList
	 **/
	/*
		SELECT Programme_Live.Name AS "Name", Programme_Live.ElementalSearchContent AS "Content",
		(CASE 
		WHEN Programme_Live.Name LIKE '%New Zealand Diploma%' THEN 1 
		WHEN Programme_Live.ElementalSearchContent LIKE CONCAT('%','enrolment','%') THEN 2
		ELSE 3
		END) AS Priority
		FROM Programme_Live  WHERE 
		(Programme_Live.Name LIKE CONCAT('%','enrolment','%') 
		OR Programme_Live.NiceName LIKE CONCAT('%','enrolment','%') 
		OR Programme_Live.Blurb LIKE CONCAT('%','enrolment','%') 
		OR Programme_Live.ElementalSearchContent LIKE CONCAT('%','enrolment','%')
		) 
		AND (Programme_Live.HideInSearch = 0)  
		ORDER BY `Priority` ASC
	*/
	public function PerformSearch(){
		
		// get all our search requirements
		$query = self::get_query($mysqlSafe = true);
		$types = self::get_mapped_types();
		$filters = self::get_mapped_filters();
		
		// prepare combined result object
		$allResults = array();

		// keep track of the order of types for sorting
		$type_order = 0;
		
		// loop all the records we need to lookup
		foreach ($types as $type){
			
			$sql = '';
			$joins = '';
			$where = '';
			$sort = '';
			
			/**
			 * Result selection
			 * We only need ClassName and ID to fetch the full object (using the SilverStripe ORM)
			 * once we've got our results
			 **/
			$sql.= "SELECT \"".$type['Table']."\".\"ID\" AS \"ResultObject_ID\", ";

			// add columns to select so can add priority
			foreach ($type['Columns'] as $i => $column){
				$column = explode('.', $column);
				$sql.= "$column[0].$column[1], ";
			}

			// add priority as per order of columns
			$priority = 1;
			$sql.= "CASE ";
			foreach ($type['Columns'] as $i => $column){
				$column = explode('.',$column);
				$sql.= "WHEN $column[0].$column[1] LIKE '%".$query."%'" . " THEN $priority ";
				$priority++;
			}
			$sql.= "ELSE $priority END AS Priority ";

			$sql.="FROM \"".$type['Table']."\" ";
			
			// Join this type with any dependent tables (if applicable)
			if (isset($type['JoinTables'])){
				foreach ($type['JoinTables'] as $joinTable){
					$joins.= "LEFT JOIN \"".$joinTable."\" ON \"".$joinTable."\".\"ID\" = \"".$type['Table']."\".\"ID\" ";		
				}
			}		
			
			/**
			 * Query term
			 * We search each column for this type for the provided query string

			 */
			$where .= ' WHERE (';
			foreach ($type['Columns'] as $i => $column){
				$column = explode('.',$column);
				if ($i > 0){
					$where .= ' OR ';
				}
				$where .= "\"".$column[0]."\".\"".$column[1]."\" LIKE CONCAT('%','".$query."','%')";
			}
			$where.= ')';
			
			
			/**
			 * Apply our type-level filters (if applicable)
			 **/		
			if (isset($type['Filters'])){
				foreach ($type['Filters'] as $key => $value){
					$where.= ' AND ('.$key.' = '.$value.')';
				}
			}
			
			
			/**
			 * Apply filtering
			 **/
			$relations_sql = '';

			if ($filters){
				foreach ($filters as $filter){

					// Apply filters, based on filter structure
					switch ($filter['Structure']){

						/**
						 * Simple column value filter
						 **/
						case 'db':

							// Identify which table has the column which we're trying to filter by
							$table_with_column = null;
							if (isset($type['JoinTables'])){
								$tables_to_check = $type['JoinTables'];
							} else {
								$tables_to_check = [];
							}
							$tables_to_check[] = $type['Table'];

							foreach ($tables_to_check as $table_to_check){
								$column_exists_query = DB::query( "SHOW COLUMNS FROM \"".$table_to_check."\" LIKE '".$filter['Column']."'" );

								foreach ($column_exists_query as $column){
									$table_with_column = $table_to_check;
								}
							}

							// Not anywhere in this type's table joins, so we can't search this particular type
							if (!$table_with_column){
								continue 2;
							}
					
							// open our wrapper
							$where.= ' AND (';
							
							/**
							 * This particular type needs to join with other parent tables to
							 * form a complete, and searchable row
							 **/
							if (isset($type['JoinTables'])){
								foreach ($type['JoinTables'] as $join_table){
									//$joins.= "LEFT JOIN \"".$type['Table']."\" ON \"".$join_table."\".\"ID\" = \"".$type['Table']."\".\"ID\"";
								}
							}
							
							if (is_array($filter['Value'])){
								$valuesString = '';
								foreach ($filter['Value'] as $value){
									if ($valuesString != ''){
										$valuesString.= ',';
									}
									$valuesString.= "'".$value."'";
								}
							} else {
								$valuesString = $filter['Value'];
							}
							
							$where.= "\"".$table_with_column."\".\"".$filter['Column']."\" ".$filter['Operator']." '".$valuesString ."'";
						
							// close our wrapper
							$where.= ')';

							break;

						/**
						 * Simple relational filter (ie Page.Author)
						 **/
						case 'has_one':
							
							// Identify which table has the column which we're trying to filter by
							$table_with_column = null;
							if (isset($type['JoinTables'])){
								$tables_to_check = $type['JoinTables'];
							} else {
								$tables_to_check = [];
							}
							$tables_to_check[] = $type['Table'];

							foreach ($tables_to_check as $table_to_check){
								$column_exists_query = DB::query( "SHOW COLUMNS FROM \"".$table_to_check."\" LIKE '".$filter['Column']."'" );				

								foreach ($column_exists_query as $column){
									$table_with_column = $table_to_check;
								}
							}

							// Not anywhere in this type's table joins, so we can't search this particular type
							if (!$table_with_column){
								continue 2;
							}

							// join the relationship table to our record(s)
							$joins.= "LEFT JOIN \"".$filter['Table']."\" ON \"".$filter['Table']."\".\"ID\" = \"".$table_with_column."\".\"".$filter['Column']."\"";
							
							if(!empty($filter['Value'])){
								if (is_array($filter['Value'])){
									$ids = '';
									foreach ($filter['Value'] as $id){
										if ($ids != ''){
											$ids.= ',';
										}
										$ids.= "'".$id."'";
									}
								} else {
									$ids = $filter['Value'];
								}
								$where.= ' AND ('."\"".$table_with_column."\".\"".$filter['Column']."\" IN (". $ids .")".')';
							}

							break;
						
						/**
						 * Complex relational filter (ie Page.Tags)
						 **/
						case 'many_many':

							// Make sure this type has a relationship to this filter object
							if (isset($filter['JoinTables'][$type['Key']])){

								$filter_join = $filter['JoinTables'][$type['Key']];

								$joins.= "LEFT JOIN \"".$filter_join['Table']."\" ON \"".$type['Table']."\".\"ID\" = \"".$filter_join['Table']."\".\"".$filter_join['Column']."\"";
								
								if(!empty($filter['Value'])){
									if (is_array($filter['Value'])){
										$ids = '';
										foreach ($filter['Value'] as $id){
											if ($ids != ''){
												$ids.= ',';
											}
											$ids.= "'".$id."'";
										}
									} else {
										$ids = $filter['Value'];
									}

									if ($relations_sql !== ''){
										$relations_sql.= " AND ";
									}
									$relations_sql.= "\"".$filter_join['Table']."\".\"".$filter['Table']."ID\" IN (". $ids .")";
								}
							}

							break;
					}
				}

				// Append any required relations SQL
				if ($relations_sql !== ''){
					$where.= ' AND ('.$relations_sql.')';
				}
			}			
			
			// Compile our sql string
			$sql.= $joins;
			$sql.= $where;

			// Apply sorting by priority from CASE statement
			$sql.=" ORDER BY Priority";

			// Debugging
			// echo '<h3 style="position: relative; padding: 20px; background: #EEEEEE; z-index: 999;">'.str_replace('"', '`', $sql).'</h3>';

			// Executioner enter stage left
			$results = DB::query($sql);

			// Add all the result ids to our array
			foreach ($results as $result){

				// add to array list
				$allResults[] = [
					'id' => $result['ResultObject_ID'],
					'priority' => $result['Priority'],
					'class' => $type['ClassName'],
					'type' => $type_order,
				];
			}

			$type_order++;

		}		

		// Sort all results by priority, and keep type order
		array_multisort(array_column($allResults, 'priority'), SORT_ASC, array_column($allResults, 'type'), SORT_ASC, $allResults);
		
		$orderedResults = ArrayList::create();

		// filter by permission
		if($allResults) foreach($allResults as $result) {

			$resultObject = $result['class']::get()->byID($result['id']);

			// check permissions
			if($resultObject->canView()) {
				$orderedResults->push($resultObject);
			}
		}

		// Remove duplicates
		$orderedResults->removeDuplicates('ID');

		$sort = false;
		// Sorting applied throug form submission
		if(isset(self::get_mapped_sort()['Sort'])){
			$sort = self::get_mapped_sort()['Sort'];		
			$sort = str_replace("'", "\'", $sort);
			$sort = str_replace('"', '\"', $sort);
			$sort = str_replace('`', '\`', $sort);
		}
		// Default sort defined in config
		elseif(isset(self::get_mapped_defaults()['sort'])){
			$sort = self::get_mapped_defaults()['sort'];
		}

		if($sort != 'NULL' && $sort != false){
			$orderedResults = $orderedResults->Sort($sort);
		}		
		
		// load into a paginated list. To change the items per page, set via the template (ie Results.setPageLength(20))
		$paginatedItems = PaginatedList::create($orderedResults, $this->request);
		
		return $paginatedItems;
	}
}