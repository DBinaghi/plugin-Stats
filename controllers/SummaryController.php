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

        // Disable view rendering for AJAX chart actions.
        $chartActions = array(
            'chart-days', 'chart-months', 'chart-years',
            'chart-browsers', 'chart-languages',
        );
        if (in_array($this->getRequest()->getActionName(), $chartActions)) {
            $this->_helper->viewRenderer->setNoRender(true);
        }
    }

    /**
     * Index action.
     */
    public function indexAction()
    {
        $tableStat  = $this->_tableStat;
        $tableHit   = $this->_tableHit;
        $userStatus = $this->_userStatus;

        $results = array();
        $time    = time();

        // Build all period counts in a single query using conditional aggregation.
        $db       = get_db();
        $hitTable = $db->Hit;

        $periods = array(
            'all'           => array(null,                                          null),
            'today'         => array(date('Y-m-d 00:00:00'),                        null),
            'last_year'     => array(date('Y-m-d 00:00:00', strtotime('-1 year', strtotime(date('Y-1-1', $time)))),
                                     date('Y-m-d 23:59:59', strtotime(date('Y-1-1', $time) . ' - 1 second'))),
            'last_month'    => array(date('Y-m-d 00:00:00', strtotime('-1 month', strtotime(date('Y-m-1', $time)))),
                                     date('Y-m-d 23:59:59', strtotime(date('Y-m-1', $time) . ' - 1 second'))),
            'last_week'     => array(date('Y-m-d 00:00:00', strtotime('previous week')),
                                     date('Y-m-d 23:59:59', strtotime('previous week + 6 days'))),
            'yesterday'     => array(date('Y-m-d 00:00:00', strtotime('yesterday')),
                                     date('Y-m-d 23:59:59', strtotime('yesterday'))),
            'this_year'     => array(date('Y-01-01 00:00:00', $time),               null),
            'this_month'    => array(date('Y-m-01 00:00:00', $time),                null),
            'this_week'     => array(date('Y-m-d 00:00:00', strtotime('this week')), null),
            'this_day'      => array(date('Y-m-d 00:00:00', $time),                 null),
            'rolling_365'   => array(date('Y-m-d 00:00:00', strtotime('-365 days')), null),
            'rolling_30'    => array(date('Y-m-d 00:00:00', strtotime('-30 days')),  null),
            'rolling_7'     => array(date('Y-m-d 00:00:00', strtotime('-7 days')),   null),
            'rolling_1'     => array(date('Y-m-d 00:00:00', strtotime('-1 days')),   null),
        );

        // Build SELECT with one CASE WHEN per period.
        // Each condition is inlined as a literal date string to avoid
        // parameter-count mismatches with repeated placeholders.
        $selects = array();
        foreach ($periods as $key => $range) {
            list($from, $to) = $range;
            $cond = '1=1';
            if ($from) {
                $escaped = $db->quote($from);
                $cond    = "added >= {$escaped}";
            }
            if ($to) {
                $escaped = $db->quote($to);
                $cond   .= " AND added <= {$escaped}";
            }
            $selects[] = "SUM(CASE WHEN ({$cond}) AND user_id IS NULL     THEN 1 ELSE 0 END) AS `{$key}_anon`";
            $selects[] = "SUM(CASE WHEN ({$cond}) AND user_id IS NOT NULL THEN 1 ELSE 0 END) AS `{$key}_ident`";
        }
        $sql = "SELECT " . implode(', ', $selects) . " FROM `{$hitTable}`";
        $row = $db->fetchRow($sql);

        // Helper to extract anonymous/identified/total from the result row.
        $p = function($key) use ($row) {
            $anon  = (int)$row[$key . '_anon'];
            $ident = (int)$row[$key . '_ident'];
            return array('anonymous' => $anon, 'identified' => $ident, 'total' => $anon + $ident);
        };

        $results['all']   = $p('all');
        $results['today'] = $p('today');

        $results['history'][__('Last year')]  = $p('last_year');
        $results['history'][__('Last month')] = $p('last_month');
        $results['history'][__('Last week')]  = $p('last_week');
        $results['history'][__('Yesterday')]  = $p('yesterday');

        $results['current'][__('This year')]  = $p('this_year');
        $results['current'][__('This month')] = $p('this_month');
        $results['current'][__('This week')]  = $p('this_week');
        $results['current'][__('This day')]   = $p('this_day');

        $results['rolling'][__('Last 365 days')]  = $p('rolling_365');
        $results['rolling'][__('Last 30 days')]   = $p('rolling_30');
        $results['rolling'][__('Last 7 days')]    = $p('rolling_7');
        $results['rolling'][__('Last 24 hours')]  = $p('rolling_1');

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
            $results['most_frequent_fields']['user_agent'] = $this->_enrichUserAgents($results['most_frequent_fields']['user_agent']);
            $results['most_frequent_fields']['accept_language'] = $this->_aggregateLanguages(
                $tableHit->getMostFrequents('accept_language', $userStatus, 100), 10
            );
        }

        $this->view->assign(array(
            'results' => $results,
            'user_status' => $userStatus,
        ));
    }

    /**
     * Charts action.
     */
    public function chartsAction()
    {
        // Data is loaded asynchronously via AJAX — see chart*Action methods below.
    }

    // =========================================================================
    // Charts AJAX endpoints
    // =========================================================================

    private function _jsonChart($data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        echo json_encode($data);
    }

    public function chartDaysAction()
    {
        $db       = get_db();
        $hitTable = $db->Hit;
        $rows = $db->fetchAll("
            SELECT DATE(added) AS day,
                   SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS anonymous,
                   SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS identified
            FROM `{$hitTable}`
            WHERE added >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            GROUP BY DATE(added)
        ");
        $byDay = array();
        foreach ($rows as $row) {
            $byDay[$row['day']] = array((int)$row['anonymous'], (int)$row['identified']);
        }
        $labels = array();
        $anonymous = array();
        $identified = array();
        for ($i = 29; $i >= 0; $i--) {
            $dayKey = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[]     = date('d', strtotime($dayKey)) . ' ' . $this->_months((int)date('n', strtotime($dayKey)), true);
            $anonymous[]  = isset($byDay[$dayKey]) ? $byDay[$dayKey][0] : 0;
            $identified[] = isset($byDay[$dayKey]) ? $byDay[$dayKey][1] : 0;
        }
        $this->_jsonChart(array('labels' => $labels, 'anonymous' => $anonymous, 'identified' => $identified));
    }

    public function chartMonthsAction()
    {
        $db       = get_db();
        $hitTable = $db->Hit;
        $rows = $db->fetchAll("
            SELECT YEAR(added) AS yr, MONTH(added) AS mo,
                   SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS anonymous,
                   SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS identified
            FROM `{$hitTable}`
            WHERE added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
            GROUP BY YEAR(added), MONTH(added)
        ");
        $byMonth = array();
        foreach ($rows as $row) {
            $byMonth[$row['yr'] . '-' . str_pad($row['mo'], 2, '0', STR_PAD_LEFT)] = array((int)$row['anonymous'], (int)$row['identified']);
        }
        $labels = array();
        $anonymous = array();
        $identified = array();
        for ($i = 11; $i >= 0; $i--) {
            $ts       = strtotime('-' . $i . ' months');
            $monthKey = date('Y-m', $ts);
            $labels[]     = $this->_months((int)date("n", $ts), true) . date(" 'y", $ts);
            $anonymous[]  = isset($byMonth[$monthKey]) ? $byMonth[$monthKey][0] : 0;
            $identified[] = isset($byMonth[$monthKey]) ? $byMonth[$monthKey][1] : 0;
        }
        $this->_jsonChart(array('labels' => $labels, 'anonymous' => $anonymous, 'identified' => $identified));
    }

    public function chartYearsAction()
    {
        $db       = get_db();
        $hitTable = $db->Hit;
        $rows = $db->fetchAll("
            SELECT YEAR(added) AS yr,
                   SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS anonymous,
                   SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) AS identified
            FROM `{$hitTable}`
            GROUP BY YEAR(added)
            ORDER BY yr ASC
        ");
        $labels = array();
        $anonymous = array();
        $identified = array();
        foreach ($rows as $row) {
            $labels[]     = $row['yr'];
            $anonymous[]  = (int)$row['anonymous'];
            $identified[] = (int)$row['identified'];
        }
        $this->_jsonChart(array('labels' => $labels, 'anonymous' => $anonymous, 'identified' => $identified));
    }

    public function chartBrowsersAction()
    {
        if (!is_allowed('Stats_Browse', 'by-field')) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }
        $browsers = $this->_getBrowserStats();
        $notIdentifiedLabel = __('not identified');
        $labels = array();
        $data   = array();
        foreach ($browsers as $row) {
            $labels[] = $row['label'] === 'not identified' ? $notIdentifiedLabel : $row['label'];
            $data[]   = (int)$row['hits'];
        }
        $this->_jsonChart(array(
            'labels'             => $labels,
            'data'               => $data,
            'notIdentifiedLabel' => $notIdentifiedLabel,
        ));
    }

    public function chartLanguagesAction()
    {
        if (!is_allowed('Stats_Browse', 'by-field')) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }
        $tableHit = $this->_tableHit;
        $langs = $this->_aggregateLanguages(
            $tableHit->getMostFrequents('accept_language', null, 500), 24
        );
        $labels = array();
        $data   = array();
        foreach ($langs as $row) {
            $labels[] = $row['accept_language'];
            $data[]   = (int)$row['hits'];
        }
        $this->_jsonChart(array('labels' => $labels, 'data' => $data));
    }

    // =========================================================================
    // UA Parser AJAX endpoints
    // =========================================================================


    /**
     * POST /stats/summary/parse  (chiamato dal tracker pubblico)
     *
     * Riceve lo UA parsato dal browser visitatore e lo memorizza in cache.
     * Lo UA nel payload deve corrispondere all'header HTTP reale per
     * impedire l'inquinamento della cache.
     */
    public function parseAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        if (!$this->getRequest()->isPost()) {
            $this->_jsonUa(array('error' => 'Method not allowed'), 405);
            return;
        }

        $body = $this->getRequest()->getRawBody();
        $data = json_decode($body, true);

        if (empty($data['user_agent']) || empty($data['parsed']) || !is_array($data['parsed'])) {
            $this->_jsonUa(array('error' => 'Invalid payload'), 400);
            return;
        }

        $userAgent = (string) $data['user_agent'];
        $serverUA  = (string) $this->getRequest()->getServer('HTTP_USER_AGENT');

        if ($userAgent !== $serverUA) {
            $this->_jsonUa(array('error' => 'User agent mismatch'), 403);
            return;
        }

        try {
            $db     = get_db();
            $table  = $db->prefix . 'stats_user_agents';
            $result = $this->_upsertUa($db, $table, $userAgent, $data['parsed']);
            $this->_jsonUa(array('status' => $result ? 'ok' : 'error'));
        } catch (Exception $e) {
            $this->_jsonUa(array('error' => 'Server error: ' . $e->getMessage()), 500);
        }
    }

    /**
     * GET /admin/stats/summary/parse-batch
     *
     * Returns JSON list of distinct user-agent strings in the hit log
     * that have not yet been parsed. Called by the admin batch tool.
     */
    public function parseBatchAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        if (!is_allowed('Stats_Summary', null)) {
            $this->_jsonUa(array('error' => 'Forbidden'), 403);
            return;
        }

        try {
            $db       = get_db();
            $uaTable  = $db->prefix . 'stats_user_agents';
            $hitTable = $db->Hit;

            $sql = "
                SELECT DISTINCT h.`user_agent`
                FROM `{$hitTable}` AS h
                WHERE h.`user_agent` != ''
                  AND h.`ua_parsed` = 0
            ";

            $unparsed = $db->fetchCol($sql);

            $this->_jsonUa(array(
                'status'      => 'ok',
                'count'       => count($unparsed),
                'user_agents' => array_values($unparsed),
            ));
        } catch (Exception $e) {
            $this->_jsonUa(array('error' => 'Server error: ' . $e->getMessage()), 500);
        }
    }

    /**
     * POST /admin/stats/summary/store-batch
     *
     * Receives a JSON array of parsed UA objects and stores them.
     * Body: { "items": [ { "user_agent": "...", "parsed": {...} }, ... ] }
     */
    public function storeBatchAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        if (!is_allowed('Stats_Summary', null)) {
            $this->_jsonUa(array('error' => 'Forbidden'), 403);
            return;
        }

        if (!$this->getRequest()->isPost()) {
            $this->_jsonUa(array('error' => 'Method not allowed'), 405);
            return;
        }

        $body = $this->getRequest()->getRawBody();
        $data = json_decode($body, true);

        if (empty($data['items']) || !is_array($data['items'])) {
            $this->_jsonUa(array('error' => 'Invalid payload'), 400);
            return;
        }

        try {
            $db      = get_db();
            $table   = $db->prefix . 'stats_user_agents';
            $stored  = 0;
            $errors  = 0;

            foreach ($data['items'] as $item) {
                if (empty($item['user_agent']) || empty($item['parsed'])) {
                    $errors++;
                    continue;
                }
                if ($this->_upsertUa($db, $table, $item['user_agent'], $item['parsed'])) {
                    $stored++;
                } else {
                    $errors++;
                }
            }

            $this->_jsonUa(array(
                'status' => 'ok',
                'stored' => $stored,
                'errors' => $errors,
            ));
        } catch (Exception $e) {
            $this->_jsonUa(array('error' => 'Server error: ' . $e->getMessage()), 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Helper to get all stats of a period.
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
        } else {
            $params['user_status'] = $userStatus;
            $result = $tableHit->count($params);
        }

        return $result;
    }

    private function _months($month = 0, $shorten = false)
    {
        $months = array(
            __('January'), __('February'), __('March'),    __('April'),
            __('May'),     __('June'),     __('July'),     __('August'),
            __('September'), __('October'), __('November'), __('December'),
        );
        return $shorten ? substr($months[$month - 1], 0, 3) : $months[$month - 1];
    }

    /**
     * Aggrega le righe grezze di accept_language per prima lingua preferita.
     *
     * Ogni riga ha la forma "it-IT,it;q=0.9,en-US;q=0.8,...".
     * Estrae il primo token, ricava il codice ISO 639-1 a due lettere,
     * lo traduce nel nome inglese (con il codice tra parentesi).
     * I codici non in lista vengono mostrati così come sono.
     *
     * @param  array $rows   Output di getMostFrequents('accept_language', ...)
     * @param  int   $limit  Numero massimo di lingue da restituire
     * @return array         [['accept_language' => 'Italian (it)', 'hits' => 123], ...]
     */
    private function _aggregateLanguages(array $rows, $limit = 10)
    {
        $iso = array(
            'ab' => 'Abkhazian',       'aa' => 'Afar',            'af' => 'Afrikaans',
            'ak' => 'Akan',            'sq' => 'Albanian',        'am' => 'Amharic',
            'ar' => 'Arabic',          'an' => 'Aragonese',       'hy' => 'Armenian',
            'as' => 'Assamese',        'av' => 'Avaric',          'ae' => 'Avestan',
            'ay' => 'Aymara',          'az' => 'Azerbaijani',     'bm' => 'Bambara',
            'ba' => 'Bashkir',         'eu' => 'Basque',          'be' => 'Belarusian',
            'bn' => 'Bengali',         'bi' => 'Bislama',         'bs' => 'Bosnian',
            'br' => 'Breton',          'bg' => 'Bulgarian',       'my' => 'Burmese',
            'ca' => 'Catalan',         'ch' => 'Chamorro',        'ce' => 'Chechen',
            'ny' => 'Chichewa',        'zh' => 'Chinese',         'cu' => 'Church Slavic',
            'cv' => 'Chuvash',         'kw' => 'Cornish',         'co' => 'Corsican',
            'cr' => 'Cree',            'hr' => 'Croatian',        'cs' => 'Czech',
            'da' => 'Danish',          'dv' => 'Divehi',          'nl' => 'Dutch',
            'dz' => 'Dzongkha',        'en' => 'English',         'eo' => 'Esperanto',
            'et' => 'Estonian',        'ee' => 'Ewe',             'fo' => 'Faroese',
            'fj' => 'Fijian',          'fi' => 'Finnish',         'fr' => 'French',
            'fy' => 'Western Frisian', 'ff' => 'Fula',            'gd' => 'Scottish Gaelic',
            'gl' => 'Galician',        'lg' => 'Ganda',           'ka' => 'Georgian',
            'de' => 'German',          'el' => 'Greek',           'kl' => 'Greenlandic',
            'gn' => 'Guaraní',         'gu' => 'Gujarati',        'ht' => 'Haitian Creole',
            'ha' => 'Hausa',           'he' => 'Hebrew',          'hz' => 'Herero',
            'hi' => 'Hindi',           'ho' => 'Hiri Motu',       'hu' => 'Hungarian',
            'is' => 'Icelandic',       'io' => 'Ido',             'ig' => 'Igbo',
            'id' => 'Indonesian',      'ia' => 'Interlingua',     'ie' => 'Interlingue',
            'iu' => 'Inuktitut',       'ik' => 'Inupiaq',         'ga' => 'Irish',
            'it' => 'Italian',         'ja' => 'Japanese',        'jv' => 'Javanese',
            'kn' => 'Kannada',         'kr' => 'Kanuri',          'ks' => 'Kashmiri',
            'kk' => 'Kazakh',          'km' => 'Khmer',           'ki' => 'Kikuyu',
            'rw' => 'Kinyarwanda',     'ky' => 'Kyrgyz',          'kv' => 'Komi',
            'kg' => 'Kongo',           'ko' => 'Korean',          'kj' => 'Kwanyama',
            'ku' => 'Kurdish',         'lo' => 'Lao',             'la' => 'Latin',
            'lv' => 'Latvian',         'li' => 'Limburgish',      'ln' => 'Lingala',
            'lt' => 'Lithuanian',      'lu' => 'Luba-Katanga',    'lb' => 'Luxembourgish',
            'mk' => 'Macedonian',      'mg' => 'Malagasy',        'ms' => 'Malay',
            'ml' => 'Malayalam',       'mt' => 'Maltese',         'gv' => 'Manx',
            'mi' => 'Māori',           'mr' => 'Marathi',         'mh' => 'Marshallese',
            'mn' => 'Mongolian',       'na' => 'Nauru',           'nv' => 'Navajo',
            'nd' => 'North Ndebele',   'nr' => 'South Ndebele',   'ng' => 'Ndonga',
            'ne' => 'Nepali',          'no' => 'Norwegian',       'nb' => 'Norwegian Bokmål',
            'nn' => 'Norwegian Nynorsk','ii' => 'Nuosu',          'oc' => 'Occitan',
            'oj' => 'Ojibwe',          'or' => 'Odia',            'om' => 'Oromo',
            'os' => 'Ossetian',        'pi' => 'Pāli',            'ps' => 'Pashto',
            'fa' => 'Persian',         'pl' => 'Polish',          'pt' => 'Portuguese',
            'pa' => 'Punjabi',         'qu' => 'Quechua',         'ro' => 'Romanian',
            'rm' => 'Romansh',         'rn' => 'Rundi',           'ru' => 'Russian',
            'se' => 'Northern Sami',   'sm' => 'Samoan',          'sg' => 'Sango',
            'sa' => 'Sanskrit',        'sc' => 'Sardinian',       'sr' => 'Serbian',
            'sn' => 'Shona',           'sd' => 'Sindhi',          'si' => 'Sinhala',
            'sk' => 'Slovak',          'sl' => 'Slovenian',       'so' => 'Somali',
            'st' => 'Southern Sotho',  'es' => 'Spanish',         'su' => 'Sundanese',
            'sw' => 'Swahili',         'ss' => 'Swati',           'sv' => 'Swedish',
            'tl' => 'Tagalog',         'ty' => 'Tahitian',        'tg' => 'Tajik',
            'ta' => 'Tamil',           'tt' => 'Tatar',           'te' => 'Telugu',
            'th' => 'Thai',            'bo' => 'Tibetan',         'ti' => 'Tigrinya',
            'to' => 'Tongan',          'ts' => 'Tsonga',          'tn' => 'Tswana',
            'tr' => 'Turkish',         'tk' => 'Turkmen',         'tw' => 'Twi',
            'ug' => 'Uyghur',          'uk' => 'Ukrainian',       'ur' => 'Urdu',
            'uz' => 'Uzbek',           've' => 'Venda',           'vi' => 'Vietnamese',
            'vo' => 'Volapük',         'wa' => 'Walloon',         'cy' => 'Welsh',
            'wo' => 'Wolof',           'xh' => 'Xhosa',           'yi' => 'Yiddish',
            'yo' => 'Yoruba',          'za' => 'Zhuang',          'zu' => 'Zulu',
        );

        $aggregated = array();

        foreach ($rows as $row) {
            $raw  = trim($row['accept_language']);
            // Replica la stessa logica usata nel grafico:
            // prende i primi 2 caratteri del primo token prima del ";"
            // (che può essere "it-IT;q=1", "it;q=0.9", "en-US", ecc.)
            $first = strtolower(substr(explode(';', $raw)[0], 0, 2));
            $code  = preg_replace('/[^a-z]/', '', $first);

            if (strlen($code) !== 2) {
                continue; // ignora wildcard "*", codici malformati, ecc.
            }

            $label = isset($iso[$code])
                ? $iso[$code] . ' (' . $code . ')'
                : $code;

            if (!isset($aggregated[$label])) {
                $aggregated[$label] = 0;
            }
            $aggregated[$label] += (int) $row['hits'];
        }

        arsort($aggregated);
        $aggregated = array_slice($aggregated, 0, $limit, true);

        $result = array();
        foreach ($aggregated as $label => $hits) {
            $result[] = array('accept_language' => $label, 'hits' => $hits);
        }
        return $result;
    }

    /**
     * Restituisce i browser raggruppati per nome (senza versione),
     * con conteggio degli hit dalla tabella omeka_hits.
     * I browser non risolti vengono raggruppati come "not identified".
     * I bot vengono identificati con " - bot" in coda al nome.
     *
     * @return array  [['label' => string, 'hits' => int], ...]
     */
    private function _getBrowserStats()
    {
        $db       = get_db();
        $uaTable  = $db->prefix . 'stats_user_agents';
        $hitTable = $db->Hit;

        // Join hits con la cache UA: raggruppa per browser + is_bot,
        // contando il numero di hit. Gli UA non in cache finiscono nel
        // gruppo "not identified".
        $sql = "
            SELECT
                CASE
                    WHEN uar.`browser` IS NULL OR uar.`browser` = ''
                        THEN 'not identified'
                    WHEN uar.`is_bot` = 1
                        THEN CONCAT(uar.`browser`, ' (bot)')
                    ELSE uar.`browser`
                END AS label,
                COUNT(h.`id`) AS hits
            FROM `{$hitTable}` AS h
            LEFT JOIN `{$uaTable}` AS uar
                   ON uar.`ua_hash` = MD5(h.`user_agent`)
            WHERE h.`user_agent` != ''
            GROUP BY label
            ORDER BY hits DESC
            LIMIT 20
        ";

        try {
            $rows = $db->fetchAll($sql);

            // Se tutti i risultati sono "not identified" significa che la
            // cache UA è vuota o non ha ancora nessun browser riconosciuto:
            // restituiamo array vuoto così la view mostra il messaggio "None".
            $hasResolved = false;
            foreach ($rows as $row) {
                if ($row['label'] !== 'not identified') {
                    $hasResolved = true;
                    break;
                }
            }

            return $hasResolved ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Arricchisce la lista dei top user-agent con i dati parsati dalla cache.
     *
     * Per ogni riga:
     * - se lo UA è in stats_user_agents → sostituisce il campo 'user_agent'
     *   con la stringa formattata "Browser Versione [OS] (bot?)" e aggiunge
     *   il flag 'parsed' = true e il raw UA in 'user_agent_raw'
     * - se non è in cache → lascia la stringa grezza e aggiunge
     *   'parsed' = false così la view può avviare il parsing JS
     *
     * @param  array $rows  Output di getMostFrequents('user_agent', ...)
     * @return array
     */
    private function _enrichUserAgents(array $rows)
    {
        if (empty($rows)) {
            return $rows;
        }

        $db      = get_db();
        $uaTable = $db->prefix . 'stats_user_agents';

        // Raccoglie tutti gli hash in un'unica query.
        $hashes = array_map(function ($r) { return md5($r['user_agent']); }, $rows);
        $placeholders = implode(',', array_fill(0, count($hashes), '?'));

        $sql = "SELECT `ua_hash`, `browser`, `browser_version`, `os`, `os_version`,
                       `device_type`, `is_bot`
                FROM `{$uaTable}`
                WHERE `ua_hash` IN ({$placeholders})";

        try {
            $parsed = $db->fetchAssoc($sql, $hashes);
        } catch (Exception $e) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $hash = md5($row['user_agent']);
            $row['user_agent_raw'] = $row['user_agent'];

            if (isset($parsed[$hash])) {
                $p       = $parsed[$hash];
                $browser = trim($p['browser'] . ' ' . $p['browser_version']);
                $os      = trim($p['os'] . ' ' . $p['os_version']);
                $label   = $browser ?: __('Unknown');
                if ($os)           $label .= ' [' . $os . ']';
                if ($p['is_bot'])  $label .= ' (bot)';
                $row['user_agent'] = $label;
                $row['parsed']     = true;
            } else {
                $row['parsed'] = false;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * INSERT … ON DUPLICATE KEY UPDATE a parsed UA row.
     */
    private function _upsertUa($db, $table, $userAgent, array $parsed)
    {
        $hash   = md5((string) $userAgent);
        $fields = array(
            'ua_hash'         => $hash,
            'user_agent'      => mb_substr((string) $userAgent, 0, 1024),
            'browser'         => mb_substr(isset($parsed['browser'])         ? (string) $parsed['browser']         : '', 0, 100),
            'browser_version' => mb_substr(isset($parsed['browser_version']) ? (string) $parsed['browser_version'] : '', 0, 50),
            'engine'          => mb_substr(isset($parsed['engine'])          ? (string) $parsed['engine']          : '', 0, 100),
            'engine_version'  => mb_substr(isset($parsed['engine_version'])  ? (string) $parsed['engine_version']  : '', 0, 50),
            'os'              => mb_substr(isset($parsed['os'])              ? (string) $parsed['os']              : '', 0, 100),
            'os_version'      => mb_substr(isset($parsed['os_version'])      ? (string) $parsed['os_version']      : '', 0, 50),
            'device_type'     => mb_substr(isset($parsed['device_type'])     ? (string) $parsed['device_type']     : '', 0, 50),
            'device_vendor'   => mb_substr(isset($parsed['device_vendor'])   ? (string) $parsed['device_vendor']   : '', 0, 100),
            'device_model'    => mb_substr(isset($parsed['device_model'])    ? (string) $parsed['device_model']    : '', 0, 100),
            'is_bot'          => !empty($parsed['is_bot']) ? 1 : 0,
        );

        $columns      = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $values       = array_values($fields);

        $updateParts = array();
        foreach ($columns as $col) {
            if ($col !== 'ua_hash') {
                $updateParts[] = "`{$col}` = VALUES(`{$col}`)";
            }
        }

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)
             ON DUPLICATE KEY UPDATE %s, `parsed_at` = CURRENT_TIMESTAMP",
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders),
            implode(', ', $updateParts)
        );

        try {
            $db->query($sql, $values);

            // Marca le righe di hit come parsate.
            $hitTable = $db->Hit;
            $db->query(
                "UPDATE `{$hitTable}` SET `ua_parsed` = 1 WHERE `user_agent` = ?",
                array((string) $userAgent)
            );

            return true;
        } catch (Exception $e) {
            _log('Stats UaParser upsert error: ' . $e->getMessage(), Zend_Log::WARN);
            return false;
        }
    }

    /**
     * Send a JSON response. Uses a distinct name (_jsonUa) to avoid any
     * conflict with methods that might exist in parent or sibling classes.
     */
    private function _jsonUa(array $data, $status = 200)
    {
        $response = $this->getResponse();
        $response->setHttpResponseCode($status);
        $response->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $response->setBody(json_encode($data));
    }
}
