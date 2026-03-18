<?php
/**
 * Table_StatsUserAgent
 *
 * Handles DB operations for the user-agent cache table.
 *
 * @package Stats\models\Table
 */
class Table_StatsUserAgent extends Omeka_Db_Table
{
    /**
     * Return the real table name with the Omeka DB prefix.
     *
     * We hardcode the suffix rather than relying on $db->StatsUserAgent
     * because Omeka's __get magic only works reliably for simple model names
     * registered via the autoloader. Using getTableName() is the safe path.
     *
     * @return string  e.g. "omeka_stats_user_agents"
     */
    private function _tableName()
    {
        return $this->getDb()->getTableName('StatsUserAgent');
    }

    /**
     * Return true if the cache table already exists in the database.
     *
     * Used to guard calls made before (or during) plugin installation.
     *
     * @return bool
     */
    public function tableExists()
    {
        $db   = $this->getDb();
        $name = $this->_tableName();
        try {
            $result = $db->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?",
                array($name)
            );
            return (bool) $result;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Find a cached record by the raw user-agent string.
     *
     * @param  string $userAgent
     * @return StatsUserAgent|null
     */
    public function findByUserAgent($userAgent)
    {
        return $this->findBySql(
            'ua_hash = ?',
            array(md5((string) $userAgent)),
            true
        );
    }

    /**
     * Find a cached record by its MD5 hash.
     *
     * @param  string $hash
     * @return StatsUserAgent|null
     */
    public function findByHash($hash)
    {
        return $this->findBySql('ua_hash = ?', array($hash), true);
    }

    /**
     * Return all distinct user-agent strings present in the hits table
     * that have NOT yet been parsed into the cache.
     *
     * @return array  Array of raw user-agent strings.
     */
    public function getUnparsedFromHits()
    {
        $db       = $this->getDb();
        $uaTable  = $this->_tableName();
        $hitTable = $db->getTableName('Hit');

        $sql = "
            SELECT DISTINCT h.`user_agent`
            FROM `{$hitTable}` AS h
            LEFT JOIN `{$uaTable}` AS uar
                   ON uar.`ua_hash` = MD5(h.`user_agent`)
            WHERE h.`user_agent` != ''
              AND uar.`id` IS NULL
        ";

        return $db->fetchCol($sql);
    }

    /**
     * Return the count of distinct user-agent strings in the hit log
     * that have not yet been parsed.
     *
     * Returns 0 silently if the cache table does not exist yet.
     *
     * @return int
     */
    public function countUnparsed()
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $db       = $this->getDb();
        $uaTable  = $this->_tableName();
        $hitTable = $db->getTableName('Hit');

        $sql = "
            SELECT COUNT(DISTINCT h.`user_agent`)
            FROM `{$hitTable}` AS h
            LEFT JOIN `{$uaTable}` AS uar
                   ON uar.`ua_hash` = MD5(h.`user_agent`)
            WHERE h.`user_agent` != ''
              AND uar.`id` IS NULL
        ";

        try {
            return (int) $db->fetchOne($sql);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Insert or update a parsed UA record.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE to handle race conditions
     * gracefully (the ua_hash column has a UNIQUE index).
     *
     * @param  string $userAgent  Raw UA string.
     * @param  array  $parsed     Parsed data array.
     * @return bool
     */
    public function upsert($userAgent, array $parsed)
    {
        $db    = $this->getDb();
        $table = $this->_tableName();
        $hash  = md5((string) $userAgent);

        $fields = array(
            'ua_hash'         => $hash,
            'user_agent'      => (string) $userAgent,
            'browser'         => isset($parsed['browser'])         ? (string) $parsed['browser']         : '',
            'browser_version' => isset($parsed['browser_version']) ? (string) $parsed['browser_version'] : '',
            'engine'          => isset($parsed['engine'])          ? (string) $parsed['engine']          : '',
            'engine_version'  => isset($parsed['engine_version'])  ? (string) $parsed['engine_version']  : '',
            'os'              => isset($parsed['os'])              ? (string) $parsed['os']              : '',
            'os_version'      => isset($parsed['os_version'])      ? (string) $parsed['os_version']      : '',
            'device_type'     => isset($parsed['device_type'])     ? (string) $parsed['device_type']     : '',
            'device_vendor'   => isset($parsed['device_vendor'])   ? (string) $parsed['device_vendor']   : '',
            'device_model'    => isset($parsed['device_model'])    ? (string) $parsed['device_model']    : '',
            'is_bot'          => !empty($parsed['is_bot'])         ? 1                                   : 0,
        );

        $columns      = array_keys($fields);
        $placeholders = array_fill(0, count($fields), '?');
        $values       = array_values($fields);

        $updateParts = array();
        foreach ($columns as $col) {
            if ($col !== 'ua_hash') {
                $updateParts[] = "`$col` = VALUES(`$col`)";
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
            return true;
        } catch (Exception $e) {
            _log('Stats – StatsUserAgent upsert error: ' . $e->getMessage(), Zend_Log::WARN);
            return false;
        }
    }

    /**
     * Return aggregated browser statistics.
     *
     * @param  int $limit
     * @return array  Array of ['label' => string, 'cnt' => int]
     */
    public function getBrowserStats($limit = 10)
    {
        return $this->_getFieldStats('browser', $limit);
    }

    /**
     * Return aggregated OS statistics.
     *
     * @param  int $limit
     * @return array
     */
    public function getOsStats($limit = 10)
    {
        return $this->_getFieldStats('os', $limit);
    }

    /**
     * Return aggregated device-type statistics.
     *
     * @param  int $limit
     * @return array
     */
    public function getDeviceTypeStats($limit = 10)
    {
        return $this->_getFieldStats('device_type', $limit);
    }

    /**
     * Return aggregated bot/human hit counts.
     *
     * @return array  ['bots' => int, 'humans' => int]
     */
    public function getBotStats()
    {
        $db       = $this->getDb();
        $uaTable  = $this->_tableName();
        $hitTable = $db->getTableName('Hit');

        $sql = "
            SELECT
                SUM(CASE WHEN uar.`is_bot` = 1 THEN hit_counts.cnt ELSE 0 END) AS bots,
                SUM(CASE WHEN uar.`is_bot` = 0 THEN hit_counts.cnt ELSE 0 END) AS humans
            FROM (
                SELECT `user_agent`, COUNT(*) AS cnt
                FROM `{$hitTable}`
                WHERE `user_agent` != ''
                GROUP BY `user_agent`
            ) AS hit_counts
            JOIN `{$uaTable}` AS uar
              ON uar.`ua_hash` = MD5(hit_counts.`user_agent`)
        ";

        $row = $db->fetchRow($sql);
        return array(
            'bots'   => (int) ($row ? $row['bots']   : 0),
            'humans' => (int) ($row ? $row['humans']  : 0),
        );
    }

    /**
     * Helper: aggregate hit counts by a single UA field.
     *
     * @param  string $field
     * @param  int    $limit
     * @return array
     */
    private function _getFieldStats($field, $limit = 10)
    {
        $db       = $this->getDb();
        $uaTable  = $this->_tableName();
        $hitTable = $db->getTableName('Hit');
        $limit    = (int) $limit;

        $sql = "
            SELECT uar.`{$field}` AS label, COUNT(h.`id`) AS cnt
            FROM `{$hitTable}` AS h
            JOIN `{$uaTable}` AS uar
              ON uar.`ua_hash` = MD5(h.`user_agent`)
            WHERE uar.`{$field}` != ''
            GROUP BY uar.`{$field}`
            ORDER BY cnt DESC
            LIMIT {$limit}
        ";

        return $db->fetchAll($sql);
    }
}
