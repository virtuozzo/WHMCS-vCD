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
    const MODULE_VERSION = '0.3';
    const MODULE_NAME = 'OnAppvCD';
    const ATTEMPTS_TO_DELETE = 15;
    const MINIMUM_WRAPPER_VERSION = 5.4;

	private $server;
	private $moneyFormat;

    private $loadedLangs = [];

	private $vdcsesByUserGroupId = [];

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

    private function createUserGroup($label, $hypervisorID, $billingPlanDefault, $groupBillingPlans)
    {
        $userGroupObj = $this->getObject('UserGroup');
        $userGroupObj->label = $label;
        $userGroupObj->assign_to_vcloud = true;
        $userGroupObj->assign_vcloud_roles = true;
        $userGroupObj->hypervisor_id = $hypervisorID;
        $row_company_billing_plan_id = $this->getRow('company_billing_plan_id');
        $userGroupObj->{$row_company_billing_plan_id} = $billingPlanDefault;
        $row_billing_plan_ids = $this->getRow('billing_plan_ids');
        $userGroupObj->{$row_billing_plan_ids} = $groupBillingPlans;
        $userGroupObj->save();

        $userGroupID = $userGroupObj->id;

        $n = 0;
        $attempts = 10;
        $errorMsg = '';
        while (!$this->checkObject('UserGroup', $userGroupID)) {
            $n++;
            if ($n > $attempts) {
                $errorMsg = $this->loadLang()->Admin->Error_CreateUserGroup . ': ';
                $errorMsg .= $userGroupObj->getErrorsAsString(', ');

                return array(
                    'userGroupID' => 0,
                    'errorMsg' => $errorMsg
                );
            }
            sleep(1);
        }
        sleep(5);

        return array(
            'userGroupID' => $userGroupID,
            'errorMsg' => $errorMsg
        );
    }

    private function updateUserGroup($id, $label, $billingPlanDefault, $groupBillingPlans)
    {
        $userGroupObj = $this->getObject('UserGroup');
        $userGroupObj->_id = $id;

        $userGroupObj->_label = $label;
        $userGroupObj->_bucket_id = $billingPlanDefault;
        $userGroupObj->_user_buckets = $groupBillingPlans;
        $userGroupObj->save();
    }

    private function createOrganization($label, $hypervisorID, $billingPlanDefault, $groupBillingPlans)
    {
        $organizationObj = $this->getObject('Organizations');
        $organizationObj->_label = $label;
        $organizationObj->_user_group_id = 0;
        $organizationObj->_hypervisor_id = $hypervisorID;
        $organizationObj->_create_user_group = true;
        $organizationObj->_user_bucket_id = $billingPlanDefault;

        $organizationObj->save();

        if (!$organizationObj->_id) {
            $errorMsg = $this->loadLang()->Admin->Error_CreateUserGroup . ': ';
            $errorMsg .= $organizationObj->getErrorsAsString(', ');

            return array(
                'userGroupID' => 0,
                'errorMsg' => $errorMsg
            );
        }
        $organizationID = $organizationObj->_id;
        $userGroupID = $organizationObj->_user_group_id;

        $n = 0;
        $attempts = 10;
        $errorMsg = '';
        while (!$this->checkObject('Organizations', $organizationID)) {
            $n++;
            if ($n > $attempts) {
                $errorMsg = $this->loadLang()->Admin->Error_CreateUserGroup . ': ';
                $errorMsg .= $organizationObj->getErrorsAsString(', ');

                return array(
                    'userGroupID' => 0,
                    'errorMsg' => $errorMsg
                );
            }
            sleep(1);
        }
        sleep(5);

        if (!$userGroupID) {
            $userGroupID = $this->checkIfOrganizationHasUserGroup($organizationID);
        }

        if (!$userGroupID) {
            $errorMsg = $this->loadLang()->Admin->Error_CreateUserGroup . ': ';
            $errorMsg .= $organizationObj->getErrorsAsString(', ');

            return array(
                'userGroupID' => 0,
                'errorMsg' => $errorMsg
            );
        }

        $this->updateUserGroup($userGroupID, $label, $billingPlanDefault, $groupBillingPlans);

        return array(
            'userGroupID' => $userGroupID,
            'errorMsg' => $errorMsg
        );
    }

    private function checkIfOrganizationHasUserGroup($organizationID)
    {
        $n = 0;
        $attempts = 10;
        $userGroupID = 0;
        while (!$userGroupID) {
            $organizationObj = $this->getObject('Organizations');
            $organizationObj->load($organizationID);
            $userGroupID = $organizationObj->_obj->_user_group_id;

            $n++;
            if ($n > $attempts) {
                break;
            }
            sleep(1);
        }
        sleep(5);

        return $userGroupID;
    }

    public function createGroup($label, $hypervisorID, $billingPlanDefault, $groupBillingPlans)
    {
        if ($this->getAPIVersionNumber() > 5.99) {
            return $this->createOrganization($label, $hypervisorID, $billingPlanDefault, $groupBillingPlans);
        }

        return $this->createUserGroup($label, $hypervisorID, $billingPlanDefault, $groupBillingPlans);
    }

	public function getBillingPlans() {
            $apiVersionArr = $this->getAPIVersion();
            $apiVersion = $apiVersionArr['version'];
            if ( $apiVersion > 5.5 ) {
                $data = $this->getObject( 'BillingBucket' )->getList();
            } elseif ( $apiVersion > 5.1 ){
                $data = $this->getObject( 'BillingUser' )->getList();
            } else {
                $data = $this->getObject( 'BillingPlan' )->getList();
            }

            return $this->buildArray( $data );
	}

        public function getRow( $row ){
            $result = $row;
            $rows = [
                'company_billing_plan_id'   => 'bucket_id',
                'billing_plan_ids'          => 'user_buckets',
                '_billing_plan_id'          => '_bucket_id',
            ];
            $apiVersionArr = $this->getAPIVersion();
            $apiVersion = $apiVersionArr['version'];
            if ( $apiVersion > 5.5 && array_key_exists($row, $rows) ) {
                $result = $rows[$row];
            }

            return $result;
        }

	public function BillingCompanyPlans() {
		$data = $this->getObject( 'BillingCompany' )->getList();

		return $this->buildArray( $data );
	}

	public function getLocales() {
		$tmp = array();
		foreach( $this->getObject( 'Locale' )->getList() as $locale ) {
			if( empty( $locale->name ) ) {
				continue;
			}
			$index = $locale->code ? $locale->code : $locale->name;
			$tmp[ $index ] = $locale->name;
		}
		if (!isset($tmp['en'])) {
			$tmp['en'] = 'en';
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
							->first();

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
		$obj       = new $className;
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

        if (isset($this->loadedLangs[$languageFile])) {
            return $this->loadedLangs[$languageFile];
        }

        $this->loadedLangs[$languageFile] = json_decode(json_encode(require $languageFile));

        return $this->loadedLangs[$languageFile];
	}

	public function getAmount( array $params ) {
		if( $_GET[ 'tz_offset' ] != 0 ) {
			$dateFrom = date( 'Y-m-d H:i', strtotime( $_GET[ 'start' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
			$dateTill = date( 'Y-m-d H:i', strtotime( $_GET[ 'end' ] ) + ( $_GET[ 'tz_offset' ] * 60 ) );
		}
		else {
			$dateFrom = $_GET[ 'start' ];
			$dateTill = $_GET[ 'end' ];
		}
		$date = [
			'period[startdate]' => $dateFrom,
			'period[enddate]'   => $dateTill,
		];
		$rate = Capsule::table( 'tblcurrencies' )
					   ->where( 'id', $params[ 'clientsdetails' ][ 'currency' ] )
					   ->select( 'rate', 'prefix', 'suffix' )
					   ->first();

		$result                    = new stdClass;
		$this->moneyFormat         = $this->loadLang()->Money;
		$this->moneyFormat->Symbol = $rate->{$this->moneyFormat->Symbol};

		if( $params[ 'configoption7' ] == 1 ) {
			# single org
			$data = self::getResourcesData( $params, $date );
			if( ! $data ) {
				return false;
			}

			$data         = $data->user_stat;
			$result->cost = $this->formatAmount( $this->getTotalCost($data) * $rate->rate );

			$module = new self( $params );
			$module = $module->getObject( 'VirtualMachine' );
			foreach( $data->vm_stats as $vm ) {
				$tmp           = [
					'label' => $module->load( $vm->virtual_machine_id )->label,
					'cost'  => $this->formatAmount( $this->getTotalCost($vm) * $rate->rate ),
				];
				$result->vms[] = $tmp;
			}
		}
		else {
			# multiple orgs

			$tmp    = [
				'configoption1'    => $params[ 'configoption1' ],
				'serverusername'   => $params[ 'username' ],
				'serverpassword'   => $params[ 'password' ],
				'serverhttpprefix' => $params[ 'serverhttpprefix' ],
				'serverip'         => $params[ 'serverip' ],
				'serverhostname'   => $params[ 'serverhostname' ],
			];
			$module = new self( $tmp );
			$vdcs   = $module->getObject( 'VDCS' )->getList();

			$result->cost = 0;
			foreach( $vdcs as $vdc ) {
				$tmp   = 0;
				$stats = $module->getObject( 'VDCS_Statistics' )->getList( $vdc->id, $date );
				foreach( $stats as $stat ) {
					$tmp += $stat->cost;
				}
				$result->cost += $tmp;
				$result->pools[] = [
					'label' => $vdc->label,
					'cost'  => $this->formatAmount( $tmp * $rate->rate ),
				];
			}
			$result->cost = $this->formatAmount( $result->cost * $rate->rate );
		}

		return $result;
	}

	private static function getResourcesData( $params, $date ) {
		$user = Capsule::table( OnAppvCDModule::MODULE_NAME . '_Users' )
					   ->where( 'serviceID', $params[ 'serviceid' ] )
					   ->select( 'serverID', 'WHMCSUserID', 'OnAppUserID' )
					   ->first();

		$serverAddr = $params[ 'serverhttpprefix' ] . '://';
		$serverAddr .= $params[ 'serverip' ] ?: $params[ 'serverhostname' ];

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
		$curl->addOption( CURLOPT_HTTPHEADER, [ 'Accept: application/json', 'Content-type: application/json' ] );
		$curl->addOption( CURLOPT_HEADER, true );
		$data = $curl->get( $url );

		if( $curl->getRequestInfo( 'http_code' ) != 200 ) {
			return false;
		}
		else {
			return $data;
		}
	}

	private function formatAmount( $amount ) {
		$f    = $this->moneyFormat;
		$data = [
			'amount' => number_format( $amount, $f->Precision, $f->DecimalPoint, $f->ThousandsSeparator ),
			'symbol' => $f->Symbol,
		];

		return preg_replace_callback(
			'/{(\w+)}/',
			function( $matches ) use ( $data ) {
				return $data[ $matches[ 1 ] ];
			},
			$f->Format
		);
	}

	public static function generatePassword( $length = 20 ) {
		$password = substr( str_shuffle( '~!@$%^&*(){}|0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ), 0, $length - 1 );
		$password .= mt_rand( 0, 9 );

		return $password;
	}

	private function getWrapperVersion(){
		$pathToWrapper = realpath( ROOTDIR ) . '/includes/wrapper/';
		$version = file_get_contents( $pathToWrapper.'version.txt' );

		return $version;
	}

	private function getAPIVersion() {
		$obj = new OnApp_Factory( $this->server->address, $this->server->user, $this->server->pass);
		$apiVersion = (string) $obj->getAPIVersion();
        return array(
            'version' => trim($apiVersion),
            'message' => trim($obj->getErrorsAsString( ', ' )),
        );
	}

    private function getAPIVersionNumber()
    {
        $apiVersionArr = $this->getAPIVersion();

        return (float) $apiVersionArr['version'];
    }

	public function checkWrapperVersion(){
        $wrapperVersion = trim($this->getWrapperVersion());

        $apiVersionArr = $this->getAPIVersion();
        $apiVersion = $apiVersionArr['version'];

        $result = array();
        $result['apiMessage'] = $apiVersionArr['message'];
        $result['wrapperVersion'] = $wrapperVersion;
        $result['apiVersion'] = $apiVersion;
        if(($wrapperVersion == '')||($apiVersion == '')){
            $result['status'] = false;
            return $result;
        }

        $wrapperVersionAr = preg_split( '/[.,]/', $wrapperVersion, NULL, PREG_SPLIT_NO_EMPTY );
        if((count($wrapperVersionAr) == 1)&&($wrapperVersionAr[0] == '')){
            $result['status'] = false;
            return $result;
        }

        $apiVersionAr     = preg_split( '/[.,]/', $apiVersion, NULL, PREG_SPLIT_NO_EMPTY );
        if((count($apiVersionAr) == 1)&&($apiVersionAr[0] == '')){
            $result['status'] = false;
            return $result;
        }

        $result['status'] = true;
        foreach ( $apiVersionAr as $apiVersionKey => $apiVersionValue ) {
            if ( ! isset( $wrapperVersionAr[ $apiVersionKey ] ) ) {
                $result['status'] = false;
                break;
            }

            $apiVersionValue     = (int) $apiVersionValue;
            $wrapperVersionValue = (int) $wrapperVersionAr[ $apiVersionKey ];

            if ( $apiVersionValue == $wrapperVersionValue ) {
                continue;
            }

            $result['status'] = ( $wrapperVersionAr[ $apiVersionKey ] > $apiVersionValue );
            break;
        }

        return $result;
	}

    public function checkObject( $class, $id, $additionalObjParams = [] ) {
        $className = 'OnApp_' . $class;
        $obj       = new $className;
        $obj->auth( $this->server->address, $this->server->user, $this->server->pass );

        $obj->id = $id;
        foreach ( $additionalObjParams as $paramName => $paramVal ) {
            $obj->{$paramName} = $paramVal;
        }
        $obj->load();

        return is_null( $obj->getErrorsAsArray() );
    }

    public function deleteAllByClassNameAndVDCSId( $className, $vdcsId ) {
        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteAllByClassNameAndVDCSId className: ' . $className. "; vdcsId: " . $vdcsId);
        $errorMsg = "";
        $obj  = $this->getObject( $className );
        $list = $obj->getList();

        $idsToDelete = array();
        foreach ( $list as $item ) {
            if ( $item->_vdc_id == $vdcsId ) {
                $idsToDelete[] = $item->_id;
            }
        }

        foreach ( $idsToDelete as $id ) {
            $errorMsg .= $this->deleteObject($className, $id);
        }
        return $errorMsg;
    }

    public function deleteAllByClassNameAndCatalogItemId( $className, $catalogItemId ) {
        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteAllByClassNameAndCatalogItemId className: ' . $className . '; catalogItemId: ' . $catalogItemId);
        $errorMsg = "";
        $obj  = $this->getObject( $className );
        $obj->_catalog_item_id = $catalogItemId;
        $list = $obj->getList();

        $idsToDelete = array();
        foreach ( $list as $item ) {
            $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'in deleteAllByClassNameAndCatalogItemId idsToDelete: ' . $item->_id);
            $idsToDelete[] = $item->_id;
        }

        foreach ( $idsToDelete as $id ) {
            $errorMsg .= $this->deleteObject($className, $id, ['_catalog_item_id' => $catalogItemId]);
        }
        return $errorMsg;
    }

    public function deleteCatalogsByClassNameAndUserGroupId( $userGroupId ) {
        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteCatalogsByClassNameAndVDCSId userGroupId: ' . $userGroupId);
        $className = "Catalogs";

        $errorMsg = "";
        $obj  = $this->getObject( $className );
        $list = $obj->getList();

        $idsToDelete = array();
        foreach ( $list as $item ) {
            if ( $item->_user_group_id == $userGroupId ) {
                $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteCatalogsByClassNameAndVDCSId idsToDelete: ' . $item->_id);
                $idsToDelete[] = $item->_id;
            }
        }

        foreach ( $idsToDelete as $id ) {
            $errorMsg .= $this->deleteAllByClassNameAndCatalogItemId('Catalogs_Media', $id);
            $errorMsg .= $this->deleteAllByClassNameAndCatalogItemId('Catalogs_VAppTemplates', $id);
            $errorMsg .= $this->deleteObject($className, $id);
        }
        return $errorMsg;
    }

    public function deleteGroup($userGroupIdToDelete)
    {
        $errorMsg = '';
        if ($this->getAPIVersionNumber() > 5.99) {
            $errorMsg = $this->findAndDeleteOrganizationByUserGroup($userGroupIdToDelete);
        }

        if ($errorMsg) {
            return $errorMsg;
        }

        return $this->deleteObject('UserGroup', $userGroupIdToDelete);
    }

    public function findAndDeleteOrganizationByUserGroup($userGroupIdToDelete)
    {
        $this->debugLog('OnAppvCDmod', __FUNCTION__, 'find and delete an organization by user group');
        $this->debugLog('OnAppvCDmod', __FUNCTION__, 'userGroupId: ' . $userGroupIdToDelete);
        $obj = $this->getObject('Organizations');
        $orgList = $obj->getList();

        $orgIDToDelete = 0;
        $areThereSeveralOrganizations = false;
        foreach ($orgList as $org) {
            if ($org->_user_group_id == $userGroupIdToDelete) {
                if ($orgIDToDelete) {
                    $areThereSeveralOrganizations = true;

                    break;
                }
                $orgIDToDelete = $org->_id;
            }
        }
        if ($areThereSeveralOrganizations) {
            $errorMsg = 'there are several organizations for a user group';

            return $errorMsg;
        }

        if (!$orgIDToDelete) {
            $errorMsg = 'can not find an organization for a user group';

            return $errorMsg;
        }

        return $this->deleteObject('Organizations', $orgIDToDelete);
    }

    public function deleteObject( $className, $id, $additionalObjParams = [], $force = false ) {
        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteObject className: ' . $className . '; id: ' . $id);

        $errorMsg = "";

	    $obj      = $this->getObject( $className );
        $obj->_id = $id;
        foreach ( $additionalObjParams as $paramName => $paramVal ) {
            $obj->{$paramName} = $paramVal;
        }
        if ( $force ) {
            $obj->delete( true );
        } else {
            $obj->delete();
        }

        if ( ! is_null( $obj->getErrorsAsArray() ) ) {
            $errorMsg = $obj->getErrorsAsString( ', ' ) . '; ';
            logModuleCall( self::MODULE_NAME, 'delete failed', "", $errorMsg );

            return $errorMsg;
        }

        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteObject className: ' . $className . '; id: ' . $id . '; status: check');

        $n = 0;
        while ( $this->checkObject( $className, $id, $additionalObjParams ) ) {
            $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteObject className: ' . $className . '; id: ' . $id . '; status: wait(' . ( $n ) . ')' );
            $n ++;
            if ( $n > self::ATTEMPTS_TO_DELETE ) {
                $errorMsg = 'can not delete ' . $className . '; id=' . $id;
                break;
            }
            sleep( 5 );
        }
        if ( $errorMsg != '' ) {
            logModuleCall( self::MODULE_NAME, 'delete failed', "", $errorMsg );
            return $errorMsg;
        }
        sleep( 5 );
        $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'deleteObject className: ' . $className . '; id: ' . $id . '; status: deleted' );

        return '';
    }

    public function getVDCSesByUserGroupId( $userGroupId ) {
        if ( isset( $this->vdcsesByUserGroupId[ $userGroupId ] ) ) {
            return $this->vdcsesByUserGroupId[ $userGroupId ];
        }

        $OnAppVDCS   = $this->getObject( 'VDCS' );
        $OnAppVDCSes = $OnAppVDCS->getList();

        $this->vdcsesByUserGroupId[ $userGroupId ] = [];
        foreach ( $OnAppVDCSes as $vdcs ) {
            if ( $vdcs->user_group_id == $userGroupId ) {
                $this->debugLog( 'OnAppvCDmod', __FUNCTION__, 'vdcsesToDelete: ' . $vdcs->_id);
                $this->vdcsesByUserGroupId[ $userGroupId ][] = $vdcs->_id;
            }
        }
        return $this->vdcsesByUserGroupId[ $userGroupId ];
    }

    public function clearUserGroupResources( $userGroupId ) {
        $errorMsg = "";

        $vdcsesToClear = $this->getVDCSesByUserGroupId($userGroupId);

        foreach ( $vdcsesToClear as $vdcsId ) {
            $errorMsg .= $this->deleteAllByClassNameAndVDCSId('OrgNetwork', $vdcsId);
            $errorMsg .= $this->deleteAllByClassNameAndVDCSId('VDCS_EdgeGateway', $vdcsId);
        }

        $errorMsg .= $this->deleteCatalogsByClassNameAndUserGroupId( $userGroupId );

        return $errorMsg;

    }

    public function deleteVDCSByUserGroupId( $userGroupId ) {
        $errorMsg = "";

        $vdcsesToDelete = $this->getVDCSesByUserGroupId($userGroupId);

        foreach ( $vdcsesToDelete as $vdcsId ) {
            $errorMsg .= $this->deleteObject('VDCS', $vdcsId);
        }

        return $errorMsg;

    }

    public function debugLog( $title, $func, $msg ) {
	    return;
        logModuleCall( $title, $func, '', '', $msg );
    }

    protected function getTotalCost($obj)
    {
        if (!property_exists($obj, 'total_cost')) {
            return 0;
        }

        return property_exists($obj, 'total_cost_with_discount') ?
            $obj->total_cost_with_discount : $obj->total_cost;
    }
}
