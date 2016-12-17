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
		$this->keys = [
			'keywords' => 0,
			'keywordsLen' => 0,
			'demerit' => 0
		];
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
	 * demerits, min (default): 0, max: length of rest
	 */
	public function demerit() {
		return $this->keys['demerit'];
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
		$codeLength = strlen($this->code);
		if (!$codeLength) return 0;
		$bonus = $this->keys['keywordsLen'];
		$prob = 1 - (strlen($this->rest) + $this->demerit() - $bonus) / $codeLength;
		if ($prob < 0) $prob = 0;
		return $this->hasErrors() ? 0 : $prob;
	}
	
	/**
	 * set a local variable
	 */
	public function set($key, $val, $action = null) {
		switch ($action) {
			case '.':
			case '&':
				$this->keys[$key] .= $val;
				break;
			case '+':
				$this->keys[$key] += $val;
				break;
			case '-':
				$this->keys[$key] -= $val;
				break;
			case '*':
				$this->keys[$key] *= $val;
				break;
			case '/':
				$this->keys[$key] /= $val;
				break;
			default:
				$this->keys[$key] = $val;
		}
	}
	
	/**
	 * get a local variable
	 */
	public function get($key, $default = null) {
		if (isset($this->keys[$key])) return $this->keys[$key];
		else return $default;
	}
}
