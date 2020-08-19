<?php

ContentController::add_extension('SearchControllerExtension');
Page_Controller::add_extension('SearchFormsExtension');
SearchController::set_types_available( array(
	// 'Vacancies' => array(
	// 	'ClassName' => 'Vacancy',
	// 	'Table' => 'Vacancy_Live',
	// 	'ExtraTables' => array('Article_Live'),
	// 	'ExtraWhere' => 'ShowInSearch = 1',
	// 	'Columns' => array('Title','MenuTitle','Content')
	// ),
	'Stories' => array(
		'ClassName' => 'Story',
		'Table' => 'Story_Live',
		'ExtraTables' => array('Article_Live'),
		'ExtraWhere' => 'ShowInSearch = 1',
		'Columns' => array('Title','MenuTitle','Content')
	),
	'Authors' => array(
		'ClassName' => 'Profile',
		'Table' => 'Profile_Live',
		'ExtraTables' => array('Article_Live'),
		'ExtraWhere' => 'ShowInSearch = 1',
		'Columns' => array('Title','MenuTitle','Content')
	),
	'Pages' => array(
		'ClassName' => 'Page',
		'Table' => 'Page_Live',
		'ExtraWhere' => 'ShowInSearch = 1',
		'Columns' => array('Title','MenuTitle','Content')
	),
	'Returned Volunteers' => array(
		'ClassName' => 'VolunteerPosting',
		'Table' => 'VolunteerPosting',
		'Columns' => array('MemberName')
	)
));
