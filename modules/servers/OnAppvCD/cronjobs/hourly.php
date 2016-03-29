<?php

require __DIR__ . '/common.php';

class OnAppvCD_Cron_Hourly extends OnAppvCD_Cron {
	const TYPE = 'hourly';

	protected function run() {
		require_once $this->root . 'includes/wrapper/OnAppInit.php';
		$this->getStat();
	}

	private function getStat() {
		$endDate = gmdate( 'Y-m-d H:i:00' );
		while( $client = mysql_fetch_assoc( $this->clients ) ) {
			if( $client[ 'billingType' ] != 'prepaid' ) {
				continue;
			}

			if( isset( $this->cliOptions->since ) ) {
				$startDate = $this->cliOptions->since;
			}
			else {
				# get last stat retrieving date
				$qry = 'SELECT
							`Date`
						FROM
							`OnAppvCD_Hourly_LastCheck`
						WHERE
							`WHMCSUserID` = :WHMCSUserID
							AND `serverID` = :serverID
							AND `OnAppUserID` = :OnAppUserID';
				$qry = str_replace( ':WHMCSUserID', $client[ 'WHMCSUserID' ], $qry );
				$qry = str_replace( ':serverID', $client[ 'serverID' ], $qry );
				$qry = str_replace( ':OnAppUserID', $client[ 'OnAppUserID' ], $qry );
				$startDate = mysql_query( $qry );
				if( ( $startDate === false ) || ( mysql_num_rows( $startDate ) == 0 ) ) {
					$startDate = gmdate( 'Y-m-01 00:00:00' );
				}
				else {
					$startDate = mysql_result( $startDate, 0 );
					$startDate = date( 'Y-m-d H:00:00', strtotime( $startDate ) - ( 1 * 3600 ) );
				}
			}

			$date = array(
				'period[startdate]' => $startDate,
				'period[enddate]'   => $endDate,
			);

			$sql = $this->getResourcesStat( $client, $date );

			# process SQL
			foreach( $sql as $record ) {
				$record .= ' ON DUPLICATE KEY UPDATE id = id';
				full_query( $record );
			}
		}

		echo 'Itemized cron job finished successfully', PHP_EOL;
		echo 'Get data from ', $startDate, ' to ', $endDate;
		echo ' (UTC)', PHP_EOL;
	}

	private function getResourcesStat( $client, $date ) {
		$sql = array();
		$start = strtotime( $date[ 'period[startdate]' ] );
		$finish = strtotime( $date[ 'period[enddate]' ] );

		while( $start < $finish ) {
			$date = array(
				'period[startdate]' => date( 'Y-m-d H:10:s', $start ),
				'period[enddate]'   => date( 'Y-m-d H:10:s', $start += 3600 ),
			);

			$data = $this->getResourcesData( $client, $date )->user_stat;

			if( $data->total_cost == 0 ) {
				continue;
			}

			$qry = 'INSERT INTO
							`OnAppvCD_Hourly_Stat` (
							 	`serverID`,
							 	`WHMCSUserID`,
							 	`OnAppUserID`,
							 	`cost`,
							 	`startDate`,
							 	`endDate`
							)
							VALUES (
								:serverID,
								:WHMCSUserID,
								:OnAppUserID,
								:cost,
								":startDate",
								":endDate"
							)';
			$qry = str_replace( ':WHMCSUserID', $client[ 'WHMCSUserID' ], $qry );
			$qry = str_replace( ':OnAppUserID', $client[ 'OnAppUserID' ], $qry );
			$qry = str_replace( ':serverID', $client[ 'serverID' ], $qry );
			$qry = str_replace( ':startDate', $date[ 'period[startdate]' ], $qry );
			$qry = str_replace( ':endDate', $date[ 'period[enddate]' ], $qry );
			$qry = str_replace( ':cost', $data->total_cost, $qry );
			$sql[ ]  = $qry;

			$this->chargeClient( $client, $data->total_cost, $date );
		}
		return $sql;
	}

	public function chargeClient( $client, $amount, $date ) {
		$qry   = 'SELECT
					`username`
				FROM
					`tbladmins`
				LIMIT 1';
		$res   = mysql_query( $qry );
		$admin = mysql_result( $res, 0 );

		$command                 = 'addcredit';
		$values[ 'clientid' ]    = $client[ 'WHMCSUserID' ];
		$values[ 'description' ] = 'Hourly bill user #' . $client[ 'WHMCSUserID' ] . ' | Period: ' . $date[ 'period[startdate]' ] . ' - ' . $date[ 'period[enddate]' ] . ' | Billed ' . date( 'd/m/Y H:i' );
		$values[ 'amount' ]      = - ( $amount * $client[ 'rate' ] );

		$results = localAPI( $command, $values, $admin );

		if( $results[ 'newbalance' ] <= 0 ) {
			global $CONFIG;

			if( $CONFIG[ 'AutoTermination' ] == 'on' ) {
				// todo check query
				$qry = 'UPDATE
							tblhosting
						SET
							nextduedate = DATE_ADD( NOW(), INTERVAL :days DAY )
						WHERE
							id = :serviceID';
				$qry = str_replace( ':days', $CONFIG[ 'AutoTerminationDays' ], $qry );
				$qry = str_replace( ':serviceID', $client[ 'service_id' ], $qry );
				full_query( $qry );
			}

			$this->suspendUser( $client );
		}
	}

	private function suspendUser( $client ) {
		// todo optimize suspension
		// todo add change billing
		if( ! function_exists( 'serversuspendaccount' ) ) {
			require_once $this->root . 'includes/modulefunctions.php';
		}
		$result = serversuspendaccount( $client[ 'service_id' ] );
	}

	protected function getResourcesData( $client, $date ) {
		// todo check dupe
		$dateAsArray = $date;
		$date = http_build_query( $date );

		$url = $this->servers[ $client[ 'serverID' ] ][ 'address' ] . '/users/' . $client[ 'OnAppUserID' ] . '/user_statistics.json?' . $date;
		$data = $this->sendRequest( $url, $this->servers[ $client[ 'serverID' ] ][ 'username' ], $this->servers[ $client[ 'serverID' ] ][ 'password' ] );

		if( $data ) {
			$this->saveLastCheckDate( $client, $dateAsArray );
			return json_decode( $data );
		}
		else {
			return array();
		}
	}

	private function saveLastCheckDate( $client, $date ) {
		$qry = 'INSERT INTO
					`OnAppvCD_Hourly_LastCheck`
				VALUES
					(
						:serverID,
						:WHMCSUserID,
						:OnAppUserID,
						""
					)
				ON DUPLICATE KEY UPDATE
					`Date` = ":Date"';
		$qry = str_replace( ':serverID', $client[ 'serverID' ], $qry );
		$qry = str_replace( ':WHMCSUserID', $client[ 'WHMCSUserID' ], $qry );
		$qry = str_replace( ':OnAppUserID', $client[ 'OnAppUserID' ], $qry );
		$qry = str_replace( ':Date', $date[ 'period[enddate]' ], $qry );
		full_query( $qry );
	}
}
new OnAppvCD_Cron_Hourly;