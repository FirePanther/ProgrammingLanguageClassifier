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
		$this->rest = strtolower($this->code);
		
		$this->keys['stringsLen'] = 0;
		$this->keys['commentsLen'] = 0;
		
		// whitespaces
		$this->rest = preg_replace_callback('~\s+~', function($m) {
			$this->keys['demerit'] += strlen($m[0]) - 1;
			return ' ';
		}, $this->rest);
		// comments
		//$this->rest = preg_replace('~/\*.*?\*/~', '', $this->rest);
		// comments and strings
		$this->removeStringsAndComments();
		// no html
		$this->rest = preg_replace_callback('~<style[^>]*>.*</style>~i', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
		$this->rest = preg_replace_callback('~ style=("|\')[^\1]+\1~i', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
		$this->rest = preg_replace_callback('~ style=[^ >]+~i', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
		// no whitespaces at all
		$this->rest = preg_replace_callback('~\s+~', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
		// urls
		$this->rest = preg_replace_callback('~url\(("|\')[^\1]*?\1\)~', function() {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += 2;
			return 'url';
		}, $this->rest);
		$this->rest = preg_replace_callback('~url\([^\)]*?\)~', function() {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += 2;
			return 'url';
		}, $this->rest);
		// prefixes
		$this->rest = preg_replace_callback('~\-(webkit|moz|ms|o|khtml)\-~', function($m) {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += $m[0];
			return '';
		}, $this->rest);
		// @media, @keyframe, @font-face, ...
		$this->rest = preg_replace_callback('~(\@[\w-]+)[^\{]+\{~', function($m) {
			if (strpos($m[1], '@media') !== false || strpos($m[1], '@font-face') !== false || strpos($m[1], '@keyframes') !== false) {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += $m[1];
			}
			return '{';
		}, $this->rest);
		// inside curly braces
		$this->rest = preg_replace_callback('~\{([^\{\}]*)\}~', function($m) {
			$m[1] = preg_replace_callback('~((^|;)([\w-]+)\:[^;]+)+~', function($m) {
				if (in_array($m[3], $this->keywords())) {
					$this->keys['keywords']++;
					$this->keys['keywordsLen'] += strlen($m[3]);
				}
				return '';
			}, $m[1]);
			return '{'.str_replace(';', '', $m[1]).'}';
		}, $this->rest);
		// selectors with round brackets
		$this->rest = preg_replace('~\:\:?(lang\(\w*\)|not\([^\)]+\)|nth(\-last)?\-child\(\d+\)|nth(\-last)?\-of\-type\(\d+\))~', '', $this->rest);
		// selectors
		$this->rest = preg_replace_callback('~(?:^|\}|\{)([\w.\*\#>\+\~\[\]\%\|\^\$=\:\-"\',]+)\{~', function($m) {
			return '';
			$m[1] = str_replace('*', '', $m[1]);
			$m[1] = preg_replace('~\d+\%~', '', $m[1]); // for keyframes
			// :
			$m[1] = preg_replace('~\:\:?\-[\w-]+~', '', $m[1]); // prefix
			$m[1] = preg_replace_callback('~\:\:?(?:active|after|before|checked|disabled|empty|enabled|first\-child|first\-letter|'.
				'first\-line|first\-of\-type|focus|hover|in\-range|invalid|lang||last\-child|link|not|nth\-child|nth\-last\-child|'.
				'nth\-last|-of\-type|nth\-of\-type|only\-of\-type|only\-child|optional|out\-of\-range|read\-only|read\-write|'.
				'required|root|selection|target|valid|visited)(\W)~', function($m) {
					$this->keys['keywords']++;
					$this->keys['keywordsLen'] += strlen($m[0]) - strlen($m[1]);
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
	
	/**
	 * @see Lang.abstract.php -> demerit()
	 */
	public function demerit() {
		return $this->keys['stringsLen'] + $this->keys['demerit'];
	}
	
	/**
	 * remove strings and comments just to be sure it's not a php or js file with much css
	 */
	private function removeStringsAndComments() {
		$inString = false;
		$inComment = false;
		for ($i = 0, $l = strlen($this->rest); $i < $l; $i++) {
			// skip if escaped, except you're in a comment
			// '/* \*/' or '// eol: \' <- don't escape
			if ($this->rest[$i] == '\\' && !$inComment) {
				$i++;
				continue;
			}
			if ($inString) {
				if ($this->rest[$i] == $inString) {
					// string finished
					$len = $i - $start + strlen($inString);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inString));
					$inString = 0;
					$i -= $len - (strlen($inString) - 1);
					$l -= $len;
					$this->keys['stringsLen'] += $len;
				}
			} elseif ($inComment) {
				if (substr($this->rest, $i, strlen($inComment)) == $inComment) {
					// comment finished
					$len = $i - $start + strlen($inComment);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inComment));
					$i -= $len - (strlen($inComment) - 1);
					$l -= $len;
					$inComment = 0;
					$this->keys['commentsLen'] += $len;
				}
			} else {
				$c = $this->rest[$i];
				// strings
				if ($c == '\'' || $c == '"') {
					$inString = $this->rest[$i];
					$start = $i;
				// default single- and multi-line comments
				} elseif (substr($this->rest, $i, 2) == '/*') {
					$inComment = $this->rest[$i + 1] == '*' ? '*/' : "\n";
					$start = $i;
					$i ++;
				// html comments
				} elseif (substr($this->rest, $i, 4) == '<!--') {
					return null;
				}
			}
		}
		return true;
	}
	
	/**
	 * css keywords
	 */
	private function keywords() {
		return [
			'align-content', 'align-items', 'align-self', 'animation', 'animation-delay', 'animation-direction', 'animation-duration',
			'animation-fill-mode', 'animation-iteration-count', 'animation-name', 'animation-play-state', 'animation-timing-function',
			'backface-visibility', 'background', 'background-attachment', 'background-blend-mode', 'background-clip', 'background-color',
			'background-image', 'background-origin', 'background-position', 'background-repeat', 'background-size', 'border',
			'border-bottom', 'border-bottom-color', 'border-bottom-left-radius', 'border-bottom-right-radius', 'border-bottom-style',
			'border-bottom-width', 'border-collapse', 'border-color', 'border-image', 'border-image-outset', 'border-image-repeat',
			'border-image-slice', 'border-image-source', 'border-image-width', 'border-left', 'border-left-color', 'border-left-style',
			'border-left-width', 'border-radius', 'border-right', 'border-right-color', 'border-right-style', 'border-right-width',
			'border-spacing', 'border-style', 'border-top', 'border-top-color', 'border-top-left-radius', 'border-top-right-radius',
			'border-top-style', 'border-top-width', 'border-width', 'bottom', 'box-shadow', 'box-sizing', 'caption-side', 'clear', 'clip',
			'color', 'column-count', 'column-fill', 'column-gap', 'column-rule', 'column-rule-color', 'column-rule-style',
			'column-rule-width', 'column-span', 'column-width', 'columns', 'content', 'counter-increment', 'counter-reset', 'cursor',
			'direction', 'display', 'empty-cells', 'filter', 'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink',
			'flex-wrap', 'float', 'font', 'font-family', 'font-size', 'font-size-adjust', 'font-stretch', 'font-style', 'font-variant',
			'font-weight', 'hanging-punctuation', 'height', 'justify-content', 'left', 'letter-spacing', 'line-height', 'list-style',
			'list-style-image', 'list-style-position', 'list-style-type', 'margin', 'margin-bottom', 'margin-left', 'margin-right',
			'margin-top', 'max-height', 'max-width', 'min-height', 'min-width', 'nav-down', 'nav-index', 'nav-left', 'nav-right', 'nav-up',
			'opacity', 'order', 'outline', 'outline-color', 'outline-offset', 'outline-style', 'outline-width', 'overflow', 'overflow-x',
			'overflow-y', 'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top', 'page-break-after', 'page-break-before',
			'page-break-inside', 'perspective', 'perspective-origin', 'position', 'quotes', 'resize', 'right', 'tab-size', 'table-layout',
			'text-align', 'text-align-last', 'text-decoration', 'text-decoration-color', 'text-decoration-line', 'text-decoration-style', 
			'text-indent', 'text-justify', 'text-overflow', 'text-shadow', 'text-transform', 'top', 'transform', 'transform-origin',
			'transform-style', 'transition', 'transition-delay', 'transition-duration', 'transition-property', 'transition-timing-function',
			'unicode-bidi', 'vertical-align', 'visibility', 'white-space', 'width', 'word-break', 'word-spacing', 'word-wrap', 'z-index'
		];
	}
}
