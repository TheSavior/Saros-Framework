<?php
/**
 * This class provides an OOP abstraction layer
 * to the underlying PHP sessions. Provides namespace
 * support to restrict name collisions.
 *
 * @copyright Eli White & SaroSoftware 2010
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 *
 * @package SarosFramework
 * @author Eli White
 * @link http://sarosoftware.com
 * @link http://github.com/TheSavior/Saros-Framework
 *
 */
class Saros_Session implements ArrayAccess, Countable, IteratorAggregate
{
	// The namespace of this instance
	protected $namespace;

	protected static $initNamespaces = array();

	// A boolean flag of whether the session has already been started
	protected static $sessionStarted;

	protected $loc;

	/**
	* Create a new namespaced Session object
	*
	* @param mixed $namespace the namespace to use
	* @param mixed $allowMultiple If true, you can only have one instance of Saros_Session tied
	* 									to the specified namespace.
	* 							  If false, you can have unlimited instances to the same
	* 									namespace.
	*/
	public function __construct($namespace, $allowMultiple = false)
	{
		if (!is_string($namespace) || trim($namespace) == "")
			throw new Saros_Session_Exception("The session namespace must be a non-empty string, '".gettype($namespace)."' given.");

		if (!$allowMultiple && isset(self::$initNamespaces[$namespace]) && self::$initNamespaces[$namespace] == true)
			throw new Saros_Session_Exception("Only one instance of Saros_Session can be defined with the namespace '".$namespace."'");

		$this->namespace = $namespace;


		// We might be the first instance, create the _saros namespace

		if (!isset($_SESSION['_saros']))
			$_SESSION['_saros'] = array();

		// This namespace hasn't been instantiated yet, do so
		if (!isset(self::$initNamespaces[$this->namespace]))
		{
			 // make it a new array
			$_SESSION['_saros'][$this->namespace] = array();
			// and store it in a variable for easy access
			$this->loc = $_SESSION['_saros'][$this->namespace];

			// set it as initialized
			self::$initNamespaces[$this->namespace] = true;
		}


		if (!self::$sessionStarted)
			self::start();
	}

	/**
	* Call this function to explicitly start a session
	*
	* @throws Saros_Session_Exception if the session has already been started,
	* 									or if headers have already been sent
	*
	*/
	public static function start()
	{
		if (self::$sessionStarted)
			throw new Saros_Session_Exception("The session has already been started");

		$filename = "";
		$linenum = "";
		if(headers_sent($filename, $linenum))
			throw new Saros_Session_Exception("Headers already sent in '".$filename."' on line '".$linenum."'");

		session_start();

		self::$sessionStarted = true;
	}


	/*
	* PHP 5 Magic Methods
	*/
	public function __get($key)
	{
		// $_SESSION['_saros'][$namespace][$key]
		if (!isset($this->loc[$key]))
			throw new Saros_Session_Exception("The key '".$key."' has not been defined for session namespace '".$this->namespace."'");

		return $_SESSION['_saros'][$this->namespace][$key];
	}

	public function __set($key, $value)
	{
		$this->loc[$key] = $value;
	}

	public function __isset($key)
	{
		return isset($this->loc[$key]);
	}

	public function __unset($key)
	{
		unset($this->loc[$key]);
	}

	/*
	* SPL - Array Access
	*/
	public function offsetGet($key)
	{
		return $this->__get($key);
	}
	public function offsetSet($key, $value)
	{
		return $this->__set($key, $value);
	}
	public function offsetExists($key)
	{
		return $this->__isset($key);
	}
	public function offsetUnset($key)
	{
		$this->__unset($key);
	}

	/*
	* SPL - Countable
	*/
	public function count()
	{
		return count($this->loc);
	}

	/*
	* SPL - IteratorAggregate
	*/
	public function getIterator()
	{
		return new ArrayIterator($this->loc);
	}

}
