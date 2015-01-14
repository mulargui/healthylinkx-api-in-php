<?php
require_once("rest.inc.php");
require_once("constants.inc.php");

//api.healthylinkx.com v4.0

 class API extends REST 
{

private $db = NULL;

public function __construct()
{
	parent::__construct();// Init parent contructor
	error_reporting(0); //set error reporting to none
	$this->dbConnect();// Initiate Database connection
}

//Database connection
private function dbConnect()
{
	$this->db = mysql_connect(DB_SERVER,DB_USER,DB_PASSWORD);
	if($this->db)
		mysql_select_db(DB,$this->db);
}

//Public method for access api.
//This method dynamically calls the method based on the query string
public function processApi()
{
	$func = strtolower(trim(str_replace("/","",$_REQUEST['rquest'])));
	if((int)method_exists($this,$func) > 0)
 		$this->$func();
 	else
		// If the method not exist with in this class, response would be "Page not found".
 		$this->response('method doesnt exist',404);
}

private function providers()
{
	// Cross validation if the request method is GET else it will return "Not Acceptable" status
 	if($this->get_request_method() != "GET")
 		$this->response('GET only',406);
 	
 	//get the parameters
 	$zipcode = $this->_request['zipcode'];
 	$lastname1 = $this->_request['lastname1'];
 	$lastname2 = $this->_request['lastname2'];
 	$lastname3 = $this->_request['lastname3'];
 	$gender = $this->_request['gender'];
 	$specialty = $this->_request['specialty'];
 	$distance = $this->_request['distance'];
 	$zipcodes = NULL;
 	
 	//check params
 	if(empty($zipcode) and empty($lastname1) and empty($specialty))
 		$this->response('not enough parameters',204); // "No Content" status
 	
 	//in case we need to find zipcodes at a distance
 	if (!empty($distance)and !empty($zipcode)){
 		//lets get a few zipcodes
 		$queryapi = "http://zipcodedistanceapi.redline13.com/rest/GFfN8AXLrdjnQN08Q073p9RK9BSBGcmnRBaZb8KCl40cR1kI1rMrBEbKg4mWgJk7/radius.json/$zipcode/$distance/mile";
 		$responsestring = file_get_contents($queryapi);
 		// for DEBUG
 		//$responsestring = "{\"zip_codes\":[{\"zip_code\":\"98052\",\"distance\":4.54},{\"zip_code\":\"98007\",\"distance\":4.247},{\"zip_code\":\"98034\",\"distance\":4.626}]}";
 		if (!$responsestring)	
 			$this->response('error on zipcodedistanceapi',204); // "No Content" status
 	
 		//translate json from string to array
 		$responsejson = json_decode($responsestring,true);
 		if (!$responsejson)	
 			$this->response('unable to decode json',204); // "No Content" status
 	
 		//lets prep a where condition for zip codes
 		$count=count($responsejson['zip_codes']);
 		$zipcodes = "((Provider_Short_Postal_Code = '{$responsejson['zip_codes'][0]['zip_code']}')";
 		for ($i = 1; $i<$count; $i++)
 			$zipcodes .= " OR (Provider_Short_Postal_Code = '{$responsejson['zip_codes'][$i]['zip_code']}')";
 		$zipcodes .= ")";
 	}

 	//building the query string
 	$query = "SELECT NPI,Provider_Full_Name,Provider_Full_Street, Provider_Full_City
 		FROM npidata2 WHERE (";
 	if(!empty($lastname1))
 		$query .= "((Provider_Last_Name_Legal_Name = '$lastname1')";
 	if(!empty($lastname2))
 		$query .= " OR (Provider_Last_Name_Legal_Name = '$lastname2')";
 	if(!empty($lastname3))
 		$query .= " OR (Provider_Last_Name_Legal_Name = '$lastname3')";
 	if(!empty($lastname1))
 		$query .= ")";
 	if(!empty($gender))
 		if(!empty($lastname1))
 			$query .= " AND (Provider_Gender_Code = '$gender')";
 		else
 			$query .= "(Provider_Gender_Code = '$gender')";
 	if(!empty($specialty))
 		if(!empty($lastname1) or !empty($gender))
 			$query .= " AND (Classification = '$specialty')";
 		else
 			$query .= "(Classification = '$specialty')";
 	if(!empty($zipcode)and empty($distance))
 		if(!empty($lastname1)or !empty($gender)or !empty($specialty))
 			$query .= " AND (Provider_Short_Postal_Code = '$zipcode')";
 		else
 			$query .= "(Provider_Short_Postal_Code = '$zipcode')";
 	if(!empty($zipcode)and !empty($distance))
 		if(!empty($lastname1)or !empty($gender)or !empty($specialty))
 			$query .= " AND $zipcodes";
 		else
 			$query .= $zipcodes;
 	$query .= ") limit 50";
 	
 	$sql = mysql_query($query, $this->db);
 	
 	if(mysql_num_rows($sql) <= 0)
 		$this->response('no records',204); // If no records "No Content" status

 	$result = array(); 		 	
	while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC))
 		$result[] = $rlt;
	
	// If success everything is good send header as "OK" and return list of providers in JSON format
 	$this->response($this->json($result), 200);
}

private function transaction()
{
	// Cross validation if the request method is GET else it will return "Not Acceptable" status
	if($this->get_request_method() != "GET")
		$this->response('GET only',406);

	//get the parameters
	$ID = $this->_request['id'];

	//all param empty
	if(empty($ID))
		$this->response('no ID',204); // If no records "No Content" status

	//retrieve the providers
	$query = "SELECT * FROM transactions WHERE (id = '$ID')";
	$sql = mysql_query($query, $this->db);
	
	if(mysql_num_rows($sql) <= 0)
		$this->response('no ID record',204); // If no records "No Content" status
	
	$rlt = mysql_fetch_array($sql,MYSQL_ASSOC);
	
	//get the providers
	$NPI1 = $rlt["NPI1"];
	$NPI2 = $rlt["NPI1"];
	$NPI3 = $rlt["NPI2"];
	
	//get the details of the providers
	$query = "SELECT NPI,Provider_Full_Name,Provider_Full_Street, Provider_Full_City,
		Provider_Business_Practice_Location_Address_Telephone_Number 
 		FROM npidata2 WHERE ((NPI = '$NPI1')";
	if(!empty($NPI2))
		$query .= "OR (NPI = '$NPI2')";
	if(!empty($NPI3))
		$query .= "OR (NPI = '$NPI3')";
	$query .= ")";
	
	$sql = mysql_query($query, $this->db);

	if(mysql_num_rows($sql) <= 0)
		$this->response('no NPI record',204); // If no records "No Content" status
		
	$result = array();
	while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC))
		$result[] = $rlt;

	// If success everything is good send header as "OK" and return list of providers in JSON format
	$this->response($this->json($result), 200);
}

private function taxonomy()
{
	// Cross validation if the request method is GET else it will return "Not Acceptable" status
	if($this->get_request_method() != "GET")
		$this->response('GET only',406);

	//building the query string
	$query = "SELECT * FROM taxonomy";
	$sql = mysql_query($query, $this->db);

	if(mysql_num_rows($sql) <= 0)
		$this->response('no taxonomy records',204); //If no records "No Content" status
		
	$result = array();
	while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC))
		$result[] = $rlt;

	// If success everything is good send header as "OK" and return list of specialities in JSON format
	$this->response($this->json($result), 200);
}
private function shortlist()
{
	// Cross validation if the request method is GET else it will return "Not Acceptable" status
	if($this->get_request_method() != "GET")
		$this->response('GET only',406);

	//get the parameters
	$NPI1 = $this->_request['NPI1'];
	$NPI2 = $this->_request['NPI2'];
	$NPI3 = $this->_request['NPI3'];

	//no param 
	if(empty($NPI1))
		$this->response('no NPI',204); // If no records "No Content" status
	
	//save the selection
	$query = "INSERT INTO transactions VALUES (DEFAULT,DEFAULT,'$NPI1','$NPI2','$NPI3')";
	$sql = mysql_query($query, $this->db);
	
	if(mysql_affected_rows() !=1)
		$this->response('error inserting',204); // If no records "No Content" status

	//return the transaction ID
	$result = array();
	$result["Transaction"] = mysql_insert_id();
	
	//return detailed data of the selected providers
	$query = "SELECT NPI,Provider_Full_Name,Provider_Full_Street, Provider_Full_City,
		Provider_Business_Practice_Location_Address_Telephone_Number
 		FROM npidata2 WHERE ((NPI = '$NPI1')";
	if(!empty($NPI2))
		$query .= "OR (NPI = '$NPI2')";
	if(!empty($NPI3))
		$query .= "OR (NPI = '$NPI3')";
	$query .= ")";
		
	$sql = mysql_query($query, $this->db);

	if(mysql_num_rows($sql) <= 0)
		$this->response('no NPI records',204); // If no records "No Content" status

	$providersdata = array();
	while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC))
		$providersdata[] = $rlt;
	$result["Providers"] = $providersdata;

	// If success everything is good send header as "OK" and return list of providers in JSON format
	$this->response($this->json($result), 200);
}

//Encode array into JSON
private function json($data)
{
 	if(is_array($data))
 		return json_encode($data);
}
}

// Initiate Library
$api = new API;
$api->processApi();
?>