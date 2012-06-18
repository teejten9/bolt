<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware function to check whether a user is logged on.
 */
$checkLogin = function(Request $request) use ($app) {
    
    if (!$app['session']->has('user')) {
        return $app->redirect('/pilex/login');
    }
    
};






/**
 * "root"
 */
$app->get("/pilex", function(Silex\Application $app) {

    $twigvars = array();

    $twigvars['content'] = "Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation.";

    if (!$app['storage']->checkTablesIntegrity()) {
        $app['session']->setFlash('error', "The database needs to be updated / repaired. Go to 'Settings' > 'Check Database' to do this now.");   
    }

    // get the 'latest' from each of the content types. 
    foreach ($app['config']['contenttypes'] as $key => $contenttype) {
        
        $twigvars['latest'][$key] = $app['storage']->getContent($key, array('limit' => 5, 'order' => 'datechanged DESC'));
        
    }

    

    
    return $app['twig']->render('dashboard.twig', $twigvars);


})->before($checkLogin);




/**
 * Login page
 */
$app->match("/pilex/login", function(Silex\Application $app, Request $request) {

    $twigvars = array();
    

    if ($request->server->get('REQUEST_METHOD') == "POST") {
      
      $username = strtolower(trim($request->request->get('username')));
    
	    if ($username == "admin" && $request->request->get('password') == "password") {
	        
	        $app['session']->start();
	        $app['session']->set('user', array('username' => $request->request->get('username')));
	        $app['session']->setFlash('success', "You've been logged on successfully.");    
	        
	        return $app->redirect('/pilex');
	        
	    } else {
	        $app['session']->setFlash('error', 'Username or password not correct. Please check your input.');    
	    }
    
    }
    
    return $app['twig']->render('login.twig', $twigvars);

})->method('GET|POST');


/**
 * Logout page
 */
$app->get("/pilex/logout", function(Silex\Application $app) {

	$app['session']->setFlash('info', 'You have been logged out.');
    $app['session']->remove('user');
    
    return $app->redirect('/pilex/login');
        
});



/**
 * Check the database, create tables, add missing/new columns to tables
 */
$app->get("/pilex/dbupdate", function(Silex\Application $app) {
	
	
	$twigvars = array();
	
	$twigvars['title'] = "Database check / update";

	
	
	$output = $app['storage']->repairTables();
	
	if (empty($output)) {
    	$twigvars['content'] = "<p>Your database is already up to date.<p>";
	} else {
    	$twigvars['content'] = "<p>Modifications made to the database:<p>";
    	$twigvars['content'] .= implode("<br>", $output);
    	$twigvars['content'] .= "<p>Your database is now up to date.<p>";

	}
	
	$twigvars['content'] .= "<br><br><p><a href='/pilex/prefill'>Fill the database</a> with Loripsum.</p>";
	
	return $app['twig']->render('base.twig', $twigvars);
	
})->before($checkLogin);




/**
 * Check the database, create tables, add missing/new columns to tables
 */
$app->get("/pilex/prefill", function(Silex\Application $app) {
	
	
	$twigvars = array();
	
	$twigvars['title'] = "Database prefill";

	
	$twigvars['content'] = $app['storage']->preFill();

	
	return $app['twig']->render('base.twig', $twigvars);
	
})->before($checkLogin);



/**
 * Check the database, create tables, add missing/new columns to tables
 */
$app->get("/pilex/overview/{contenttype}", function(Silex\Application $app, $contenttype) {
	
	
	$twigvars = array();
	
    $twigvars['contenttype'] = $app['storage']->getContentType($contenttype);


	$twigvars['multiplecontent'] = $app['storage']->getContent($contenttype, array('limit' => 100, 'order' => 'datechanged DESC'));
        


	return $app['twig']->render('overview.twig', $twigvars);
	
})->before($checkLogin);


/**
 * Edit a unit of content, or create a new one.
 */
$app->match("/pilex/edit/{contenttypeslug}/{id}", function($contenttypeslug, $id, Silex\Application $app, Request $request) {
        
    $twigvars = array();
    
    $twigvars['contenttype'] = $contenttype = $app['storage']->getContentType($contenttypeslug);        
        
    if ($request->server->get('REQUEST_METHOD') == "POST") {
        
        // $app['storage']->saveContent($contenttypeslug)
        if ($app['storage']->saveContent($request->request->all(), $contenttypeslug)) {
        
            if (!empty($id)) {
                $app['session']->setFlash('success', "The changes to this " . $contenttype['singular_name'] . " have been saved."); 
            } else {
                $app['session']->setFlash('success', "The new " . $contenttype['singular_name'] . " have been saved."); 
            }
            return $app->redirect('/pilex/overview/'.$contenttypeslug);
        
        } else {
        
            $app['session']->setFlash('error', "There was an error saving this " . $contenttype['singular_name'] . "."); 
        
        } 
    
    }      
      
	if (!empty($id)) {
      	$twigvars['content'] = $app['storage']->getSingleContent($contenttypeslug, array('where' => 'id = '.$id));
	} else {
    	$twigvars['content'] = $app['storage']->getEmptyContent($contenttypeslug);
	}
      


	// b0rken..
	// $form = $app['form.factory']->createBuilder('form', $content);

	return $app['twig']->render('editcontent.twig', $twigvars);
	
})->before($checkLogin)->assert('id', '\d*')->method('GET|POST');;







/**
 * Error page.
 */
$app->error(function(Exception $e) use ($app) {

    $app['monolog']->addError(json_encode(array(
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTrace()
        )));

    $twigvars = array();

    $twigvars['class'] = get_class($e);
    $twigvars['message'] = $e->getMessage();
    $twigvars['code'] = $e->getCode();

	$trace = $e->getTrace();;

	unset($trace[0]['args']);

    $twigvars['trace'] = print_r($trace[0], true);

    $twigvars['title'] = "Een error!";

    return $app['twig']->render('error.twig', $twigvars);

});