<?php

/**
 * An example web service. 
 * 
 * Note that it is NOT necessary to declare both WebServiceable AND
 * webEnabledMethods; it's just done for completeness
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class AuthWebService implements WebServiceable {

    public function __construct() {
        
    }
    
    public function webEnabledMethods () {
        return array (
            'authenticate' => 'GET',
        );
    }
    
    public function authenticate ($email, $password) {
        if ($user = DataObject::get_one ('Member', "Email = '".$email."'")) {
            if ($user->checkPassword ($password)) {
                //Log the member in
                $user->login ();
                //Return the token and security id
                return array ('status' => 'success', 'token' => $user->Token);
            }
        }
        return array ('status' => 'failure');
    }
}