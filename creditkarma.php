<?php

require_once "simple_html_dom.php";

/**
 * CreditKarma class.
 */
class CreditKarma
{
    /**
     * Site URL for retrieving pages, no trailing slash
     *
     * @var string
     * @access private
     */
    private $http_site_url = "https://www.creditkarma.com";

    /**
     * User Agent as seen by the web server
     *
     * @var string
     * @access private
     */
    private $user_agent_string = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)";

    // Private variables
    private $logon_user = "";
    private $logon_pass = "";
    private $logon_stk = "";
    private $cookie_file = "";
    private $cache_file = "";

    /**
     * Constructor for loading the username and password
     *
     * @access public
     * @param mixed $username
     * @param mixed $password
     * @return void
     */
    function __construct( $username, $password )
    {
        // Set private variables
        $this->logon_user = $username;
        $this->logon_pass = $password;
        $this->logon_stk = "";
        $this->cookie_file = "cookie/".$this->logon_user;
        $this->cache_file = "cache/".$this->logon_user;

        // Make sure cookie directory exists
        if ( !is_dir( dirname( $this->cookie_file ) ) )
            mkdir( dirname( $this->cookie_file ), 0777, true );
        chmod( dirname( $this->cookie_file ), 0777 );

        // Make sure the cookie file exists
        touch( $this->cookie_file );
        chmod( $this->cookie_file, 0777 );

        // Make sure cache directory and file exists
        if ( !is_dir( dirname( $this->cache_file ) ) )
            mkdir( dirname( $this->cache_file ), 0777, true );
        chmod( dirname( $this->cache_file ), 0777 );

        // Make sure the cache file exists
        touch( $this->cache_file );
        chmod( $this->cache_file, 0777 );
    }


    public function run()
    {
        // Perform the update and get scores and last-updated date
        $update_values = $this->doUpdate();
        $LastUpdated = $update_values["LastUpdated"];
        $Score = $update_values["Score"];

        // Get the Insurance Score
        $ScoreInsurance = $this->getScoreInsurance();

        // Get the Vantage Score
        $ScoreVantage = $this->getScoreVantage();

        // Get the cache file contents
        $cache_file_contents = "";
        if ( file_exists( $this->cache_file ) )
            $cache_file_contents = file_get_contents( $this->cache_file );

        // Initialize the cache data array
        $cache_data = unserialize( $cache_file_contents );
        if ( !is_array( $cache_data ) )
            $cache_data = array();

        // Store the data in the cache
        $cache_index = date( "Y-m-d", $LastUpdated );
        $cache_data[ $cache_index ] = array(
            "Score"          => $Score,
            "ScoreInsurance" => $ScoreInsurance,
            "ScoreVantage"   => $ScoreVantage,
        );

        // Save the cache data
        file_put_contents( $this->cache_file, serialize( $cache_data ) );

        // return the results
        return array(
            "LastUpdated"    => $LastUpdated,
            "Score"          => $Score,
            "ScoreInsurance" => $ScoreInsurance,
            "ScoreVantage"   => $ScoreVantage,
        );
    }

    public function getLastUpdated()
    {
        $date_string = $this->getPageValue( "/score", "div#scoreInfo p.lastUpdated", 0 );
        $date_string = explode( ":", $date_string );
	$date_string = $date_string[1];
        return strtotime( $date_string );
    }

    public function getScore()
    {
        return $this->getPageValue( "/score", "div#scoreInfo h5", 0 );
    }

    public function getScoreInsurance()
    {
        return $this->getPageValue( "/scoreinsurance", "div#scoreInfo h5", 0 );
    }

    public function getScoreVantage()
    {
        return $this->getPageValue( "/scorevantage", "div#scoreInfo h5", 0 );
    }

    public function doUpdate()
    {
        $values = $this->getPageValues(
            "/scoretransrisk/getscore",
            array(
                "div#scoreInfo p.lastUpdated",
                "div#scoreInfo h5",
            )
        );

        $date_string = explode( ":", $values[0] );
	$LastUpdated = strtotime( $date_string[1] );
        $Score = $values[1];

        return array(
            "LastUpdated"  => $LastUpdated,
            "Score"        => $Score,
        );
    }

    public function doLogon( $html_object )
    {
        // Check the logon status
        $form_object = $html_object->find( "form#logonform", 0 );
        $member_object = $html_object->find( "img.memberImage", 0 );
        $logon_status = ( !$form_object && $member_object );

        // If logon status is active, return that nothing was done
        if ( $logon_status )
            return false;

        // Parse the Logon page
        $logon_submit_url = $form_object->action;
        $this->logon_stk = $form_object->find( "input[name=stk]", 0 )->value;

        // Check for errors
        if ( !$logon_submit_url )
            throw new Exception( "Invalid Logon URL" );
        if ( !$this->logon_user )
            throw new Exception( "Invalid Logon User" );
        if ( !$this->logon_pass )
            throw new Exception( "Invalid Logon Pass" );
        if ( !$this->logon_stk )
            throw new Exception( "Invalid Logon STK" );

        // Submit Logon action
        $response_string = $this->getResponse(
            $logon_submit_url,
            array(
                "username"  => $this->logon_user,
                "password"  => $this->logon_pass,
                "stk"       => $this->logon_stk,
            )
        );
        if ( !$response_string )
            throw new Exception( "Missing Page Response String" );

        // Check html string and convert to object
        $html_object = str_get_html( $response_string );
        if ( !$html_object )
            throw new Exception( "Invalid Page HTML Object" );

        // Check for errors
        $username_error_text = "";
        if ( $username_error_object = $html_object->find( "div#advice-username", 0 ) )
            $username_error_text = $username_error_object->innertext();
        $password_error_text = "";
        if ( $password_error_object = $html_object->find( "div#advice-password", 0 ) )
            $password_error_text = $password_error_object->innertext();
        $general_error_text = "";
        if ( $general_error_object = $html_object->find( "div.promptContainer.promptalert p", 0 ) )
            $general_error_text = $general_error_object->innertext();

        // If errors, stop here
        if ( $username_error_text )
            throw new Exception( "Logon Error: ".$username_error_text );
        if ( $password_error_text )
            throw new Exception( "Logon Error: ".$password_error_text );
        if ( $general_error_text )
            throw new Exception( "Logon Error: ".$general_error_text );

        // Return that something was done
        return true;
    }

    public function doLogoff()
    {
        $this->logon_stk = "";
        if ( $this->cookie_file && file_exists( $this->cookie_file ) )
            unlink( $this->cookie_file );
    }





    /**
     * Get a page from the server and return the tag contents of the selector
     *
     * @access private
     * @param mixed $path
     * @param mixed $selector
     * @param int $selector_id (default: 0)
     * @return mixed
     */
    private function getPageValue( $path, $selector, $selector_id = 0 )
    {
        // Get the HTML object
        $html_object = $this->getPage( $path );
        if ( !$html_object )
            throw new Exception( "Invalid HTML Object (getPageValue)" );

        // Get the Tag object
        $tag_object = $html_object->find( $selector, $selector_id );
        if ( !$tag_object )
            throw new Exception( "Invalid Tag Object (getPageElement)" );

        // Return the contents of the tag object
        return trim( $tag_object->innertext() );
    }

    /**
     * Get a page from the server and return the tag contents of the selector
     *
     * @access private
     * @param mixed $path
     * @param mixed $selectors
     * @param int $selector_ids (default: "")
     * @return mixed
     */
    private function getPageValues( $path, $selectors, $selector_ids = "" )
    {
        // Get the HTML object
        $html_object = $this->getPage( $path );
        if ( !$html_object )
            throw new Exception( "Invalid HTML Object (getPageValues)" );

        // Get all of the selected tags
        $values = array();
        foreach( $selectors as $index => $selector )
        {
            // Determine the selector id for this array index
            $selector_id = 0;
            if ( is_array( $selector_ids ) && $selector_ids[ $index ] )
                $selector_id = $selector_ids[ $index ];

            // Get the Tag object
            $tag_object = $html_object->find( $selector, $selector_id );
            if ( !$tag_object )
                throw new Exception( "Invalid Tag Object (getPageValues)" );

            // Store the contents of the tag object
            $values[] = trim( $tag_object->innertext() );
        }

        // Return the values array
        return $values;
    }

    /**
     * Get a page from the server and return the parsed html object
     *
     * @access private
     * @param mixed $path
     * @param bool $fail_on_logon_form (default: false)
     * @return object
     */
    private function getPage( $path, $fail_on_logon_form = false )
    {
        // Request page and parse results
        $response_string = $this->getResponse( $this->http_site_url.$path );
        if ( !$response_string )
            throw new Exception( "Missing Page Response String" );

        // Check html string and convert to object
        $html_object = str_get_html( $response_string );
        if ( !$html_object )
            throw new Exception( "Invalid Page HTML Object" );

        // Check for logon form
        if ( $this->doLogon( $html_object ) )
        {
            // Prevent loops to logon page
            if ( $fail_on_logon_form )
                throw new Exception( "Logon Redirect Loop" );

            // Run the request again
            return $this->getPage( $path, true );

        }

        // Return html object
        return $html_object;
    }

    /**
     * Perform an HTTP request and return the result string
     *
     * @access private
     * @param string $url
     * @param array $post_data (default: null)
     * @return string
     */
    private function getResponse( $url, $post_data = null )
    {
        // Create CURL request object
        $request = curl_init( $url );

        // Set CURL options
        if ( $this->cookie_file )
        {
            if ( !is_dir( dirname( $this->cookie_file ) ) )
                mkdir( dirname( $this->cookie_file ), 0777, true );
            curl_setopt( $request, CURLOPT_COOKIEFILE, $this->cookie_file ); // Store cookies in the specified file
            curl_setopt( $request, CURLOPT_COOKIEJAR, $this->cookie_file ); // Store cookies in the specified file
        }
        curl_setopt( $request, CURLOPT_ENCODING, "gzip" ); // Turn on GZIP compression
        curl_setopt( $request, CURLOPT_USERAGENT, $this->user_agent_string ); // Set user agent
    	curl_setopt( $request, CURLOPT_HEADER, 0 ); // set to 0 to eliminate header info from response
    	curl_setopt( $request, CURLOPT_RETURNTRANSFER, 1 ); // Returns response data instead of TRUE(1)
        curl_setopt( $request, CURLOPT_FOLLOWLOCATION, TRUE );
    	if ( is_array( $post_data ) )
        	curl_setopt( $request, CURLOPT_POSTFIELDS, http_build_query( $post_data ) ); // use HTTP POST to send form data

    	// Execute the request
    	$response = curl_exec( $request );

    	// Check for curl errors
    	if ( curl_errno( $request ) )
    	   throw new Exception( "Response Error: ".curl_error( $request ) );

        // Close CURL request object
        curl_close( $request );

        // Sleep to prevent hitting the server too fast
        sleep( 1 );

        // Return the CURL response
        return $response;
    }
}
