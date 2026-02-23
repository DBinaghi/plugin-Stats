<?php
/**
 * Controller to get summary of Stats.
 * @package Stats
 */
class Stats_SummaryController extends Omeka_Controller_AbstractActionController
{
    private $_tableStat;
    private $_tableHit;
    private $_userStatus;

    /**
     * Controller-wide initialization. Sets the underlying model to use.
     */
    public function init()
    {
        // Short for default table.
        $this->_tableStat = $this->_helper->_db->getTable('Stat');
        $this->_tableHit = $this->_helper->_db->getTable('Hit');

        $this->_userStatus = is_admin_theme()
            ? get_option('stats_default_user_status_admin')
            : get_option('stats_default_user_status_public');
    }

    /**
     * Index action.
     */
    public function indexAction()
    {
        $tableStat = $this->_tableStat;
        $tableHit = $this->_tableHit;
        $userStatus = $this->_userStatus;

        $results = array();
        $time = time();

        $results['all'] = $this->_statsPeriod();

        $results['today'] = $this->_statsPeriod(strtotime('today'));

        $results['history'][__('Last year')] = $this->_statsPeriod(
            strtotime('-1 year', strtotime(date('Y-1-1', $time))),
            strtotime(date('Y-1-1', $time) . ' - 1 second'));
        $results['history'][__('Last month')] = $this->_statsPeriod(
            strtotime('-1 month', strtotime(date('Y-m-1', $time))),
            strtotime(date('Y-m-1', $time) . ' - 1 second'));
        $results['history'][__('Last week')] = $this->_statsPeriod(
            strtotime("previous week"),
            strtotime("previous week + 6 days"));
        $results['history'][__('Yesterday')] = $this->_statsPeriod(
            strtotime('-1 day', strtotime(date('Y-m-d', $time))),
            strtotime('-1 day', strtotime(date('Y-m-d', $time))));

        $results['current'][__('This year')] = $this->_statsPeriod(
            strtotime(date('Y-1-1', $time)));
        $results['current'][__('This month')] = $this->_statsPeriod(
            strtotime(date('Y-m-1', $time)));
        $results['current'][__('This week')] = $this->_statsPeriod(
            strtotime('this week'));
        $results['current'][__('This day')] = $this->_statsPeriod(
            strtotime('today'));

        foreach (array(365 => null, 30 => null, 7 => null, 1 => null) as $start => $endPeriod) {
            $startPeriod = strtotime("- {$start} days");
            $label = ($start == 1) ? __('Last 24 hours') : __('Last %s days', $start);
            $results['rolling'][$label] = $this->_statsPeriod($startPeriod, $endPeriod);
        }

        if (is_allowed('Stats_Browse', 'by-page')) {
            $results['most_viewed_pages'] = $tableStat->getMostViewedPages(null, $userStatus, 10);
        }
        if (is_allowed('Stats_Browse', 'by-record')) {
            $results['most_viewed_records'] = $tableStat->getMostViewedRecords(null, $userStatus, 10);
            $results['most_viewed_collections'] = $tableStat->getMostViewedRecords('Collection', $userStatus, 10);
        }
        if (is_allowed('Stats_Browse', 'by-download')) {
            $results['most_viewed_downloads'] = $tableStat->getMostViewedDownloads($userStatus, 10);
        }

        if (is_allowed('Stats_Browse', 'by-field')) {
            $results['most_frequent_fields'] = array();
            $results['most_frequent_fields']['referrer'] = $tableHit->getMostFrequents('referrer', $userStatus, 10);
            $results['most_frequent_fields']['query'] = $tableHit->getMostFrequents('query', $userStatus, 10);
            $results['most_frequent_fields']['user_agent'] = $tableHit->getMostFrequents('user_agent', $userStatus, 10);
            $results['most_frequent_fields']['accept_language'] = $tableHit->getMostFrequents('accept_language', $userStatus, 10);
        }

        $this->view->assign(array(
            'results' => $results,
            'user_status' => $userStatus,
        ));
    }

    /**
     * Graphs action.
     */
    public function graphsAction()
    {
        $tableHit = $this->_tableHit;
		$dateNow = new DateTime();
		
		// statistics for hits during the last 30 days
		for ($i = 29; $i >= 0; $i--) {
			$date = date('d', strtotime('-' . $i . ' days')) . ' ' . $this->_months(date('n', strtotime('-' . $i . ' days')), true);
			$time1 = strtotime(date('Y-m-d', strtotime('-' . $i . ' days'))); 
			$time2 = $time1;
			$results['last30Days'][$date] = $this->_statsPeriod($time1, $time2);
		}
		
		// statistics for hits during the last 12 months
		for ($i = 11; $i > 0; $i--) {
			$date = $this->_months(date('n', strtotime('-' . $i . ' months')), true) . ' ' . date(' \'y', strtotime('-' . $i . ' months'));
			$time1 = strtotime(date('Y-m-1', strtotime('-' . $i . ' months'))); 
			$time2 = strtotime(date('Y-m-1', strtotime('-' . ($i - 1) . ' months'))); 
			$results['last12Months'][$date] = $this->_statsPeriod($time1, $time2);
		}
		$date = $this->_months(date('n'), true) . ' ' . date(' \'y');
		$time1 = strtotime(date('Y-m-1')); 
		$time2 = strtotime(date('Y-m-1', strtotime('+1 months'))); 
		$results['last12Months'][$date] = $this->_statsPeriod($time1, $time2);
		
		// statistics for hits by year
		for ($year = substr($tableHit->getEndDate(), 0, 4); $year <= date('Y'); $year++) {
			$time1 = strtotime($year . '-1-1');
			$time2 = strtotime($year . '-12-31');
			$results['perYear'][$year] = $this->_statsPeriod($time1, $time2);
		}

		// statistics for hits by user agent and language
		if (is_allowed('Stats_Browse', 'by-field')) {
			$results['most_frequent_fields'] = array();
			$results['most_frequent_fields']['user_agent'] = $tableHit->getMostFrequents('user_agent', null, 10);
			$results['most_frequent_fields']['accept_language'] = $tableHit->getMostFrequents('accept_language', null, 100);
		}
		
		$this->view->assign(array(
            'results' => $results
        ));
	}
	
    /**
     * Helper to get all stats of a period.
     *
     * @param integer $startPeriod Number of days before today (default is all).
     * @param integer $endPeriod Number of days before today (default is now).
     *
     * @return array
     */
     private function _statsPeriod($startPeriod = null, $endPeriod = null)
     {
        $tableHit = $this->_tableHit;
        $userStatus = $this->_userStatus;

        $params = array();
        if ($startPeriod) {
             $params['since'] = date('Y-m-d 00:00:00', $startPeriod);
        }
        if ($endPeriod) {
             $params['until'] = date('Y-m-d 23:59:59', $endPeriod);
        }

        $result = array();
        if (is_admin_theme()) {
            $counts = $tableHit->getCountsByUserStatus($params);
            $result['anonymous'] = $counts['hits_anonymous'];
            $result['identified'] = $counts['hits_identified'];
            $result['total'] = $result['anonymous'] + $result['identified'];
        }
        else {
            $params['user_status'] = $userStatus;
            $result = $tableHit->count($params);
        }

        return $result;
    }
	
	private function _months($month = 0, $shorten = false)
	{
		$months = array(
			__('January'),
			__('February'),
			__('March'),
			__('April'),
			__('May'),
			__('June'),
			__('July'),
			__('August'),
			__('September'),
			__('October'),
			__('November'),
			__('December')
		);

		if ($shorten) {
			return substr($months[$month - 1], 0, 3);
		} else {
			return $months[$month - 1];
		}
	}
}
