<?php

use Illuminate\Database\Capsule\Manager as Capsule;

global $whmcs;
$configModuleVersion = $whmcs->get_config( OnAppvCDModule::MODULE_NAME . 'Version' );
if( empty( $configModuleVersion ) || ( OnAppvCDModule::MODULE_VERSION > $configModuleVersion ) ) {
	require __DIR__ . '/setup/setup.php';
}

if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
	define( 'ONAPP_WRAPPER_INIT', ROOTDIR . '/includes/wrapper/OnAppInit.php' );
}

if( file_exists( ONAPP_WRAPPER_INIT ) ) {
	require_once ONAPP_WRAPPER_INIT;
}

class OnAppvCDModule {
	const MODULE_VERSION = '0.1';
	const MODULE_NAME    = 'OnAppvCD';

	private $server;

	public function __construct( $params = null ) {
		if( $params != null ) {
			if( is_array( $params ) ) {
				$this->server          = new stdClass;
				$this->server->ID      = $params[ 'configoption1' ];
				$this->server->user    = $params[ 'serverusername' ];
				$this->server->pass    = $params[ 'serverpassword' ];
				$this->server->address = $params[ 'serverhttpprefix' ] . '://';
				$this->server->address .= $params[ 'serverip' ] ?: $params[ 'serverhostname' ];
			}
			else {
				$this->server       = new stdClass;
				$this->server->ID   = $params->id;
				$this->server->user = $params->username;
				$this->server->pass = $params->password;
				if( $params->secure == 'on' ) {
					$this->server->address = 'https://';
				}
				else {
					$this->server->address = 'http://';
				}
				$this->server->address .= $params->ipaddress ?: $params->hostname;
			}
		}
	}

	public function getUserGroups() {
		$data = $this->getObject( 'UserGroup' )->getList();

		return $this->buildArray( $data );
	}

	public function getRoles() {
		$data = $this->getObject( 'Role' )->getList();

		return $this->buildArray( $data );
	}

	public function getBillingPlans() {
		$data = $this->getObject( 'BillingPlan' )->getList();

		return $this->buildArray( $data );
	}

	public function BillingCompanyPlans() {
		$data = $this->getObject( 'BillingCompany' )->getList();
		return $this->buildArray( $data );
	}

	public function getLocales() {
		$tmp = [ ];
		foreach( $this->getObject( 'Locale' )->getList() as $locale ) {
			if( empty( $locale->name ) ) {
				continue;
			}
			$tmp[ $locale->code ] = $locale->name;
		}

		return $tmp;
	}

	public function getHyperVisors() {
		$data = $this->getObject( 'Hypervisor' )->getList();
		return $this->buildArray( $data );
	}

	public function getData() {
		$tableName = self::MODULE_NAME . '_Cache';
		$data      = Capsule::table( $tableName )
							->where( 'type', 'serverData' )
							->where( 'itemID', $this->server->ID )
							->select( 'data' )
							->first()
		;

		if( empty( $data ) ) {
			# get data from OnApp CP
			$data                      = new stdClass;
			$data->BillingPlans        = $this->getBillingPlans();
			$data->BillingCompanyPlans = $this->BillingCompanyPlans();
			$data->HyperVisors         = $this->getHyperVisors();
			$data->Roles               = $this->getRoles();
			$data->UserGroups          = $this->getUserGroups();
			$data->Locales             = $this->getLocales();

			# store data to DB
			$values = [
				'type'   => 'serverData',
				'itemID' => $this->server->ID,
				'data'   => json_encode( $data ),
			];
			Capsule::table( $tableName )->insert( $values );
		}
		else {
			# get data from local DB
			$data = json_decode( $data->data );
		}

		return $data;
	}

	public function getObject( $class ) {
		$className = 'OnApp_' . $class;
		$obj   = new $className;
		$obj->auth( $this->server->address, $this->server->user, $this->server->pass );

		return $obj;
	}

	public function buildHTML( stdClass &$data, $tpl = 'productSettings.tpl' ) {
		require_once ROOTDIR . '/vendor/smarty/smarty/libs/Smarty.class.php';
		$templatesDir         = realpath( __DIR__ . '/../tpl' );
		$templatesCacheDir    = $GLOBALS[ 'templates_compiledir' ];
		$smarty               = new Smarty();
		$compile_dir          = file_exists( $templatesCacheDir ) ? $templatesCacheDir : ROOTDIR . '/' . $templatesCacheDir;
		$smarty->compile_dir  = $compile_dir;
		$smarty->template_dir = $templatesDir;
		$data->moduleName     = self::MODULE_NAME;
		$smarty->assign( (array)$data );

		return $smarty->fetch( $tpl );
	}

	private function buildArray( $data ) {
		$tmp = [ ];
		foreach( $data as $item ) {
			$tmp[ $item->_id ] = $item->_label;
		}

		return $tmp;
	}

	public function loadLang( $languageFile = null ) {
		global $CONFIG;
		$languageFileDir = realpath( __DIR__ . '/../../lang' ) . DIRECTORY_SEPARATOR;

		if( is_null( $languageFile ) ) {
			$languageFile = isset( $_SESSION[ 'Language' ] ) ? $_SESSION[ 'Language' ] : $CONFIG[ 'Language' ];
		}
		$languageFile = $languageFileDir . strtolower( $languageFile ) . '.php';

		if( ! file_exists( $languageFile ) ) {
			$languageFile = $languageFileDir . 'english.php';
		}

		return json_decode( json_encode( require $languageFile ) );
	}

	public static function getAmount( array $params ) {
		if( $_GET[ 'tz_offset' ] != 0 ) {
			$dateFrom = date( 'Y-m-d H:i', strtotime( $_GET[ 'start' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
			$dateTill = date( 'Y-m-d H:i', strtotime( $_GET[ 'end' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
		}
		else {
			$dateFrom = $_GET[ 'start' ];
			$dateTill = $_GET[ 'end' ];
		}
		$date = array(
			'period[startdate]' => $dateFrom,
			'period[enddate]'   => $dateTill,
		);

		$data = self::getResourcesData( $params, $date );

		if( ! $data ) {
			return false;
		}

		$sql  = 'SELECT
					`code`,
					`rate`
				FROM
					`tblcurrencies`
				WHERE
					`id` = ' . $params[ 'clientsdetails' ][ 'currency' ];
		$rate = mysql_fetch_assoc( full_query( $sql ) );

		$data  = $data->user_stat;
		$unset = array(
			'vm_stats',
			'stat_time',
			'user_resources_cost',
			'user_id',
		);
		foreach( $data as $key => &$value ) {
			if( in_array( $key, $unset ) ) {
				unset( $data->$key );
			}
			else {
				$data->$key *= $rate[ 'rate' ];
			}
		}
		$data->currency_code = $rate[ 'code' ];

		return $data;
	}

	private static function getResourcesData( $params, $date ) {
		$user = Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
					   ->where( 'serviceID', $params[ 'serviceid' ] )
					   ->select( 'serverID', 'WHMCSUserID', 'OnAppUserID' )
					   ->first()
		;

		$serverAddr = $params[ 'serverhttpprefix' ] . '://';
		$serverAddr .= ! empty( $params[ 'serverip' ] ) ? $params[ 'serverip' ] : $params[ 'serverhostname' ];

		$date = http_build_query( $date );
		$url  = $serverAddr . '/users/' . $user->OnAppUserID . '/user_statistics.json?' . $date;
		$data = self::sendRequest( $url, $params[ 'serverusername' ], $params[ 'serverpassword' ] );

		if( $data ) {
			return json_decode( $data );
		}
		else {
			return false;
		}
	}

	private static function sendRequest( $url, $user, $password ) {
		require_once __DIR__ . '/CURL.php';

		$curl = new CURL();
		$curl->addOption( CURLOPT_USERPWD, $user . ':' . $password );
		$curl->addOption( CURLOPT_HTTPHEADER, array( 'Accept: application/json', 'Content-type: application/json' ) );
		$curl->addOption( CURLOPT_HEADER, true );
		$data = $curl->get( $url );

		if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
			return false;
		}
		else {
			return $data;
		}
	}

	public static function generatePassword() {
		return substr( str_shuffle( '~!@$%^&*(){}|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, 20 );
	}
}