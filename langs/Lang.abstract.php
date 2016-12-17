<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
abstract class Lang {
	protected $code, $rest, $keys, $errors;
	
	/**
	 * construct for setting the code by creating an object
	 */
	function __construct($code) {
		$this->code = $code;
		$this->run();
	}
	
	/**
	 * the main method to remove known syntax from the code
	 */
	abstract public function run();
	
	/**
	 * the rest (unknown syntax)
	 */
	public function rest() {
		return $this->rest;
	}
	
	/**
	 * returns if there were any errors (syntax errors just cancel the whole run and return probability 0)
	 */
	public function hasErrors() {
		return isset($this->errors) ? $this->errors == true : false;
	}
	
	/**
	 * the actual probability, if syntax errors were found the probability is 0
	 */
	public function probability() {
		return $this->hasErrors() ? 0 : (1 - strlen($this->rest) / strlen($this->code));
	}
	
	/**
	 * set a local variable
	 */
	protected function set($key, $val) {
		$this->keys[$key] = $val;
	}
	
	/**
	 * get a local variable
	 */
	public function get($key, $default = null) {
		if (isset($this->keys[$key])) return $this->keys[$key];
		else return $default;
	}
}
