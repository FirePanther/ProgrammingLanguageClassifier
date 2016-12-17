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
	
	function __construct($code = null, $langs = null) {
		$this->setLangs($langs);
		if ($code !== null) $this->parseCode($code);
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
	public function parseCode($code) {
		$code = str_replace(["\r\n", "\r"], "\n", $code);
		$this->code = $code;
		$this->probabilities = [];
		
		// track time
		$startTime = microtime(1);
		
		// call all language classes
		foreach ($this->langs as $lang) {
			$lang = strtolower($lang);
			$langClass = ucfirst($lang).'Lang';
			if (file_exists("langs/$langClass.class.php")) {
				require_once "langs/$langClass.class.php";
				$class[$lang] = new $langClass($this->code, false);
			}
		}
		
		### some bonuses and demerits for language specific stuff (like keywords)
		
		/* little collaboration between js and php, if both are active */
		if (isset($class['js'], $class['php'])) {
			if ($class['js']->get('validPhpVarsLen') !== null) {
				// php bonus for "php valid variables"
				if ($class['js']->get('validPhpVarsLen') > 0) {
					// most variables are php valid, decrease js, just a bit because it could still be a js var
					$class['js']->set('demerit', abs($class['js']->get('validPhpVarsLen') / 2), '+');
				} elseif ($class['js']->get('validPhpVarsLen') < 0) {
					// not all variables are php valid, decrease php
					$class['php']->set('demerit', -$class['js']->get('validPhpVarsLen'), '+');
				}
			}
		}
		
		// fetch all probabilities and sort them
		foreach ($class as $lang => $c) {
			$this->probabilities[$lang] = $c->probability();
		}
		arsort($this->probabilities, SORT_NUMERIC);
		
		echo 'time: '.round(microtime(1) - $startTime, 3).' s'.PHP_EOL;
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
