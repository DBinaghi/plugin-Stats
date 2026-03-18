<?php
/**
 * Stats_UaParserController
 *
 * AJAX endpoints for user-agent parsing and caching.
 *
 *  GET  /stats/ua-parser/parse-batch   → list of unparsed UA strings
 *  POST /stats/ua-parser/store-batch   → store a chunk of parsed results
 *  POST /stats/ua-parser/parse         → store a single parsed UA (public)
 *
 * @package Stats\controllers
 */
class Stats_UaparserController extends Omeka_Controller_AbstractActionController
{
    /**
     * Disable view rendering for all actions.
     */
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
	}

    /**
     * GET /stats/ua-parser/parse-batch
     *
     * Returns the list of distinct user-agent strings in the hit log that
     * have not yet been parsed. Called by the admin batch tool.
     */
    public function parseBatchAction()
    {
        if (!is_allowed('Stats_Summary', null)) {
            $this->_jsonError('Forbidden', 403);
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

            $this->_json(array(
                'status'      => 'ok',
                'count'       => count($unparsed),
                'user_agents' => array_values($unparsed),
            ));
        } catch (Exception $e) {
            $this->_jsonError('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /stats/ua-parser/store-batch
     *
     * Receives a JSON array of parsed UA objects and stores them.
     * Body: { "items": [ { "user_agent": "...", "parsed": {...} }, ... ] }
     */
    public function storeBatchAction()
    {
        if (!is_allowed('Stats_Summary', null)) {
            $this->_jsonError('Forbidden', 403);
            return;
        }

        if (!$this->getRequest()->isPost()) {
            $this->_jsonError('Method not allowed', 405);
            return;
        }

        $body = $this->getRequest()->getRawBody();
        $data = json_decode($body, true);

        if (empty($data['items']) || !is_array($data['items'])) {
            $this->_jsonError('Invalid payload', 400);
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
                if ($this->_upsert($db, $table, $item['user_agent'], $item['parsed'])) {
                    $stored++;
                } else {
                    $errors++;
                }
            }

            $this->_json(array(
                'status' => 'ok',
                'stored' => $stored,
                'errors' => $errors,
            ));
        } catch (Exception $e) {
            $this->_jsonError('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /stats/ua-parser/parse
     *
     * Stores a single parsed UA sent by the public tracker script.
     * The UA must match the real HTTP_USER_AGENT header to prevent spoofing.
     */
    public function parseAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_jsonError('Method not allowed', 405);
            return;
        }

        $body = $this->getRequest()->getRawBody();
        $data = json_decode($body, true);

        if (empty($data['user_agent']) || empty($data['parsed']) || !is_array($data['parsed'])) {
            $this->_jsonError('Invalid payload', 400);
            return;
        }

        $userAgent = (string) $data['user_agent'];
        $serverUA  = (string) $this->getRequest()->getServer('HTTP_USER_AGENT');

        if ($userAgent !== $serverUA) {
            $this->_jsonError('User agent mismatch', 403);
            return;
        }

        try {
            $db    = get_db();
            $table = $db->prefix . 'stats_user_agents';

            $result = $this->_upsert($db, $table, $userAgent, $data['parsed']);

            if ($result) {
                $this->_json(array('status' => 'ok'));
            } else {
                $this->_jsonError('Database error', 500);
            }
        } catch (Exception $e) {
            $this->_jsonError('Server error: ' . $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * INSERT … ON DUPLICATE KEY UPDATE a parsed UA row.
     *
     * Inlined here (instead of delegating to Table_StatsUserAgent) to avoid
     * any model-resolution issues in the controller context.
     *
     * @param Omeka_Db $db
     * @param string   $table   Full table name with prefix.
     * @param string   $userAgent
     * @param array    $parsed
     * @return bool
     */
    private function _upsert($db, $table, $userAgent, array $parsed)
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
     * Send a JSON response.
     */
    private function _json(array $data, $status = 200)
    {
        $response = $this->getResponse();
        $response->setHttpResponseCode($status);
        $response->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $response->setBody(json_encode($data));
    }

    /**
     * Send a JSON error response.
     */
    private function _jsonError($message, $status = 400)
    {
        $this->_json(array('error' => $message), $status);
    }
}
