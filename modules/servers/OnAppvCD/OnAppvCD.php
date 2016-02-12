<?php

require_once __DIR__ . '/includes/php/ModuleWrapper.php';
use Illuminate\Database\Capsule\Manager as Capsule;

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
					  ->get()
	;

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

			$module                                   = new OnAppvCDModule( $serverConfig );
			$data->servers->{$serverConfig->id}       = $module->getData();
			$data->servers->{$serverConfig->id}->Name = $serverConfig->name;
		}

		if( $data->servers ) {
			# get additional data
			$data->TimeZones      = file_get_contents( __DIR__ . '/includes/php/tzs.json' );
			$data->TimeZones      = json_decode( $data->TimeZones );
			$data->productOptions = $GLOBALS[ 'packageconfigoption' ] ?: [ ];
			if( ! ( empty( $data->productOptions[ 1 ] ) || empty( $data->productOptions[ 24 ] ) ) ) {
				$data->productSettings     = json_decode( $data->productOptions[ 24 ] )->{$data->productOptions[ 1 ]};
				$data->productSettingsJSON = htmlspecialchars( $GLOBALS[ 'packageconfigoption' ][ 24 ] );
			}
		}
	}

	end:
	return [
		'' => [
			'Description' => $module->buildHTML( $data )
		]
	];
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
	$userName        = $params[ 'username' ] ? $params[ 'username' ] : $clientsDetails[ 'email' ];
	$password        = OnAppvCDModule::generatePassword();
	$productSettings = json_decode( $params[ 'configoption24' ] )->$serverID;

	if( $productSettings->OrganizationType == 1 ) {
		$userGroup = $productSettings->UserGroups;
	}
	else {
		# create user group // todo add error handling and dupe title
		$label = Capsule::table( 'tblcustomfieldsvalues' )
			->where( 'relid', $serviceID )
			->pluck( 'value' )
		;
		$userGroup = $module->getObject( 'UserGroup' );
		$userGroup->label = $label;
		$userGroup->assign_to_vcloud = true;
		$userGroup->hypervisor_id = $productSettings->HyperVisor;
		$userGroup->company_billing_plan_id = $productSettings->BillingPlanDefault;
		$userGroup->billing_plan_ids = $productSettings->GroupBillingPlans;
		$userGroup->save();
		$userGroup = $userGroup->id;
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

	# trial user
	$isTrial = false;
	if( $productSettings->TrialDays > 0 ) {
		if( $params[ 'status' ] == 'Active' ) {
			$OnAppUser->_billing_plan_id = $productSettings->BillingPlanTrial;
			$isTrial                     = true;
		}
	}

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
			   'serviceID'     => $params[ 'serviceid' ],
			   'WHMCSUserID'   => $params[ 'userid' ],
			   'OnAppUserID'   => $OnAppUser->_obj->_id,
			   'serverID'      => $params[ 'serverid' ],
			   'billingPlanID' => $OnAppUser->_billing_plan_id,
			   'billingType'   => $productSettings->BillingType,
			   //'isTrial'       => $isTrial,
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
	$module = new OnAppvCDModule( $params );
	$tableName = $module::MODULE_NAME . '_Users';
	$lang   = $module->loadLang()->Admin;
	if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
		return $lang->Error_WrapperNotFound . realpath( ROOTDIR ) . '/includes';
	}

	$serviceID = $params[ 'serviceid' ];
	$clientID  = $params[ 'clientsdetails' ][ 'userid' ];
	$serverID  = $params[ 'serverid' ];

	$OnAppUserID = Capsule::table( $tableName )
		->where( 'serverID', $serverID )
		->where( 'serviceID', $serviceID )
		->pluck( 'OnAppUserID' )
	;

	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	$module    = new OnAppvCDModule( $params );
	$OnAppUser = $module->getObject( 'User' );
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

	sendmessage( 'OnApp account has been terminated', $serviceID );

	return 'success';
}

function OnAppvCD_SuspendAccount( $params ) {
	$module = new OnAppvCDModule( $params );
	$lang   = $module->loadLang()->Admin;
	if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
		return $lang->Error_WrapperNotFound . realpath( ROOTDIR ) . '/includes';
	}

	$serverID        = $params[ 'serverid' ];
	$clientID        = $params[ 'clientsdetails' ][ 'userid' ];
	$serviceID       = $params[ 'serviceid' ];
	$productSettings = json_decode( $params[ 'configoption24' ] )->$serverID;

	$query = "SELECT
					`OnAppUserID`
				FROM
					`OnAppUsersNG`
				WHERE
					serverID = $serverID
					-- AND client_id = $clientID
					AND serviceID = $serviceID";
	// todo use placeholders

	$result = full_query( $query );
	if( $result ) {
		$OnAppUserID = mysql_result( $result, 0 );
	}
	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	$OnAppUser      = $module->getObject( 'User' );
	$OnAppUser->_id = $OnAppUserID;

	# change billing plan
	$unset = array( 'time_zone', 'user_group_id', 'locale' );
	$OnAppUser->unsetFields( $unset );
	$OnAppUser->_billing_plan_id = $productSettings->BillingPlanSuspended;
	$OnAppUser->save();

	$OnAppUser->suspend();
	if( ! is_null( $OnAppUser->error ) ) {
		$errorMsg = $lang->Error_SuspendUser . ':<br/>';
		$errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

		return $errorMsg;
	}

	sendmessage( 'OnApp account has been suspended', $serviceID );

	return 'success';
}

function OnAppvCD_UnsuspendAccount( $params ) {
	$module = new OnAppvCDModule( $params );
	$lang   = $module->loadLang()->Admin;
	if( ! file_exists( ONAPP_WRAPPER_INIT ) ) {
		return $lang->Error_WrapperNotFound . realpath( ROOTDIR ) . '/includes';
	}

	$serverID        = $params[ 'serverid' ];
	$clientID        = $params[ 'clientsdetails' ][ 'userid' ];
	$serviceID       = $params[ 'serviceid' ];
	$productSettings = json_decode( $params[ 'configoption24' ] )->$serverID;

	$query = "SELECT
					`OnAppUserID`
				FROM
					`OnAppElasticUsers`
				WHERE
					serverID = $serverID
					-- AND client_id = $clientID
					AND serviceID = $serviceID";
	// todo use placeholders

	$result = full_query( $query );
	if( $result ) {
		$OnAppUserID = mysql_result( $result, 0 );
	}
	if( ! $OnAppUserID ) {
		return sprintf( $lang->Error_UserNotFound, $clientID, $serverID );
	}

	//$module = new OnAppvCDModule( $params );
	$OnAppUser = $module->getObject( 'User' );
	$unset     = array( 'time_zone', 'user_group_id', 'locale' );
	$OnAppUser->unsetFields( $unset );
	$OnAppUser->_id              = $OnAppUserID;
	$OnAppUser->_billing_plan_id = $productSettings->BillingPlanDefault;
	$OnAppUser->save();
	$OnAppUser->activate_user();

	if( ! is_null( $OnAppUser->error ) ) {
		$errorMsg = $lang->Error_UnsuspendUser . ':<br/>';
		$errorMsg .= $OnAppUser->getErrorsAsString( '<br/>' );

		return $errorMsg;
	}

	sendmessage( 'OnApp account has been unsuspended', $serviceID );

	return 'success';
}

function OnAppvCD_ClientArea( $params = '' ) {
	if( isset( $_GET[ 'modop' ] ) && ( $_GET[ 'modop' ] == 'custom' ) ) {
		if( isset( $_GET[ 'a' ] ) ) {
			echo '<pre>';
			exit( PHP_EOL . ' die at ' . __LINE__ . ' in ' . __FILE__ );

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

	// todo fix lang loading
	$data       = new stdClass;
	$module     = new OnAppvCDModule( $params );
	$data->lang = $module->loadLang()->Client;
	//$data->lang   = json_decode( OnAppvCDModule::$tmpModuleLang )->Client;
	$data->jsLang = json_encode( $data->lang );
	$data->params = json_decode( json_encode( $params ) );

	# server form
	$server = $params[ 'serverhttpprefix' ] . '://';
	$server .= ! empty( $params[ 'serverip' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];
	$tmp = [
		'login'    => $params[ 'username' ],
		'password' => $params[ 'password' ],
		'server'   => $server,
	];
	$tmp = json_encode( $tmp ) . '%%%';

	$iv_size           = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
	$iv                = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
	$key               = substr( md5( uniqid( rand( 1, 999999 ), true ) ), 0, 32 );
	$crypttext         = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $tmp, MCRYPT_MODE_ECB, $iv );
	$_SESSION[ 'utk' ] = [
		$key . substr( md5( uniqid( rand( 1, 999999 ), true ) ), rand( 0, 26 ), 5 ),
		base64_encode( base64_encode( $crypttext ) )
	];
	$data->token       = md5( uniqid( rand( 1, 999999 ), true ) );
	$data->serverURL   = $server;

	$result           = select_query(
		'OnAppElasticUsers',
		'',
		[ 'serviceID' => $params[ 'serviceid' ] ]
	);
	$data->additional = mysql_fetch_object( $result );

	return $module->buildHTML( $data, 'clientArea.tpl' );
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
	// todo make complex check
	$result = select_query(
		'OnAppElasticUsers',
		'',
		[ 'serviceID' => $params[ 'serviceid' ] ]
	);
	$data   = mysql_fetch_object( $result );

	// todo localize
	# get data
	$result       = select_query(
		'OnAppvCD_Cache',
		'data',
		[
			'itemID' => $params[ 'serverid' ],
			'type'   => 'serverData'
		]
	);
	$billingPlans = mysql_fetch_object( $result )->data;
	$billingPlans = json_decode( $billingPlans )->BillingPlans;

	$fields = [ ];
	$field  = '';
	foreach( $billingPlans as $id => $name ) {
		if( $data->billingPlanID == $id ) {
			$selected = 'selected';
		}
		else {
			$selected = '';
		}
		$field .= '<option value="' . $id . '" ' . $selected . '>' . $name . '</option>';
	}
	$fields[ 'Billing Plan' ] = '<select name="OnAppElasticUsers[billingPlanID]" class="form-control select-inline">' . $field . '</select>';
	$fields[ 'Billing Plan' ] .= '<input type="hidden" name="OnAppvCD_Prev" value="' . htmlentities( json_encode( $data ) ) . '">';
	$fields[ 'OnApp user ID' ] = '<input type="text" value="' . $data->OnAppUserID . '" name="OnAppElasticUsers[OnAppUserID]">';
	$fields[ 'Billing Type' ]  = ucfirst( $data->billingType );
	$fields[ 'Trial' ]         = $data->isTrial ? 'Yes' : 'No';

	return $fields;
}

function OnAppvCD_AdminServicesTabFieldsSave( $params ) {
	$prev = json_decode( html_entity_decode( $_POST[ 'OnAppUsersNG_Prev' ] ) );

	# check server change
	if( $prev->serverID != $_POST[ 'server' ] ) {
		$_POST[ 'OnAppElasticUsers' ][ 'serverID' ] = $_POST[ 'server' ];
	}

	# check billing plan change
	if( $prev->billingPlanID != $_POST[ 'billingPlanID' ] ) {
		$module    = new OnAppvCDModule( $params );
		$OnAppUser = $module->getObject( 'User' );
		$unset     = [ 'time_zone', 'user_group_id', 'locale' ];
		$OnAppUser->unsetFields( $unset );
		$OnAppUser->_id              = $_POST[ 'OnAppElasticUsers' ][ 'OnAppUserID' ];
		$OnAppUser->_billing_plan_id = $_POST[ 'OnAppElasticUsers' ][ 'billingPlanID' ];
		$OnAppUser->save();

		if( ! is_null( $OnAppUser->error ) ) {
			$lang = $module->loadLang()->Admin;
			unset( $_POST[ 'OnAppElasticUsers' ][ 'billingPlanID' ] );
			$errorMsg = $lang->Error_ChangeBillingPlan . ":\\n";
			$errorMsg .= $OnAppUser->getErrorsAsString( "\\n" );
			echo '<script>alert("' . $errorMsg . '");</script><meta http-equiv="refresh" content="0">';
			exit;
		}
	}

	update_query(
		'OnAppElasticUsers',
		$_POST[ 'OnAppElasticUsers' ],
		[ 'id' => $prev->id ]
	);
}

function OnAppvCD_Custom_GeneratePassword( $params ) {
	$serviceID = $params[ 'serviceid' ];
	$clientID  = $params[ 'clientsdetails' ][ 'userid' ];
	$serverID  = $params[ 'serverid' ];
	$password  = OnAppvCDModule::generatePassword();

	$query = "SELECT
					`OnAppUserID`
				FROM
					`OnAppElasticUsers`
				WHERE
					serverID = '$serverID'
					AND WHMCSUserID = '$clientID'
					AND serviceID = '$serviceID'";
	// todo use placeholders

	$result      = full_query( $query );
	$OnAppUserID = mysql_result( $result, 0 );

	$module               = new OnAppvCDModule( $params );
	$OnAppUser            = $module->getObject( 'User' );
	$OnAppUser->_id       = $OnAppUserID;
	$OnAppUser->_password = $password;
	$OnAppUser->save();

	$lang = $module->loadLang()->Client;
	$data = new stdClass;

	if( ! is_null( $OnAppUser->error ) ) {
		$data->status  = false;
		$data->message = $lang->PasswordNotSet . ':<br/>';
		$data->message .= $OnAppUser->getErrorsAsString( '<br/>' );
	}
	else {
		// Save OnApp login and password
		full_query(
			"UPDATE
				tblhosting
			SET
				password = '" . encrypt( $password ) . "'
			WHERE
				id = '$serviceID'"
		);

		sendmessage( 'OnApp account password has been generated', $serviceID );

		$data->status  = true;
		$data->message = $lang->PasswordSet;
	}

	echo json_encode( $data );
	exit;
}

function OnAppvCD_Custom_ConvertTrial( $params ) {
	//echo '<pre style="text-align: left;">';
	//print_r( $params );
	//exit( PHP_EOL . ' die at ' . __LINE__ . ' in ' . __FILE__ );

	$result      = select_query(
		'OnAppElasticUsers',
		'',
		[
			'serviceID'   => $params[ 'serviceid' ],
			'WHMCSUserID' => $params[ 'userid' ],
			'serverID'    => $params[ 'serverid' ],
		]
	);
	$OnAppUserID = mysql_fetch_object( $result )->OnAppUserID;

	$module    = new OnAppvCDModule( $params );
	$OnAppUser = $module->getObject( 'User' );
	$unset     = [ 'time_zone', 'user_group_id', 'locale' ];
	$OnAppUser->unsetFields( $unset );
	$OnAppUser->_id              = $OnAppUserID;
	$OnAppUser->_billing_plan_id = json_decode( $params[ 'configoption24' ] )->{2};
	$OnAppUser->save();

	$data = new stdClass;
	$lang = $module->loadLang()->Client;
	if( ! is_null( $OnAppUser->error ) ) {
		$data->status  = false;
		$data->message = $lang->Error_ConvertTrial . ':<br>';
		$data->message .= $OnAppUser->getErrorsAsString( '<br>' );
	}
	else {
		$update        = [
			'isTrial'       => false,
			'billingPlanID' => $OnAppUser->_billing_plan_id,
		];
		$data->status  = true;
		$data->message = $lang->TrialConverted;

		update_query(
			'OnAppElasticUsers',
			$update,
			[
				'serviceID'   => $params[ 'serviceid' ],
				'WHMCSUserID' => $params[ 'userid' ],
				'serverID'    => $params[ 'serverid' ],
				'OnAppUserID' => $OnAppUserID,
			]
		);
	}

	exit( json_encode( $data ) );
}

function OnAppvCD_Custom_OutstandingDetails( $params = '' ) {
	$data = json_encode( OnAppvCDModule::getAmount( $params ) );
	exit( $data );
}