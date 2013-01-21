<?php

// Get input parameters
if ( count( $argc ) > 0 ) {
    // Running from command line
    $username = $argv[1];
    $password = $argv[2];
    $delayed_start = ( count( $argc ) >= 4 && $argv[3] == "true" ? true : false );
    $PHP_EOL = "\n";
} else {
    // Running from web server
    $username = $_GET["user"];
    $password = $_GET["pass"];
    $delayed_start = ( $_GET["delay"] == "true" ? true : false );
    $PHP_EOL = "<br />\n";
}

// Start CreditKarma
require_once 'creditkarma.php';
try
{
    // Start a random amount of time before starting
    if ( $delayed_start )
    {
        $hours = rand( 0, 2 ) * 3600;
        $minutes = rand( 0, 60 ) * 60;
        $seconds = $hours + $minutes;
        sleep( $seconds );
    }

    // Run CreditKarma to get info
    $CreditKarma = new CreditKarma( $username, $password );
    $values = $CreditKarma->run();

    // Display results
    echo "[".date( "m/d/Y", $values["LastUpdated"] )."]".$PHP_EOL;
    echo "Score: ".$values["Score"].$PHP_EOL;
    echo "ScoreInsurance: ".$values["ScoreInsurance"].$PHP_EOL;
    echo "ScoreVantage: ".$values["ScoreVantage"].$PHP_EOL;
}
catch( Exception $e )
{
    // Display any errors
    echo "Error: ".$e->getMessage().$PHP_EOL;
}
