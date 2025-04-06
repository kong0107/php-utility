<?php

/**
 * @package PDOi
 */

class PDOi {
	public static $defaultOptions = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
		PDO::ATTR_STRINGIFY_FETCHES => false,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	);

	public const PARAMS = array(
		PDO::PARAM_BOOL => 'bool',
		PDO::PARAM_NULL => 'null',
		PDO::PARAM_INT => 'int',
		PDO::PARAM_STR => 'str',
		PDO::PARAM_STR_NATL => 'str_natl',
		PDO::PARAM_STR_CHAR => 'str_char',
		PDO::PARAM_LOB => 'lob',
		PDO::PARAM_STMT => 'stmt',
		PDO::PARAM_INPUT_OUTPUT => 'input_output'
	); // https://www.php.net/manual/zh/pdo.constants.php

	private PDO $db;

	/** @var callable(mixed): mixed */
	private $logger = null;

	/**
	 * Constructor with different parameter list from parent.
	 *
	 * @param string $driver driver name (ex: mysql, pgsql, sqlsrv, sqlite)
	 * @param array|string $dsn_kv string for sqlite, assoc array for other drivers
	 * @param string|null $username
	 * @param string|null $password
	 * @param array<int, mixed>|null $options
	 *
	 * @throws PDOException
	 */
	public function __construct(
		$driver,
		$dsn_kv,
		$username = null,
		$password = null,
		$options = null
	) {
		$dsn = $driver . ':';
		if (is_string($dsn_kv)) $dsn .= $dsn_kv;
		else {
			$pieces = [];
			foreach ($dsn_kv as $k => $v) $pieces[] = "$k=$v";
			$dsn .= implode(';', $pieces);
		}
		$options = array_replace(self::$defaultOptions, $options ?? array());
		$this->db = new PDO($dsn, $username, $password, $options);
	}

	/**
	 * @param callable $callback
	 * @return void
	 */
	public function set_logger($callback) {
		$this->logger = $callback;
	}

	/**
	 * @param mixed $any
	 * @return mixed
	 */
	private function log($any) {
		if (! $this->logger) return null;
		return call_user_func($this->logger, $any);
	}

	/**
	 * @param string $statement
	 * @return int|false
	 */
	public function exec($statement) {
		$this->log($statement);
		return $this->db->exec($statement);
	}

	/**
	 * Execute a query, with or without paremeters or values.
	 *
	 * @param string $query
	 * @param mixed|array<string, mixed>|array{0: string, 1: mixed, 2?: int|string}[]|null $values
	 * @return PDOStatement|false
	 *
	 * @example
	 * query('SELECT 1 + 2');
	 *
	 * @example
	 * query('SELECT ? + ?', [1, 2]);
	 *
	 * @example
	 * query('SELECT :a + :b', ['a' => 1, 'b' => 2]);
	 *
	 * @example
	 * query('SELECT :a + :b', [
	 *	  ['a', 1, 'int'],
	 *	  ['b', 2, PDO::PARAM_INT]
	 * ]);
	 *
	 */
	public function query($query, $values = null) {
		$this->log($query);
		if (is_null($values) || ! count($values)) return $this->db->query($query);

		$this->log($values);
		$stmt = $this->db->prepare($query);

		if (array_is_list($values) && is_array($values[0])) {
			static::bindStatementValues($stmt, $values);
			$stmt->execute();
		}
		else $stmt->execute($values);
		return $stmt;
	}

	/**
	 * Get all result rows as a two-dimensional array.
	 * @param string $sql
	 * @param array|null $values
	 * @return array<int, array<string, mixed>>|false
	 */
	public function get_all($sql, $values = null) {
		$stmt = $this->query($sql, $values);
		return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
	}

	/**
	 * @param string $sql
	 * @param array|null $values
	 * @return array<array<string, mixed>>|false
	 */
	public function get_all_assoc($sql, $values = null) {
		$stmt = $this->query($sql, $values);
		return $stmt ? $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC) : false;
	}

	/**
	 * Get all values of the first column of each rows of the query result.
	 * @param string $sql
	 * @param array|null $values
	 * @return mixed[]|false
	 */
	public function get_col($sql, $values = null) {
		$stmt = $this->query($sql, $values);
		return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : false;
	}

	/**
	 * Get the value of the first column of the first row of the query result.
	 * @param string $sql
	 * @param array|null $values
	 * @return mixed
	 */
	public function get_one($sql, $values = null) {
		$stmt = $this->query($sql, $values);
		return $stmt ? $stmt->fetchColumn() : false;
	}

	/**
	 * Get the first row as an associative array.
	 * @param string $sql
	 * @param array|null $values
	 * @return mixed[]|false
	 */
	public function get_row($sql, $values = null) {
		$stmt = $this->query($sql, $values);
		return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
	}

	/**
	 * Insert the associative array $data as a new row into $table_name and returns the inserted ID.
	 * @param string $table_name
	 * @param array $row See query()
	 * @return string|false
	 */
	public function insert($table_name, $row) {
		static::assert_valid_identifier($table_name);
		if (array_is_list($row))
			$sql = static::join_columns(
				array_map(function ($a) { return $a[0]; }, $row),
				', '
			);
			// $sql = static::join_columns(array_map(fn ($a) => $a[0], $row), ', ');
		else $sql = static::join_columns(array_keys($row), ', ');

		$sql = "INSERT INTO $table_name SET $sql";
		return $this->query($sql, $row) ? $this->db->lastInsertId() : false;
	}

	/**
	 * Insert multiple rows from a 2d array.
	 * @param string $table_name
	 * @param array[] $rows A list of:
	 * 		1. assoc arrays as elements.
	 * 		2. list arrays as argument list for `PDOStatement::bind*()`
	 * @return int|false
	 */
	public function insert_multi($table_name, $rows) {
		static::assert_valid_identifier($table_name);
		$sql = "INSERT INTO $table_name (%s) VALUES";
		$all_values = array();

		if (array_is_list($rows[0])) {
			$cols = array_unique(array_merge(
				...array_map(
					function ($row) {
						return array_map(function ($fieldInfo) {
							return $fieldInfo[0];
						}, $row);
					},
					// fn ($row) => array_map(fn ($fieldInfo) => $fieldInfo[0], $row),
					$rows
				)
			));
			foreach ($cols as $col) static::assert_valid_identifier($col);
			$sql = sprintf($sql, implode(', ', $cols));

			foreach ($rows as $i => $row) {
				$fields = array();
				foreach ($cols as $col) {
					$args = array_find($row,
						function ($args) use ($col) {
							return $args[0] === $col;
						}
					);
					if (is_array($args)) {
						array_shift($args);
						$all_values[] = $args;
					}
					else $fields[] = 'null';
				}
				if ($i) $sql .= ',';
				$sql .= "\n(" . implode(', ', $fields) . ')';
			}

			$this->log($sql);
			$stmt = $this->db->prepare($sql);
			if (! $stmt) return false;

			$this->log($all_values);
			foreach ($all_values as $i => $args)
				$stmt->bindValue($i + 1, ...$args);
			$success = $stmt->execute();
		}
		else {
			$cols = array_unique(array_merge(
				...array_map(
					// fn ($row) => array_keys($row),
					function ($row) { return array_keys($row); },
					$rows
				)
			));
			foreach ($cols as $col) static::assert_valid_identifier($col);
			$sql = sprintf($sql, implode(', ', $cols));
			foreach ($rows as $i => $row) {
				$fields = array();
				foreach ($cols as $col) {
					if (isset($row[$col])) {
						$fields[] = '?';
						$all_values[] = $row[$col];
					}
					else $fields[] = 'null';
				}
				if ($i) $sql .= ',';
				$sql .= "\n(" . implode(', ', $fields) . ')';
			}
			$this->log($sql);
			$stmt = $this->db->prepare($sql);
			if (! $stmt) return false;
			$this->log($all_values);
			$success = $stmt->execute($all_values);
		}

		if (! $success) return false;
		$this->log($row_count = $stmt->rowCount());
		return $row_count;
	}

	/**
	 * Get all rows which meets $conditions from $table_name.
	 * @param string $table_name
	 * @param array $conditions See query()
	 * @param string $order_by SQL closure for ORDER BY
	 * @return array[]|false
	 */
	public function select_all($table_name, $conditions = array(), $order_by = '') {
		static::assert_valid_identifier($table_name);
		$sql = "SELECT * FROM $table_name" . static::make_where($conditions);
		if ($order_by) $sql .= " ORDER BY $order_by"; /// todo: prevent sql injection
		return $this->get_all($sql, $conditions);
	}

	/**
	 * Get the first row which meets $conditions from $table_name.
	 * @param string $table_name
	 * @param array $conditions See query()
	 * @return array|false
	 */
	public function select_row($table_name, $conditions) {
		static::assert_valid_identifier($table_name);
		$sql = "SELECT * FROM $table_name"
			. static::make_where($conditions)
			. ' LIMIT 1'
		;
		return $this->get_row($sql, $conditions);
	}

	/**
	 * Delete rows which meets $conditions in $table_name.
	 * @param string $table_name
	 * @param array $conditions See query()
	 * @param int $limit
	 * @return int|false
	 */
	public function delete($table_name, $conditions, $limit = -1) {
		static::assert_valid_identifier($table_name);
		$sql = "DELETE FROM $table_name" . static::make_where($conditions);
		if ($limit >= 0) $sql .= " LIMIT $limit";
		$stmt = $this->query($sql, $conditions);
		if (! $stmt) return false;
		$this->log($row_count = $stmt->rowCount());
		return $row_count;
	}

	/**
	 * Update rows.
	 *
	 * NOTE: $data and $conditions may have duplicate keys with different values.
	 *	   Therefore value binding would be different here.
	 *
	 * @param string $table_name
	 * @param array $data See query()
	 * @param array $conditions See query()
	 * @param int $limit
	 * @return int|false
	 */
	public function update($table_name, $data, $conditions, $limit = -1) {
		static::assert_valid_identifier($table_name);
		$sql = "UPDATE $table_name";
		$all_values = array();
		$i = 0;

		$sql_set = '';
		if (array_is_list($data)) {
			foreach ($data as $fieldInfo) {
				$col = array_shift($fieldInfo);
				if ($sql_set) $sql_set .= ', ';
				$sql_set .= "$col = ?";
				$all_values[] = array(++$i, ...$fieldInfo);
			}
		}
		else {
			foreach ($data as $col => $value) {
				if ($sql_set) $sql_set .= ', ';
				$sql_set .= "$col = ?";
				$all_values[] = array(++$i, $value);
			}
		}
		$sql .= " SET $sql_set";

		$sql_where = '';
		if (array_is_list($conditions)) {
			foreach ($conditions as $fieldInfo) {
				$col = array_shift($fieldInfo);
				if ($sql_where) $sql_where .= ' AND ';
				$sql_where .= "$col = ?";
				$all_values[] = array(++$i, ...$fieldInfo);
			}
		}
		else {
			foreach ($conditions as $col => $value) {
				if ($sql_where) $sql_where .= ' AND ';
				$sql_where .= "$col = ?";
				$all_values[] = array(++$i, $value);
			}
		}
		$sql .= " WHERE $sql_where";
		if ($limit >= 0) $sql .= " LIMIT $limit";

		$stmt = $this->query($sql, $all_values);
		if (! $stmt) return false;
		$this->log($row_count = $stmt->rowCount());
		return $row_count;
	}

	/**
	 * @param string $str
	 * @return bool
	 */
	public static function validate($str) {
		return preg_match('/^[A-Za-z_]\w*+$/', $str) ? true : false;
	}

	/**
	 * @param string $identifier
	 * @param string $exception_message_format
	 * @return true
	 * @throws InvalidArgumentException
	 */
	public static function assert_valid_identifier(
		$identifier,
		$exception_message_format = "invalid character in identifier: %s"
	) {
		if (static::validate($identifier)) return true;
		throw new InvalidArgumentException(sprintf($exception_message_format, $identifier));
	}

	/**
	 * @param array $columns
	 * @param string $glue
	 * @return string
	 */
	public static function join_columns($columns, $glue = ' AND ') {
		$pieces = [];
		foreach ($columns as $col) {
			static::assert_valid_identifier($col);
			$pieces[] = "$col = :$col";
		}
		return implode($glue, $pieces);
	}

	/**
	 * @param PDOStatement &$stat
	 * @param array{0: string, 1: mixed, 2?: int|string}[] $params
	 * @return int
	 */
	public static function bindStatementValues(&$stat, $params) {
		foreach ($params as $args) {
			if (count($args) > 2 && gettype($args[2]) === 'string') {
				$type = array_search($args[2], self::PARAMS);
				if ($type === false) $type = PDO::PARAM_STR;
				$args[2] = $type;
			}
			$stat->bindValue(...$args);
			// https://www.php.net/manual/zh/pdostatement.bindparam.php
		}
		return count($params);
	}

	/**
	 * @param array $conditions See query()
	 * @return string
	 */
	public static function make_where($conditions) {
		if (count($conditions)) {
			$cols = array_is_list($conditions)
				// ? array_map(fn ($args) => $args[0], $conditions)
				? array_map(function ($args) { return $args[0]; }, $conditions)
				: array_keys($conditions)
			;
			return ' WHERE ' . static::join_columns($cols);
		}
		return '';
	}
}

if (! function_exists('array_is_list')) {
	function array_is_list($array) {
		$count = count($array);
		for ($i = 0; $i < $count; ++$i) {
			if (! array_key_exists($i, $array)) return false;
		}
		return true;
	}
}
