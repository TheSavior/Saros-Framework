<?php
require_once(dirname(dirname(__FILE__)) . '/Abstract.php');
require_once(dirname(dirname(__FILE__)) . '/Interface.php');
/**
 * MongoDB NoSQL Adapter
 *
 * @package Spot
 * @link http://spot.os.ly
 */
class Spot_Adapter_NoSQL_Mongo extends Spot_Adapter_Abstract implements Spot_Adapter_Interface
{
	// Format for date columns, formatted for PHP's date() function
	protected $format_date = 'Y-m-d';
	protected $format_time = 'H:i:s';
	protected $format_datetime = 'Y-m-d H:i:s';
	
	// Connection details
	protected $connection;
	protected $host;
	protected $database;
	protected $username;
	protected $password;
	protected $options;
	
	// Mongo caches
	protected $_mongoCollection;
	protected $_mongoDatabase;
	
    /**
    * @param mixed $host Host string or pre-existing Mongo object
	* @param string $database Optional if $host is Mongo object
    * @param string $username Optional if $host is Mongo object
    * @param string $password Optional if $host is Mongo object
    * @param array $options
    * @return void
    */
    public function __construct($host, $database = null, $username = null, $password = null, array $options = array())
    {
		// Ensure Mongo PHP extension is loaded (required to work)
		if(!extension_loaded('mongo')) {
			throw new Spot_Exception("MongoDB PHP extension required for this adapter");
		}
		
    	if($host instanceof Mongo) {
    		$this->connection = $host;
    	} else {
			$this->host = $host;
			$this->database = $database;
			$this->username = $username;
			$this->password = $password;
			$this->options = $options;
			
			// Establish connection
			try {
				$this->connection = new Mongo($this->dsn(), $this->options);
			} catch(Exception $e) {
				throw new Spot_Exception($e->getMessage());
			}
    	}
    }
	
	
	/**
	 * Get database connection
	 * 
	 * @return object Mongo
	 */
	public function connection()
	{
		return $this->connection;
	}
	
	
	/**
	 * Get DSN string to connect with
	 * 
	 * @return string
	 */
	public function dsn()
	{
		$dsn = 'mongodb://';
		if($this->username) {
			$dsn .= ($this->username) ? $this->username : '';
			$dsn .= ($this->password) ? ':' . $this->password : '';
			$dsn .= '@';
		}
		$dsn .= $this->host;
		//$dsn .= ($this->database) ? '/' . $this->database : ''; // Uses 'selectDB' instead (above) so we can attept to create db if it does not exist for migrations
		return $dsn;
	}
	
	
	/**
	 * Get database format
	 *
	 * @return string Date format for PHP's date() function
	 */
	public function dateFormat()
	{
		return $this->format_date;
	}
	
	
	/**
	 * Get database time format
	 *
	 * @return string Time format for PHP's date() function
	 */
	public function timeFormat()
	{
		return $this->format_time;
	}
	
	
	/**
	 * Get database format
	 *
	 * @return string DateTime format for PHP's date() function
	 */
	public function dateTimeFormat()
	{
		return $this->format_datetime;
	}
	
	
	/**
	 * Get date
	 *
	 * @return object MongoDate
	 */
	public function date($format = null)
	{
		// MongoDB only supports timestamps for now, not direct DateTime objects
		$format = parent::date($format)->format('U');
		return new MongoDate($format);
	}
	
	
	/**
	 * Get database time format
	 *
	 * @return object MongoDate 
	 */
	public function time($format = null)
	{
		// MongoDB only supports timestamps for now, not direct DateTime objects
		$format = parent::time($format)->format('U');
		return new MongoDate($format);
	}
	
	
	/**
	 * Get datetime
	 *
	 * @return object MongoDate
	 */
	public function dateTime($format = null)
	{
		// MongoDB only supports timestamps for now, not direct DateTime objects
		$format = parent::dateTime($format)->format('U');
		return new MongoDate($format);
	}
	
	
	/**
	 * Escape/quote direct user input
	 *
	 * @param string $string
	 */
	public function escape($string)
	{
		return $string; // Don't think Mongo needs escaping, it's not SQL, and the JSON encoding takes care of properly escaping values...
	}
	
	
	/**
	 * Migrate structure changes to database
	 * 
	 * @param String $datasource Datasource name
	 * @param Array $fields Fields and their attributes as defined in the mapper
	 */
	public function migrate($datasource, array $fields)
	{
		// Ensure collection
		$collection = $this->mongoDatabase()->createCollection($datasource);
		
		// Ensure fields are indexed
		foreach($fields as $field => $opts) {
			if($opts['primary'] !== false || $opts['index'] !== false || $opts['unique'] !== false) {
				$indexOpts = array('safe' => true);
				if($opts['unique'] !== false) {
					$indexOpts['unique'] = true;
				}
				$collection->ensureIndex($field, $indexOpts);
			}
		}
		
		return true; // Whoo! NoSQL doesn't have set schema! No column/field nonsense!
	}
	
	
	/**
	 * Create new entity record with set properties
	 */
	public function create($datasource, array $data)
	{
		// @link http://us3.php.net/manual/en/mongocollection.insert.php
		$saved = $this->mongoCollection($datasource)->insert($data);
		return ($saved) ? $data['_id'] : false;
	}
	
	
	/**
	 * Build a Mongo query
	 *
	 * Finding: @link http://us.php.net/manual/en/mongocollection.find.php
	 * Fields: @link http://us.php.net/manual/en/mongocursor.fields.php
	 * Cursor: @link http://us.php.net/manual/en/class.mongocursor.php
	 * Sorting: @link http://us.php.net/manual/en/mongocursor.sort.php
	 */
	public function read(Spot_Query $query)
	{
		// Get MongoCursor first - it's required for other options
		$criteria = $this->queryConditions($query);
		$mongoCursor = $this->mongoCollection($query->datasource)->find($criteria);
		
		// Organize 'order' options for sorting
		$order = array();
		if($query->order) {
			foreach($query->order as $oField => $oSort) {
				// MongoDB sorting: ASC: 1,  DESC: 2
				$order[$oField] = ($oSort == 'DESC') ? 2 : 1;
			}
		}
		$mongoCursor->sort($order);
		
		// @todo GROUP BY - Not supported YET (!)
		// @link http://www.mongodb.org/display/DOCS/Aggregation#Aggregation-Group
		if($query->group) {
			throw new Spot_Exception("Grouping for the Mongo adapter has not currently been implemented. Would you like to contribute? :)");
		}
		
		// LIMIT & OFFSET (Skip)
		if($query->limit) {
			$mongoCursor->limit($query->limit);
		}
		if($query->offset) {
			$mongoCursor->skip($query->offset);
		}
		
		// Add query to log
		Spot_Mapper_Abstract::logQuery("MongoDB Query", $criteria);
		
		// Return collection
		return $this->toCollection($query, $mongoCursor);
	}
	
	/**
	 * Update entity
	 */
	public function update($datasource, array $data, array $where = array())
	{
		// @todo Check on the _id field to ensure it is set - Mongo can only 'update' existing records or you get an exception
		
		$criteria = $this->queryConditions($where);
		// We are updating multiple entries by default, the same way RDBMS do
		$mongoQuery = $this->mongoCollection($query->datasource)
			->update($criteria, array('$set' => $data), array('multiple' => true));
		
		return $mongoQuery;
	}
	
	
	/**
	 * Delete entities matching given conditions
	 *
	 * @param string $datasource Name of data source
	 * @param array $conditions Array of conditions in column => value pairs
	 */
	public function delete($datasource, array $data)
	{
		// Get MongoCursor first - it's required for other options
		$criteria = $this->queryConditions($query);
		$mongoQuery = $this->mongoCollection($query->datasource)->remove($criteria);
		return $mongoQuery;
	}
	
	
	/**
	 * Returns query conditions in a way that is formatted for Mongo to use
	 *
	 * @param mixed Spot_Query object or associative array
	 */
	public function queryConditions($query)
	{
		$conditions = $query;
		if(is_object($query) && $query instanceof Spot_Query) {
			$conditions = $query->conditions;
		}
		
		if(count($conditions) == 0) { return array(); }
		
		$opts = array();
		$loopOnce = false;
		foreach($conditions as $condition) {
			if(is_array($condition) && isset($condition['conditions'])) {
				$subConditions = $condition['conditions'];
			} else {
				$subConditions = $conditions;
				$loopOnce = true;
			}
			foreach($subConditions as $field => $value) {
				
				// Handle binding depending on type
				if(is_object($value)) {
					if($value instanceof DateTime) {
						// @todo Need to take into account column type for date formatting
						$fieldType = $query->mapper()->fieldType($field);
						$dateTimeFormat = ($fieldType == 'date' ? $this->dateFormat() : ($fieldType == 'time' ? $this->timeFormat() : $this->dateTimeFormat()));
						$value = (string) $value->format($dateTimeFormat);
					} else {
						// Attempt cast of object to string (calls object's __toString method)
						// Will cause E_FATAL if object cannot be cast to string
						if(!($value instanceof MongoId)) {
							$value = (string) $value;
						}
					}
				}
				
				// Special check for Mongo's '_id' field - it needs to be a MongoId object instance
				if($field == '_id' && is_string($value)) {
					$value = new MongoId($value);
				}
				
				// Column name with comparison operator
				$colData = explode(' ', $field);
				$operator = isset($colData[1]) ? $colData[1] : '=';
				if(count($colData) > 2) {
					$operator = array_pop($colData);
					$colData = array( implode(' ', $colData), $operator);
				}
				$col = $colData[0];
				
				// @todo MERGE these array values on the column so they don't overwrite each other
				switch($operator) {
					case '<':
					case ':lt':
						$value = array('$lt' => $value);
					break;
					case '<=':
					case ':lte':
						$value = array('$lte' => $value);
					break;
					case '>':
					case ':gt':
						$value = array('$gt' => $value);
					break;
					case '>=':
					case ':gte':
						$value = array('$gte' => $value);
					break;
					// ALL (Custom to Mongo, but can be adapted to SQL with some clever WHERE clauses)
					case ':all':
						$value = array('$all' => $value);
					break;
					// Not equal
					case '<>':
					case '!=':
					case ':ne':
					case ':not':
						if(is_array($value)) {
							$value = array('$nin' => $value); // NOT IN
						} else {
							$value = array('$ne' => $value);
						}
					break;
					// Equals
					case '=':
					case ':eq':
					default:
						if(is_array($value)) {
							$value = array('$in' => $value); // IN
						}
					break;
				}
				
				// Add value to set options
				$opts[$col] = $value;
			}
			if($loopOnce) { break; }
		}
		return $opts;
	}
	
	
	/**
	 * Truncate a database table
	 * Should delete all rows and reset serial/auto_increment keys to 0
	 */
	public function truncateDatasource($datasource)
	{
		return $this->mongoCollection($datasource)->remove(array());
	}
	
	
	/**
	 * Drop a database table
	 * Destructive and dangerous - drops entire table and all data
	 */
	public function dropDatasource($datasource)
	{
		return $this->mongoCollection($datasource)->drop();
	}
	
	
	/**
	 * Create a database
 	 * Will throw errors if user does not have proper permissions
	 */
	public function createDatabase($database)
	{
		return false;
	}
	
	
	/**
	 * Drop a database table
	 * Destructive and dangerous - drops entire table and all data
	 * Will throw errors if user does not have proper permissions
	 */
	public function dropDatabase($database)
	{
		return false;
	}
	
	
	/**
	 * Return result set for current query
	 */
	public function toCollection(Spot_Query $query, MongoCursor $cursor)
	{
		$mapper = $query->mapper();
		if($cursor instanceof MongoCursor) {
			$results = array();
			$resultsIdentities = array();
			
			// Set timeout
			if(isset($this->options['cursor']['timeout']) && is_int($this->options['cursor']['timeout'])) {
				$cursor->timeout($this->options['cursor']['timeout']);
			}
			
			// Fetch all results into new entity class
			// @todo Move this to collection class so entities will be lazy-loaded by Collection iteration
			foreach($cursor as $entity) {
				
				// Load relations for this row
				$relations = $mapper->getRelationsFor($entity);
				if($relations && is_array($relations) && count($relations) > 0) {
					foreach($relations as $relationCol => $relationObj) {
						$entity->$relationCol = $relationObj;
					}
				}
				
				// Store in array for Collection
				$results[] = $entity;
				
				// Store primary key of each unique record in set
				$pk = $mapper->primaryKey($entity);
				if(!in_array($pk, $resultsIdentities) && !empty($pk)) {
					$resultsIdentities[] = $pk;
				}
				
				// Mark row as loaded
				$entity->loaded(true);
			}
			
			$collectionClass = $mapper->collectionClass();
			return new $collectionClass($results, $resultsIdentities);
			
		} else {
			$mapper->addError(__METHOD__ . " - Unable to execute query - not a valid MongoCursor");
			return array();
		}
	}
	
	
	/**
	 * Returns current Mongo database to use
	 */
	protected function mongoDatabase()
	{
		if(empty($this->_mongoDatabase)) {
			if(!$this->database) {
				throw new Spot_Exception("Mongo must have a database to connect to. No database name was specified.");
			}
			$this->connection(); // Just call to ensure we have a db connection established
			$this->_mongoDatabase = $this->connection->selectDB($this->database);
		}
		return $this->_mongoDatabase;
	}
	
	
	/**
	 * Returns current Mongo collection to use
	 */
	protected function mongoCollection($collectionName)
	{
		if(!isset($this->_mongoCollection[$collectionName])) {
			$this->_mongoCollection[$collectionName] = $this->mongoDatabase()->selectCollection($collectionName);
		}
		return $this->_mongoCollection[$collectionName];
	}
}