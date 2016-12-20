<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
class LangDetect {
	private $langs = [];
	private $probabilities = [];
	private $code = '';
	
	function __construct($code = null, $langs = null, $showTime = false) {
		$this->setLangs($langs);
		if ($code !== null) $this->parseCode($code, $showTime);
	}
	
	/**
	 * sets the language(s), as array or comma separated
	 */
	public function setLangs($langs) {
		if ($langs === null) $this->langs = ['php', 'html', 'css', 'js'];
		elseif (is_array($langs)) $this->langs = $langs;
		else $this->langs = explode(',', preg_replace('~\W+~i', ',', $langs));
	}
	
	/**
	 * checks the code, runs it in every defined language
	 */
	public function parseCode($code, $showTime = false) {
		$code = str_replace(["\r\n", "\r"], "\n", $code);
		$this->code = $code;
		$this->probabilities = [];
		
		// track time
		if ($showTime) $startTime = microtime(1);
		
		// call all language classes
		$class = [];
		foreach ($this->langs as $lang) {
			$lang = strtolower($lang);
			$langClass = ucfirst($lang).'Lang';
			if (file_exists(__DIR__."/langs/$langClass.class.php")) {
				require_once __DIR__."/langs/$langClass.class.php";
				$class[$lang] = new $langClass($this->code, false);
			}
		}
		
		// fetch all probabilities and sort them
		foreach ($class as $lang => $c) {
			$this->probabilities[$lang] = $c->probability();
		}
		arsort($this->probabilities, SORT_NUMERIC);
		
		if ($showTime) echo 'time: '.(microtime(true) - $startTime).' s'.PHP_EOL;
	}
	
	/**
	 * get an array with the probabilities, sorted by the likely one at the top
	 */
	public function getProbabilities($total100 = false) {
		if ($total100) {
			/* output with total of 100% (1), good for comparisson, bad if the actual language is none of them */
			$total = 0;
			foreach ($this->probabilities as $k => $v) {
				$total += $v;
			}
			$results = [];
			foreach ($this->probabilities as $k => $v) {
				$results[$k] = $total ? $v / $total : 0;
			}
			return $results;
		} else {
			/* output with per language total of 100% (1) + bonuses (can be more than 1) */
			return $this->probabilities;
		}
	}
}
