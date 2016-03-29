<?php

use Illuminate\Database\Capsule\Manager as Capsule;

# process SQL
logactivity( OnAppvCDModule::MODULE_NAME . ' Module: process SQL file.' );

$sql = file_get_contents( __DIR__ . '/module.sql' );
$sql = explode( ';', $sql );

$tmpSQLConfig                = $CONFIG['SQLErrorReporting'];
$CONFIG['SQLErrorReporting'] = '';
foreach ( $sql as $qry ) {
    $qry = trim($qry);
    if(!$qry){
        continue;
    }
    $qry = str_replace( '{moduleName}', OnAppvCDModule::MODULE_NAME, $qry );
    try {
        Capsule::connection()->statement( $qry );
    } catch ( \Exception $e ) {
        logactivity( 'SQL ERROR: ' . $e->getMessage() );
    }
}
$CONFIG['SQLErrorReporting'] = $tmpSQLConfig;
unset( $tmpSQLConfig );

# process mail templates
require __DIR__ . '/module.mail.php';

# store module version
$whmcs->set_config( OnAppvCDModule::MODULE_NAME . 'Version', OnAppvCDModule::MODULE_VERSION );