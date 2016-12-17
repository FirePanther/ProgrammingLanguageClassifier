<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
require_once __DIR__.'/Lang.abstract.php';
class CssLang extends Lang {
	protected $code, $rest, $keys, $errors = false;
	
	/**
	 * @see Lang.abstract.php -> __construct()
	 */
	function __construct($code) {
		parent::__construct($code);
	}
	
	/**
	 * @see Lang.abstract.php -> run()
	 */
	public function run() {
		$this->rest = ' '.strtolower($this->code).' ';
		
		$this->keys['keywords'] = 0;
		
		// whitespaces
		$this->rest = preg_replace('~\s+~', '', $this->rest);
		// comments
		$this->rest = preg_replace('~/\*.*?\*/~', '', $this->rest);
		// no html
		$this->rest = preg_replace('~<style[^>]*>.*</style>~i', '', $this->rest);
		// urls
		$this->rest = preg_replace_callback('~url\(("|\')[^\1]*?\1\)~', function() {
			$this->keys['keywords']++;
			return 'url';
		}, $this->rest);
		$this->rest = preg_replace_callback('~url\([^\)]*?\)~', function() {
			$this->keys['keywords']++;
			return 'url';
		}, $this->rest);
		// @media, @keyframe, @font-face, ...
		$this->rest = preg_replace_callback('~\@\-?\w[^\{]+\{~', function() {
			$this->keys['keywords']++;
			return '{';
		}, $this->rest);
		// inside curly braces
		$this->rest = preg_replace_callback('~\{([^\{\}]*)\}~', function($m) {
			$m[1] = preg_replace('~((^|;)[\w-]+\:[^;]+)+~', '', $m[1]);
			return '{'.str_replace(';', '', $m[1]).'}';
		}, $this->rest);
		// selectors
		$this->rest = preg_replace_callback('~(?:^|\}|\{)([\w.\*\#>\+\~\[\]\%\|\^\$=\:\(\)\-"\',]+)\{~', function($m) {
			return '';
			$m[1] = str_replace('*', '', $m[1]);
			$m[1] = preg_replace('~\([^\)]*\)~', '', $m[1]); // for lang, not, nth-child, ...
			$m[1] = preg_replace('~\d+\%~', '', $m[1]); // for keyframes
			// :
			$m[1] = preg_replace('~\:\:?\-[\w-]+~', '', $m[1]); // prefix
			$m[1] = preg_replace_callback('~\:\:?(?:active|after|before|checked|disabled|empty|enabled|first\-child|first\-letter|'.
				'first\-line|first\-of\-type|focus|hover|in\-range|invalid|lang||last\-child|link|not|nth\-child|nth\-last\-child|'.
				'nth\-last|-of\-type|nth\-of\-type|only\-of\-type|only\-child|optional|out\-of\-range|read\-only|read\-write|'.
				'required|root|selection|target|valid|visited)(\W)~', function($m) {
					$this->keys['keywords']++;
					return $m[1];
				}, $m[1]);
			// attributes
			$m[1] = preg_replace('~\[[\w-]+((=|\~=|\|=|\^=|\$=|\*=)[^\]]+)?\]~', '', $m[1]);
			// elements
			$m[1] = preg_replace('~(\#|\.)[\w-]+~', '', $m[1]);
			// rest \w
			$m[1] = preg_replace('~[\w\->\+\~]+~', '', $m[1]);
			return '##>'.$m[1].'<##{';
		}, $this->rest);
		// remove curly braces
		$this->rest = str_replace(['{', '}'], '', $this->rest);
	}
}
