<?php

/****************************************************************************


This program is designed to demonstrate how to use PhP, MySQL and Silex to 
implement a web application that accesses a database.

Files:  The application is made up of the following files

php: 	index.php - This file has all of the php code in one place.  It is found in 
		the public_html/basketball/ directory of the code source.
		
		connect.php - This file contains the specific information for connecting to the
		database.  It is stored two levels above the index.php file to prevent the db 
		password from being viewable.
		
twig:	The twig files are used to set up templates for the html pages in the application.
		There are 7 twig files:
		- home.twig - home page for the web site
		- footer.twig - common footer for each of he html files
		- header.twig - common header for each of the html files
		- form.html.twig - template for forms html files (login and register)
		- item.html.twig - template for player information to be displayed
		- search.html.twig - template for search results
		
		The twig files are found in the public_html/basketball/views directory of the source code
		
Silex Files:  Composer was used to compose the needed Service Providers from the Silex 
		Framework.  The code created by composer is found in the vendor directory of the
		source code.  This folder should be stored in a directory called basketball that is 
		at the root level of the application.  This code is used by this application and 
		has not been modified.


*****************************************************************************/

// Set time zone  
date_default_timezone_set('America/New_York');

/****************************************************************************   
Silex Setup:
The following code is necessary for one time setup for Silex 
It uses the appropriate services from Silex and Symfony and it
registers the services to the application.
*****************************************************************************/
// Objects we use directly
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Provider\FormServiceProvider;

// Pull in the Silex code stored in the vendor directory
require_once __DIR__.'/../../silex-files/vendor/autoload.php';

// Create the main application object
$app = new Silex\Application();

// For development, show exceptions in browser
$app['debug'] = true;

// For logging support
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));

// Register validation handler for forms
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Register form handler
$app->register(new FormServiceProvider());

// Register the session service provider for session handling
$app->register(new Silex\Provider\SessionServiceProvider());

// We don't have any translations for our forms, so avoid errors
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
        'translator.messages' => array(),
    ));

// Register the TwigServiceProvider to allow for templating HTML
$app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/views',
    ));

// Change the default layout 
// Requires including boostrap.css
$app['twig.form.templates'] = array('bootstrap_3_layout.html.twig');

/*************************************************************************
 Database Connection and Queries:
 The following code creates a function that is used throughout the program
 to query the MySQL database.  This section of code also includes the connection
 to the database.  This connection only has to be done once, and the $db object
 is used by the other code.

*****************************************************************************/
// Function for making queries.  The function requires the database connection
// object, the query string with parameters, and the array of parameters to bind
// in the query.  The function uses PDO prepared query statements.

function queryDB($db, $query, $params) {
    // Silex will catch the exception
    $stmt = $db->prepare($query);
    $results = $stmt->execute($params);
    $selectpos = stripos($query, "select");
    if (($selectpos !== false) && ($selectpos < 6)) {
        $results = $stmt->fetchAll();
    }
    return $results;
}



// Connect to the Database at startup, and let Silex catch errors
$app->before(function () use ($app) {
    include '../../connect3.php';
    $app['db'] = $db;
});

/*************************************************************************
 Application Code:
 The following code implements the various functionalities of the application, usually
 through different pages.  Each section uses the Silex $app to set up the variables,
 database queries and forms.  Then it renders the pages using twig.

*****************************************************************************/

 
// Player Result Page

$app->get('/item/{playerID}', function (Silex\Application $app, $playerID) {
    // Create query to get the player with the given playerID
    $db = $app['db'];
    $query = "SELECT p.fname as fname, p.lname as lname, dob, height, weight, position, team, mpg, ppg, rpg, apg, spg, bpg, fg, t.name as teamname
    	 FROM player p, team t
    	 WHERE p.teamID = t.teamID AND
    	 playerID = ?";
    $results = queryDB($db, $query, array($playerID));
    
    // Display results in item page
    return $app['twig']->render('item.html.twig', array(
        'pageTitle' => $results[0]['fname'].' '.$results[0]['lname'],
        'results' => $results
    ));
});

// *************************************************************************

// Team Result Page

$app->get('/team/{teamID}', function (Silex\Application $app, $teamID) {
    // Create query to get the team with the given teamID
    $db = $app['db'];
    $query = "SELECT * FROM team WHERE teamID = ?";
    $results = queryDB($db, $query, array($teamID));
    
    // Display results in item page
    return $app['twig']->render('teamItem.html.twig', array(
        'pageTitle' => $results[0]['name'],
        'results' => $results
    ));
});

// *************************************************************************

// Game Result Page

$app->get('/game/{gameID}', function (Silex\Application $app, $gameID) {
    // Create query to get the game with the given gameID
    $db = $app['db'];
    $query = "SELECT * FROM game WHERE gameID = ?";
    $results = queryDB($db, $query, array($gameID));
    
    // Display results in item page
    return $app['twig']->render('gameItem.html.twig', array(
        'pageTitle' => $results[0]['awayteamID'].' @ '.$results[0]['hometeamID'].' - '.$results[0]['date'],
        'results' => $results
    ));
});

// *************************************************************************

// Player Search Result Page

$app->match('/search', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query for players
        	$db = $app['db'];
		$query = "SELECT * FROM player WHERE fname like ? OR lname like ? OR team like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%', '%'.$srch.'%', '%'.$srch.'%'));
		
		
        // Display results in search page
        return $app['twig']->render('search.html.twig', array(
            'pageTitle' => 'Search',
            'form' => $form->createView(),
            'results' => $results
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('search.html.twig', array(
        'pageTitle' => 'Search',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// Team Search Result Page

$app->match('/teamSearch', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query for teams
        	$db = $app['db'];
		$query = "SELECT * FROM team WHERE name like ? OR city like ? OR teamID like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%', '%'.$srch.'%', '%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('teamSearch.html.twig', array(
            'pageTitle' => 'Search Teams',
            'form' => $form->createView(),
            'results' => $results
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('teamSearch.html.twig', array(
        'pageTitle' => 'Search Teams',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// Game Search Result Page

$app->match('/gameSearch', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query for games
        	$db = $app['db'];
		$query = "SELECT * FROM game WHERE date like ? OR awayteam like ? OR hometeam like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%', '%'.$srch.'%', '%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('gameSearch.html.twig', array(
            'pageTitle' => 'Search Games',
            'form' => $form->createView(),
            'results' => $results
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('gameSearch.html.twig', array(
        'pageTitle' => 'Search Games',
        'form' => $form->createView(),
        'results' => ''
    ));
});

// *************************************************************************

// Home Page

$app->get('/', function () use ($app) {
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
	}
	else {
		$user = '';
	}
	return $app['twig']->render('home.twig', array(
        'user' => $user,
        'pageTitle' => 'Home'));
});

// *************************************************************************

// Run the Application

$app->run();