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
		$this->code = $code;
		$this->probabilities = [];
		
		// track time
		$startTime = microtime(1);
		
		foreach ($this->langs as $lang) {
			$lang = strtolower($lang);
			$langClass = ucfirst($lang).'Lang';
			if (file_exists("langs/$langClass.class.php")) {
				require_once "langs/$langClass.class.php";
				$class[$lang] = new $langClass($this->code, false);
				$this->probabilities[$lang] = $class[$lang]->probability();
			}
		}
		
		### some bonuses for language specific stuff (like keywords)
		
		/* little collaboration between js and php, if both are active */
		if (isset($this->probabilities['js'], $this->probabilities['php']) && $this->probabilities['js'] && $this->probabilities['php']) {
			if ($class['js']->get('allVarsPhpValid') !== null) {
				// php bonus for "php valid variables" only
				if ($class['js']->get('allVarsPhpValid') > 0) $this->probabilities['php'] *= 1.1; // increase php probability
				elseif ($class['js']->get('allVarsPhpValid') < 0) $this->probabilities['php'] /= 1.1; // decrease php probability
			}
		}
		
		// bonus for more native keyword replacements
		$totalFoundKeywords = 0;
		foreach ($class as $v) {
			$totalFoundKeywords += $v->get('keywords', 0);
		}
		if ($totalFoundKeywords) {
			foreach ($class as $k => $v) {
				$this->probabilities[$k] *= 1 + $v->get('keywords', 0) / $totalFoundKeywords;
			}
		}
		
		arsort($this->probabilities, SORT_NUMERIC);
		
		echo 'time: '.round(microtime(1) - $startTime, 3).' s'.PHP_EOL;
	}
	
	/**
	 * get an array with the probabilities, sorted by the likely one at the top
	 */
	public function getProbabilities($total100 = false) {
		if ($total100) {
			/* output with total of 100% (1) */
			$total = 0;
			foreach ($this->probabilities as $k => $v) {
				$total += $v;
			}
			$results = [];
			foreach ($this->probabilities as $k => $v) {
				$results[$k] = $v / $total;
			}
			return $results;
		} else {
			/* output with per language total of 100% (1) + bonuses (can be more than 1) */
			return $this->probabilities;
		}
	}
}
