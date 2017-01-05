<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/includes/php/ModuleWrapper.php';

function initConfigOptionfromDB()
{
    $data = Capsule::table('tblproducts')->where('servertype', 'OnAppvCD')->get();
    $packageconfigoption = [];
    $packageconfigoption[3] = [];
    if (is_array($data) && count($data) > 0) {
        for($i=1; $i<=24; $i++){
            $packageconfigoption[$i] = $data[0]->{"configoption".$i};
        }
    }

    return $packageconfigoption;
}

function OnAppvCD_ConfigOptions() {
	$data         = new stdClass;
	$data->errors = $data->warnings = new stdClass;
	$module       = new OnAppvCDModule;
	$data->lang   = $module->loadLang()->Admin;

	if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
		$data->error = $data->lang->WrapperNotFound . ' ' . ROOTDIR . '/includes/wrapper';
		goto end;
	}

	$serverGroup = $GLOBALS[ 'servergroup' ];
	/** @var WHMCS_OnApp_Server[] $servers */
	$servers = Capsule::table( 'tblservers AS srv' )
					  ->leftJoin( 'tblservergroupsrel AS rel', 'srv.id', '=', 'rel.serverid' )
					  ->leftJoin( 'tblservergroups AS grp', 'grp.id', '=', 'rel.groupid' )
					  ->where( 'srv.type', OnAppvCDModule::MODULE_NAME )
					  ->where( 'grp.id', $serverGroup )
					  ->where( 'srv.disabled', 0 )
					  ->select(
						  'srv.id',
						  'srv.name',
						  'srv.ipaddress',
						  'srv.secure',
						  'srv.hostname',
						  'srv.username',
						  'srv.password'
					  )
					  ->get();

	if( empty( $servers ) ) {
		$data->error = $data->lang->ServersNone;
	}
	else {
		$data->servers = new stdClass;
		foreach( $servers as $serverConfig ) {
			# error if server IP or hostname are not set
			if( empty( $serverConfig->ipaddress ) && empty( $serverConfig->hostname ) ) {
				$data->error .= $serverConfig->name . ': ' . $data->lang->HostAddressNotFound . PHP_EOL;
				continue;
			}
			$serverConfig->password = decrypt( $serverConfig->password );
			$module                 = new OnAppvCDModule( $serverConfig );

            //compare wrapper version with API
			$compareResult = $module->checkWrapperVersion();
			if( !$compareResult['status'] ){
				$data->error = $data->lang->WrapperUpdate . ' (wrapper version: ' . $compareResult['wrapperVersion'] . '; ' . 'api version: ' . $compareResult['apiVersion'] . ')';
                if($compareResult['apiMessage'] != ''){
                    $data->error .= '; ' . $compareResult['apiMessage'];
                }
                goto end;
            }

			$data->servers->{$serverConfig->id}       = $module->getData();
			$data->servers->{$serverConfig->id}->Name = $serverConfig->name;
		}

		if( $data->servers ) {
			# get additional data
			$data->TimeZones      = file_get_contents( __DIR__ . '/includes/php/tzs.json' );
			$data->TimeZones      = json_decode( $data->TimeZones );

			$data->productOptions = initConfigOptionfromDB();
			if( ! ( empty( $data->productOptions[ 1 ] ) || empty( $data->productOptions[ 24 ] ) ) ) {
				$data->productSettings     = json_decode( $data->productOptions[ 24 ] )->{$data->productOptions[ 1 ]};

				$data->productSettingsJSON = htmlspecialchars( $data->productOptions[ 24 ] );
			}
		}
	}

	end: {
		return [
			'' => [
				'Description' => $module->buildHTML( $data ),
			],
		];
	}
}

function OnAppvCD_CreateAccount( $params ) {
	$module = new OnAppvCDModule( $params );
	$lang   = $module->loadLang()->Admin;
	if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
		return $lang->Error_WrapperNotFound . realpath( ROOTDIR ) . '/includes';
	}

	$clientsDetails  = $params[ 'clientsdetails' ];
	$serviceID       = $params[ 'serviceid' ];
	$serverID        = $params[ 'serverid' ];
	$userName        = $clientsDetails[ 'email' ];
	$password        = OnAppvCDModule::generatePassword();
	$productSettings = json_decode( $params[ 'configoption24' ] )->$serverID;

	if( $productSettings->OrganizationType == 1 ) {
		$userGroup = $productSettings->UserGroups;
	}
	else {
		# create user group // todo add error handling and dupe title
		$labelVal                           = Capsule::table( 'tblcustomfieldsvalues' )
													 ->where( 'relid', $serviceID )
													 ->select( 'value' )
													 ->first();
        $label = $labelVal->value;
		$userGroup                          = $module->getObject( 'UserGroup' );
		$userGroup->label                   = $label;
		$userGroup->assign_to_vcloud        = true;
		$userGroup->hypervisor_id           = $productSettings->HyperVisor;
		$userGroup->company_billing_plan_id = $productSettings->BillingPlanDefault;
        $userGroup->billing_plan_ids        = $productSettings->GroupBillingPlans;
		$userGroup->save();
		$userGroup = $userGroup->id;

        $n        = 0;
        $attempts = 10;
        while ( ! $module->checkObject( 'UserGroup', $userGroup ) ) {
            $n++;
            if ( $n > $attempts ) {
                return $lang->Error_CreateUser;
            }
            sleep( 1 );
        }
	}

	$OnAppUser                   = $module->getObject( 'User' );
	$OnAppUser->_email           = $clientsDetails[ 'email' ];
	$OnAppUser->_password        = $OnAppUser->_password_confirmation = $password;
	$OnAppUser->_login           = $userName;
	$OnAppUser->_first_name      = $clientsDetails[ 'firstname' ];
	$OnAppUser->_last_name       = $clientsDetails[ 'lastname' ];
	$OnAppUser->_billing_plan_id = $productSettings->BillingPlanDefault;
	$OnAppUser->_role_ids        = $productSettings->Roles;
	$OnAppUser->_time_zone       = $productSettings->TimeZone;
	$OnAppUser->_user_group_id   = $userGroup;
	$OnAppUser->_locale          = $productSettings->Locale;

	$OnAppUser->save();

	if( ! is_null( $OnAppUser->getErrorsAsArray() ) ) {
		$errorMsg = $lang->Error_CreateUser . ': ';
		$errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

		return $errorMsg;
	}

	if( ! is_null( $OnAppUser->_obj->getErrorsAsArray() ) ) {
		$errorMsg = $lang->Error_CreateUser . ': ';
		$errorMsg .= $OnAppUser->_obj->getErrorsAsString( ', ' );

		return $errorMsg;
	}

	if( is_null( $OnAppUser->_obj->_id ) ) {
		return $lang->Error_CreateUser;
	}

	# save user link
	Capsule::table( $module::MODULE_NAME . '_Users' )
		   ->insert( [
			   'serviceID'   => $params[ 'serviceid' ],
			   'WHMCSUserID' => $params[ 'userid' ],
			   'OnAppUserID' => $OnAppUser->_obj->_id,
			   'serverID'    => $params[ 'serverid' ],
			   'billingType' => $productSettings->BillingType,
		   ] );

	# save OnApp login and password
	Capsule::table( 'tblhosting' )
		   ->where( 'id', $serviceID )
		   ->update( [
			   'password' => encrypt( $password ),
			   'username' => $userName,
		   ] );

	// todo rename subject
	sendmessage( 'OnApp account has been created', $serviceID );

	return 'success';
}

function OnAppvCD_TerminateAccount( $params ) {
	$module    = new OnAppvCDModule( $params );
	$tableName = $module::MODULE_NAME . '_Users';
	$lang      = $module->loadLang()->Admin;

	$serviceID = $params[ 'serviceid' ];
	$clientID  = $params[ 'clientsdetails' ][ 'userid' ];
	$serverID  = $params[ 'serverid' ];

	$OnAppUserIDVal = Capsule::table( $tableName )
						  ->where( 'serverID', $serverID )
						  ->where( 'serviceID', $serviceID )
						  ->select( 'OnAppUserID' )
						  ->first();
    $OnAppUserID = $OnAppUserIDVal->OnAppUserID;

	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	$module         = new OnAppvCDModule( $params );
	$OnAppUser      = $module->getObject( 'User' );
	$OnAppUser->_id = $OnAppUserID;
	$OnAppUser->delete( true );

	if( ! empty( $OnAppUser->error ) ) {
		$errorMsg = $lang->Error_TerminateUser . ': ';
		$errorMsg .= $OnAppUser->getErrorsAsString( ', ' );

		return $errorMsg;
	}
	else {
		Capsule::table( $tableName )
			   ->where( 'serverID', $serverID )
			   ->where( 'serviceID', $serviceID )
			   ->delete();
	}

	// todo rename subject
	sendmessage( 'OnApp account has been terminated', $serviceID );

	return 'success';
}

function OnAppvCD_SuspendAccount( $params ) {
	$module    = new OnAppvCDModule( $params );
	$tableName = $module::MODULE_NAME . '_Users';
	$lang      = $module->loadLang()->Admin;

	$serverID  = $params[ 'serverid' ];
	$clientID  = $params[ 'clientsdetails' ][ 'userid' ];
	$serviceID = $params[ 'serviceid' ];

    $OnAppUserIDVal = Capsule::table( $tableName )
						  ->where( 'serverID', $serverID )
						  ->where( 'serviceID', $serviceID )
						  ->select( 'OnAppUserID' )
						  ->first();
    $OnAppUserID = $OnAppUserIDVal->OnAppUserID;

	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	$OnAppUser      = $module->getObject( 'User' );
	$OnAppUser->_id = $OnAppUserID;
	$OnAppUser->suspend();
	if( ! is_null( $OnAppUser->error ) ) {
		$errorMsg = $lang->Error_SuspendUser . ':<br/>';
		$errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

		return $errorMsg;
	}

	// todo rename subject
	sendmessage( 'OnApp account has been suspended', $serviceID );

	return 'success';
}

function OnAppvCD_UnsuspendAccount( $params ) {
	$module    = new OnAppvCDModule( $params );
	$tableName = $module::MODULE_NAME . '_Users';
	$lang      = $module->loadLang()->Admin;

	$serverID  = $params[ 'serverid' ];
	$clientID  = $params[ 'clientsdetails' ][ 'userid' ];
	$serviceID = $params[ 'serviceid' ];

    $OnAppUserIDVal = Capsule::table( $tableName )
						  ->where( 'serverID', $serverID )
						  ->where( 'serviceID', $serviceID )
						  ->select( 'OnAppUserID' )
						  ->first();
    $OnAppUserID = $OnAppUserIDVal->OnAppUserID;

	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	$OnAppUser = $module->getObject( 'User' );
	$unset     = [ 'time_zone', 'user_group_id', 'locale' ];
	$OnAppUser->unsetFields( $unset );
	$OnAppUser->_id = $OnAppUserID;
	$OnAppUser->activate_user();

	if( ! is_null( $OnAppUser->error ) ) {
		$errorMsg = $lang->Error_UnsuspendUser . ':<br/>';
		$errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

		return $errorMsg;
	}

	// todo rename subject
	sendmessage( 'OnApp account has been unsuspended', $serviceID );

	return 'success';
}

function OnAppvCD_ClientArea( $params = '' ) {

	if( isset( $_GET[ 'modop' ] ) && ( $_GET[ 'modop' ] == 'custom' ) ) {
		if( isset( $_GET[ 'a' ] ) ) {
			$functionName = OnAppvCDModule::MODULE_NAME . '_Custom_' . $_GET[ 'a' ];
			if( function_exists( $functionName ) ) {
				$functionName( $params );
			}
			else {
				echo $functionName;
				exit( PHP_EOL . ' die at ' . __LINE__ . ' in ' . __FILE__ );
			}
		}
	}


	$data         = new stdClass;
	$module       = new OnAppvCDModule( $params );
	$data->lang   = $module->loadLang()->Client;
	$data->jsLang = json_encode( $data->lang );
	$data->params = json_decode( json_encode( $params ) );


	# server form
	$server = $params[ 'serverhttpprefix' ] . '://' . ( $params[ 'serverhostname' ] ?: $params[ 'serverip' ] );
	$tmp    = [
		'login'    => $params[ 'username' ],
		'password' => $params[ 'password' ],
		'server'   => $server,
	];
	$tmp    = json_encode( $tmp ) . '%%%';

	$iv_size                = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
	$iv                     = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
	$key                    = substr( md5( uniqid( rand( 1, 999999 ), true ) ), 0, 32 );
	$crypttext              = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $tmp, MCRYPT_MODE_ECB, $iv );
	$_SESSION[ 'utk' ]      = [
		$key . substr( md5( uniqid( rand( 1, 999999 ), true ) ), rand( 0, 26 ), 5 ),
		base64_encode( base64_encode( $crypttext ) ),
	];

	$data->token            = md5( uniqid( rand( 1, 999999 ), true ) );
	$data->serverURL        = $server;
	$data->organizationType = $params[ 'configoption7' ];
	$data->additional       = Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
									 ->where( 'serviceID', $params[ 'serviceid' ] )
									 ->first();


	return $module->buildHTML( $data, 'clientArea/main.tpl' );
}

function OnAppvCD_AdminLink( $params ) {
	$data       = new stdClass;
	$module     = new OnAppvCDModule;
	$data->lang = $module->loadLang()->Admin;
	# open CP button
	$form = '<form target="_blank" action="' . $params[ 'serverhttpprefix' ] . '://' . ( $params[ 'serverhostname' ] ?: $params[ 'serverip' ] ) . '/users/sign_in" method="post">
	  <input type="hidden" name="user[login]" value="' . $params[ 'serverusername' ] . '">
	  <input type="hidden" name="user[password]" value="' . $params[ 'serverpassword' ] . '">
	  <input type="hidden" name="commit" value="Sign In" />
	  <input type="submit" value="' . $data->lang->LoginToCP . '" class="btn btn-default">
   </form>';

	return $form;
}

function OnAppvCD_AdminServicesTabFields( $params ) {
	$data = Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
				   ->where( 'serviceID', $params[ 'serviceid' ] )
				   ->first();

	$fields[ 'OnApp user ID' ] = '<input type="text" value="' . $data->OnAppUserID . '" name="' . OnAppvCDModule::MODULE_NAME . '[OnAppUserID]">';
	$fields[ 'OnApp user ID' ] .= '<input type="hidden" name="' . OnAppvCDModule::MODULE_NAME . '_Prev" value="' . htmlentities( json_encode( $data ) ) . '">';

	return $fields;
}

function OnAppvCD_AdminServicesTabFieldsSave( $params ) {
	if( $_POST[ OnAppvCDModule::MODULE_NAME . '_Prev' ] === 'null' ) {
		$module        = new OnAppvCDModule( $params );
		$OnAppUser     = $module->getObject( 'User' );
		$OnAppUser->id = $_POST[ $module::MODULE_NAME ][ 'OnAppUserID' ];
		$OnAppUser     = $OnAppUser->load();

		if( ! is_null( $OnAppUser->error ) ) {
			$lang     = $module->loadLang()->Admin;
			$errorMsg = $lang->Error_LinkUser . ":\\n";
			$errorMsg .= $OnAppUser->getErrorsAsString( "\\n" );
			echo '<script>alert("' . $errorMsg . '");</script><meta http-equiv="refresh" content="0">';
			exit;
		}
		else {
			$id    = $OnAppUser->id;
			$login = $OnAppUser->login;
		}

		# save user link
		Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
			   ->insert( [
				   'serviceID'   => $params[ 'serviceid' ],
				   'WHMCSUserID' => $params[ 'userid' ],
				   'OnAppUserID' => $id,
				   'serverID'    => $params[ 'serverid' ],
				   'billingType' => $params[ 'configoption2' ],
			   ] );

		$password            = $module::generatePassword();
		$OnAppUser           = $module->getObject( 'User' );
		$OnAppUser->id       = $id;
		$OnAppUser->password = $password;
		$OnAppUser->save();

		# save OnApp login and password
		Capsule::table( 'tblhosting' )
			   ->where( 'id', $params[ 'serviceid' ] )
			   ->update( [
				   'password' => encrypt( $password ),
				   'username' => $login,
			   ] );
	}
	else {
		$prev = json_decode( html_entity_decode( $_POST[ OnAppvCDModule::MODULE_NAME . '_Prev' ] ) );
		# check server change
		if( $prev->serverID != $_POST[ 'server' ] ) {
			$_POST[ OnAppvCDModule::MODULE_NAME ][ 'serverID' ] = $_POST[ 'server' ];
		}

		Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
			   ->where( 'id', $prev->id )
			   ->update( $_POST[ OnAppvCDModule::MODULE_NAME ] );
	}
}

function OnAppvCD_Custom_OutstandingDetails( $params = '' ) {
	$module = new OnAppvCDModule;
	$data = $module->getAmount( $params );
	if( $data ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $data );
	}
	else {
		header( 'HTTP/1.1 404 No Data Found' );
	}
	exit;
}