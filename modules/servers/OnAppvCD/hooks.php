<?php

# config options
# 1 - serverID
# 2 - BillingType
# 3 - SuspendDays
# 4 - TrialDays
# 5 - DueDays
# 6 - TerminateDays
# 7 - OrganizationType

if( ! defined( 'WHMCS' ) ) {
	exit( 'This file cannot be accessed directly' );
}

require_once __DIR__ . '/includes/php/ModuleWrapper.php';
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * @param $vars
 */
function InvoicePaidHook_OnAppvCD( $vars ) {
	$moduleName = OnAppvCDModule::MODULE_NAME;
	$invoiceID  = $vars[ 'invoiceid' ];
	$qry        = 'SELECT
				OnAppUsersNG.`WHMCSUserID`,
				OnAppUsersNG.`serverID`,
				OnAppUsersNG.`OnAppUserID`,
				tblhosting.`id` AS service_id,
				tblinvoices.`subtotal` AS subtotal,
				tblinvoices.`total` AS total,
				tblproducts.`configoption1` AS settings,
				tblhosting.`domainstatus` AS status
			FROM
				tblinvoices
			LEFT JOIN OnAppUsersNG ON
				tblinvoices.`userid` = OnAppUsersNG.`WHMCSUserID`
			LEFT JOIN tblhosting ON
				tblhosting.`userid` = OnAppUsersNG.`WHMCSUserID`
				AND tblhosting.`server` = OnAppUsersNG.`serverID`
			RIGHT JOIN tblinvoiceitems ON
				tblinvoiceitems.`invoiceid` = tblinvoices.`id`
				AND tblinvoiceitems.`relid` = tblhosting.`id`
			LEFT JOIN tblproducts ON
				tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`id` = @invoiceID
				AND tblinvoices.`status` = "Paid"
				AND tblproducts.`servertype` = "@moduleName"
				AND tblinvoiceitems.`type` = "@moduleName"
			GROUP BY
				tblinvoices.`id`';
	$qry        = str_replace( '@invoiceID', $invoiceID, $qry );
	$qry        = str_replace( '@moduleName', $moduleName, $qry );
	$result     = full_query( $qry );

	if( mysql_num_rows( $result ) == 0 ) {
		return;
	}

	$data = mysql_fetch_assoc( $result );
	if( $data[ 'status' ] == 'Suspended' ) {
		# check for other unpaid invoices for this service
		$qry    = 'SELECT
					tblinvoices.`id`
				FROM
					tblinvoices
				RIGHT JOIN tblinvoiceitems ON
					tblinvoiceitems.`invoiceid` = tblinvoices.`id`
					AND tblinvoiceitems.`relid` = @serviceID
				WHERE
					tblinvoices.`status` = "Unpaid"
				GROUP BY
					tblinvoices.`id`';
		$qry    = str_replace( '@serviceID', $data[ 'service_id' ], $qry );
		$result = full_query( $qry );

		if( mysql_num_rows( $result ) == 0 ) {
			if( ! function_exists( 'serverunsuspendaccount' ) ) {
				$path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
				require_once $path . 'modulefunctions.php';
			}
			serverunsuspendaccount( $data[ 'service_id' ] );
		}
	}

	$qry                  = 'SELECT
				`secure`,
				`username`,
				`hostname`,
				`password`,
				`ipaddress`
			FROM
				tblservers
			WHERE
				`type` = "@moduleName"
				AND `id` = @serverID';
	$qry                  = str_replace( '@serverID', $data[ 'server_id' ], $qry );
	$qry                  = str_replace( '@moduleName', $moduleName, $qry );
	$result               = full_query( $qry );
	$server               = mysql_fetch_assoc( $result );
	$server[ 'password' ] = decrypt( $server[ 'password' ] );
	if( $server[ 'secure' ] ) {
		$server[ 'address' ] = 'https://';
	}
	else {
		$server[ 'address' ] = 'http://';
	}
	if( empty( $server[ 'ipaddress' ] ) ) {
		$server[ 'address' ] .= $server[ 'hostname' ];
	}
	else {
		$server[ 'address' ] .= $server[ 'ipaddress' ];
	}
	unset( $server[ 'ipaddress' ], $server[ 'hostname' ], $server[ 'secure' ] );

	# get OnApp amount
	$result = select_query(
		$moduleName . '_Cache',
		'data',
		[
			'itemID' => 123,
			'type'   => 'invoiceData',
		]
	);
	$amount = mysql_fetch_object( $result )->data;

	if( $amount ) {
		$payment = new OnApp_Payment;
		$payment->auth( $server[ 'address' ], $server[ 'username' ], $server[ 'password' ] );
		$payment->_user_id        = $data[ 'OnAppUserID' ];
		$payment->_amount         = $amount;
		$payment->_invoice_number = $invoiceID;
		$payment->save();

		$error = $payment->getErrorsAsString();
		if( empty( $error ) ) {
			$msg = $moduleName . ' payment was sent. Service ID #' . $data[ 'service_id' ] . ', amount: ' . $amount;

			# delete invoice data
			$where = [
				'itemID' => $invoiceID,
				'type'   => 'invoiceData',
			];
			delete_query( $moduleName . '_Cache', $where );
		}
		else {
			$msg = 'ERROR with ' . $moduleName . ' payment for service ID #' . $data[ 'service_id' ] . ': ' . $error;
		}
	}
	else {
		$msg = 'ERROR with ' . $moduleName . ' payment for service ID #' . $data[ 'service_id' ] . ': Cannot find ' . $moduleName . '  amount';
	}

	logactivity( $msg );
}

function TerminateTrialHook_OnAppvCD() {
	global $cron;
	$moduleName = OnAppvCDModule::MODULE_NAME;

	$qry    = 'SELECT
					`WHMCSUserID`,
					`serviceID`
				FROM
					`OnAppcUsersNG`
				LEFT JOIN `tblhosting` ON
					tblhosting.`id` = `serviceID`
				LEFT JOIN `tblproducts` ON
					tblproducts.`id` = tblhosting.`packageid`
				WHERE
					`isTrial` = TRUE
					AND NOW() > DATE_ADD( tblhosting.`regdate`, INTERVAL tblproducts.`configoption4` DAY )';
	$result = full_query( $qry );

	if( ! function_exists( 'serverterminateaccount' ) ) {
		$path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
		require_once $path . 'modulefunctions.php';
	}

	$cnt = 0;
	echo 'Starting Processing ' . $moduleName . ' Trial Terminations', PHP_EOL;
	while( $data = mysql_fetch_assoc( $result ) ) {
		ServerTerminateAccount( $data[ 'id' ] );
		echo ' - terminate service ID ', $data[ 'serviceID' ], ', user ID ', $data[ 'WHMCSUserID' ], PHP_EOL;
		++ $cnt;
	}
	echo ' - Processed ', $cnt, ' Terminations', PHP_EOL;
	$cron->emailLog( $cnt . ' ' . $moduleName . ' Trial Services Terminated' );
}

function AutoSuspendHook_OnAppvCD() {
	if( $GLOBALS[ 'CONFIG' ][ 'AutoSuspension' ] != 'on' ) {
		return;
	}

	global $cron;
	$moduleName = OnAppvCDModule::MODULE_NAME;

	$qry    = 'SELECT
				tblhosting.`id`,
				tblhosting.`userid`
			FROM
				tblinvoices
			LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.`invoiceid` = tblinvoices.`id`
			LEFT JOIN tblhosting ON
				tblhosting.`id` = tblinvoiceitems.`relid`
			LEFT JOIN tblproducts ON
				tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`status` = "Unpaid"
				AND tblinvoiceitems.`type` = "@moduleName"
				AND tblhosting.`domainstatus` = "Active"
				AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL tblproducts.`configoption3` DAY )
				AND ( tblhosting.`overideautosuspend` != 1
                        OR ( tblhosting.`overidesuspenduntil` != "0000-00-00"
                            AND tblhosting.`overidesuspenduntil` <= NOW() ) )
			GROUP BY
				tblhosting.`id`';
	$qry    = str_replace( '@moduleName', $moduleName, $qry );
	$result = full_query( $qry );

	if( ! function_exists( 'serversuspendaccount' ) ) {
		$path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
		require_once $path . 'modulefunctions.php';
	}

	$cnt = 0;
	echo 'Starting Processing ' . $moduleName . ' Suspensions', PHP_EOL;
	while( $data = mysql_fetch_assoc( $result ) ) {
		ServerSuspendAccount( $data[ 'id' ] );
		echo ' - suspend service ID ', $data[ 'id' ], ', user ID ', $data[ 'userid' ], PHP_EOL;
		++ $cnt;
	}
	echo ' - Processed ', $cnt, ' Suspensions', PHP_EOL;
	$cron->emailLog( $cnt . ' ' . $moduleName . ' Services Suspended' );
}

function AutoTerminateHook_OnAppvCD() {
	if( $GLOBALS[ 'CONFIG' ][ 'AutoTermination' ] != 'on' ) {
		return;
	}

	/**
	 * @var WHMCS_CRON $cron
	 */
	global $cron;
	$moduleName = OnAppvCDModule::MODULE_NAME;

	/*
	Capsule::table( 'tblinvoices' )
	   ->leftJoin( 'tblinvoiceitems', 'tblinvoiceitems.invoiceid', '=', 'tblinvoices.id' )
	   ->leftJoin( 'tblhosting', 'tblhosting.id', '=', 'tblinvoiceitems.relid' )
	   ->leftJoin( 'tblproducts', 'tblproducts.id', '=', 'tblhosting.packageid' )
	   ->where( 'tblinvoices.status', 'Unpaid' )
	   ->where( 'tblinvoiceitems.type', OnAppUsersNGModule::MODULE_NAME )
	   ->where( 'tblhosting.domainstatus', 'Suspended' )
	   ->whereRaw( 'NOW() > DATE_ADD( `tblinvoices`.`duedate`, INTERVAL `tblproducts`.`configoption6` DAY )' )
	   ->select( 'tblhosting.id', 'tblhosting.userid' )
		->groupBy( 'tblhosting.id' )
		->get()
	;
	*/
	$qry    = 'SELECT
				tblhosting.`id`,
				tblhosting.`userid`
			FROM
				tblinvoices
			LEFT JOIN tblinvoiceitems ON
				tblinvoiceitems.`invoiceid` = tblinvoices.`id`
			LEFT JOIN tblhosting ON
				tblhosting.`id` = tblinvoiceitems.`relid`
			LEFT JOIN tblproducts ON
				tblproducts.`id` = tblhosting.`packageid`
			WHERE
				tblinvoices.`status` = "Unpaid"
				AND tblinvoiceitems.`type` = "@moduleName"
				AND tblhosting.`domainstatus` = "Suspended"
				AND NOW() > DATE_ADD( tblinvoices.`duedate`, INTERVAL tblproducts.`configoption6` DAY )
			GROUP BY
				tblhosting.`id`';// todo check
	$qry    = str_replace( '@moduleName', $moduleName, $qry );
	$result = full_query( $qry );

	if( ! function_exists( 'serverterminateaccount' ) ) {
		$path = dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/';
		require_once $path . 'modulefunctions.php';
	}

	$cnt = 0;
	echo 'Starting Processing ' . $moduleName . ' Terminations', PHP_EOL;
	while( $data = mysql_fetch_assoc( $result ) ) {
		ServerTerminateAccount( $data[ 'id' ] );
		echo ' - terminate service ID ', $data[ 'id' ], ', user ID ', $data[ 'userid' ], PHP_EOL;
		++ $cnt;
	}
	echo ' - Processed ', $cnt, ' Terminations', PHP_EOL;
	$cron->emailLog( $cnt . ' ' . $moduleName . ' Services Terminated' );
}

/**
 * @param $vars
 *
 * @return bool
 */
function ProductEditHook_OnAppvCD( $vars ) {
	if( $_POST[ 'servertype' ] === ( $moduleName = OnAppvCDModule::MODULE_NAME ) ) {
		$serverID = $_POST[ $moduleName . '_Server' ] ?: $_POST[ 'packageconfigoption' ][ 1 ];
		if( empty( $_POST[ $moduleName . '_Skip' ] ) && isset( $_POST[ $moduleName . '_Server' ] ) ) {
			if( ! empty( $_POST[ $moduleName . '_Prev' ] ) ) {
				$settings = json_decode( html_entity_decode( $_POST[ $moduleName . '_Prev' ] ) );
			}
			else {
				$settings = new stdClass;
			}
			$settings->$serverID = $_POST[ $moduleName ];

			# store product settings
			Capsule::table( 'tblproducts' )
				   ->where( 'id', $vars[ 'pid' ] )
				   ->update( [
					   'configoption2'  => $_POST[ $moduleName ][ 'BillingType' ],
					   'configoption3'  => $_POST[ $moduleName ][ 'SuspendDays' ],
					   'configoption5'  => $_POST[ $moduleName ][ 'DueDays' ],
					   'configoption6'  => $_POST[ $moduleName ][ 'TerminateDays' ],
					   'configoption7'  => $_POST[ $moduleName ][ 'OrganizationType' ],
					   'configoption24' => json_encode( $settings ),
				   ] );
		}

		# reset server cache
		if( ! empty( $_POST[ $moduleName . '_ResetServerCache' ] ) ) {
			try {
				Capsule::table( $moduleName . '_Cache' )
					   ->where( 'type', 'serverData' )
					   ->where( 'itemID', $serverID )
					   ->delete();
			}
			catch( \Exception $e ) {
				logactivity( 'SQL ERROR: ' . $e->getMessage() );
			}
		}

		# create custom field
		if( $_POST[ $moduleName ][ 'OrganizationType' ] == 2 ) {
			$exist = Capsule::table( 'tblcustomfields' )
							->where( 'type', 'product' )
							->where( 'fieldname', 'Organization Name' )
							->where( 'relid', $vars[ 'pid' ] )
							->first();

			if( ! $exist ) {
				Capsule::table( 'tblcustomfields' )
					   ->insert( [
						   'type'      => 'product',
						   'fieldtype' => 'text',
						   'required'  => 'on',
						   'showorder' => 'on',
						   'fieldname' => 'Organization Name',
						   'relid'     => $vars[ 'pid' ],
					   ] );
			}
		}
	}

	return true;
}

function ServiceDeleteHook_OnAppvCD( $vars ) {
	Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
		   ->where( 'WHMCSUserID', $vars[ 'userid' ] )
		   ->where( 'serviceID', $vars[ 'serviceid' ] )
		   ->delete();
}

add_hook( 'InvoicePaid', 1, 'InvoicePaidHook_OnAppvCD' );
add_hook( 'ProductEdit', 1, 'ProductEditHook_OnAppvCD' );
add_hook( 'ServiceDelete', 1, 'ServiceDeleteHook_OnAppvCD' );
add_hook( 'DailyCronJob', 1, 'AutoSuspendHook_OnAppvCD' );
add_hook( 'DailyCronJob', 2, 'AutoTerminateHook_OnAppvCD' );
add_hook( 'DailyCronJob', 3, 'TerminateTrialHook_OnAppvCD' );