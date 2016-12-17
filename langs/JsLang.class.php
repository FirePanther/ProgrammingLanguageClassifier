<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
require_once __DIR__.'/Lang.abstract.php';
class JsLang extends Lang {
	protected $code, $rest, $keys, $errors = false;
	
	function __construct($code) {
		parent::__construct($code);
	}
	
	public function run() {
		$this->rest = ' '.$this->code.' ';
		$this->keys['demerit'] = -2;
		
		$this->keys['stringsLen'] = 0;
		$this->keys['commentsLen'] = 0;
		$this->keys['variablesLen'] = 0;
		
		// escapes
		$this->rest = preg_replace('~\\\\.~', '', $this->rest);
		// scan the whole string (this was the safest solution)
		if ($this->removeStringsAndComments() !== null) {
			// no html
			$this->rest = preg_replace_callback('~<script[^>]*>.*</script>~is', function($m) {
				$this->keys['demerit'] += strlen($m[0]);
				return '';
			}, $this->rest);
			$onEventsRegex = '('.implode('|', $this->keywords(true)).')';
			$this->rest = preg_replace_callback('~ '.$onEventsRegex.'=("|\')[^\1]+\1~i', function($m) {
				$this->keys['demerit'] += strlen($m[0]);
				return '';
			}, $this->rest);
			$this->rest = preg_replace_callback('~ '.$onEventsRegex.'=[^\s>]+~i', function($m) {
				$this->keys['demerit'] += strlen($m[0]);
				return '';
			}, $this->rest);
			// curly brace whitespace
			$this->rest = preg_replace_callback('~\s+\{~i', function($m) {
				$this->keys['demerit'] += strlen($m[0]) - 1;
				return '{';
			}, $this->rest);
			// curly braces validity
			$this->rest = preg_replace_callback('~(\W(else|try|finally|in|return|class|class\s+\w+|extends\s+\w+|implements\s+\w+)\s*)\{~i', function($m) {
				// make it valid for the next check
				$this->keys['demerit'] -= 2;
				return $m[1].'(){';
			}, $this->rest);
			// - the ">" enables a bit of ecmascript 6 validity
			if (preg_match('~[^,\{\(\)\[\=\|\&\!\?\:>]\{~', $this->rest)) {
				$this->errors = true;
				return;
			}
			// whitespaces
			$this->rest = preg_replace_callback('~\s+~m', function($m) {
				$this->keys['demerit'] += strlen($m[0]) - 1;
				return ' ';
			}, $this->rest);
			// regex
			$this->rest = preg_replace('~/[^/]+/[gim]+~i', ' ', $this->rest);
			// function declarations and anonymous functions
			$this->rest = preg_replace_callback('~(\W)function(\s+\w+)?\s*\(~i', function($m) {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += 8;
				return $m[1];
			}, $this->rest);
			// keywords
			$keywords = $this->keywords();
			foreach ($keywords as $k) {
				$this->rest = preg_replace_callback('~\W('.preg_quote($k).')\s*(\([^\)]*\))?\b~', function($m) {
					$this->keys['keywords']++;
					$this->keys['keywordsLen'] += strlen($m[1]);
					return '';
				}, $this->rest);
			}
			// function calls
			$this->rest = preg_replace_callback('~(\W)(\w+)\([^\)]*\)\s*(;|\{)~i', function($m) {
				if ($m[2] == 'constructor') {
					$this->keys['keywords']++;
					$this->keys['keywordsLen'] += strlen($m[2]);
				}
				return $m[1];
			}, $this->rest);
			// special chars
			$this->rest = preg_replace('~(/|\*|=|;|\(|\)|\[|\]|\{|\}|\&|\|\||,|\?|\:|\!|<|>|\+|\-|\d*\.\d+e?|\d+\.\d*e?|0x\d+|\d+e?)~i', ' ', $this->rest);
			// objects, arrays, variables
			$this->rest = preg_replace('~\bnew \w+~', '', $this->rest);
			$this->rest = preg_replace('~(\)|\]|\})\.~i', '$1 ', $this->rest);
			do {
				$restBackup = $this->rest;
				$this->rest = preg_replace('~\[[^\]]+\]~i', '', $this->rest);
			} while($restBackup !== $this->rest);
			$this->keys['validPhpVarsLen'] = 0; // no variables at all
			$this->rest = preg_replace_callback('~([a-z\$_][a-z0-9\$_]*)(\.[a-z\$_][a-z0-9\$_]*)?~i', function($m) {
				$this->keys['variablesLen'] += strlen($m[0]);
				
				if (strpos($m[0], '(') === false && !(!isset($m[2]) && in_array($m[1], [
					'echo', 'return', 'break', 'case', 'continue', 'var', 'self', 'const', 'print', 'global', 'use','abstract',
					'class','declare','extends','interface','namespace'
				]))) {
					if (preg_match('~^\$[a-z_]\w*($|\.)~', $m[0])) $this->keys['validPhpVarsLen'] += strlen($m[0]);
					else $this->keys['validPhpVarsLen'] += strlen($m[0]);
				}
				return '';
			}, $this->rest);
			// remove whitespace
			$this->rest = preg_replace_callback('~\s+~', function($m) {
				$this->keys['demerit'] += strlen($m[0]);
				return '';
			}, $this->rest);
		} else {
			$this->errors = true;
		}
	}
	
	/**
	 * @see Lang.abstract.php -> demerit()
	 */
	public function demerit() {
		return $this->keys['stringsLen'] + $this->keys['demerit'];
	}
	
	/**
	 * scan the whole string (this was the safest solution)
	 * this function also caches the data for the next runs with the same code
	 */
	private function removeStringsAndComments() {
		$cs = md5($this->rest);
		$lang = 'js';
		$inString = false;
		$inComment = false;
		$error = false;
		for ($i = 0, $l = strlen($this->rest); $i < $l; $i++) {
			if ($inString) {
				if ($this->rest[$i] == $inString) {
					// string finished
					$len = $i - $start + strlen($inString);
					$this->rest = substr($this->rest, 0, $start).($error ? str_repeat('\'', $len) : '').substr($this->rest, $i + strlen($inString));
					$inString = 0;
					if ($error) $error = false;
					else {
						$i -= $len;
						$l -= $len;
						$this->keys['stringsLen'] += $len;
					}
				} elseif ($this->rest[$i] === "\n") {
					//$error = true;
					return null; // completely cancel after syntax error
				}
			} elseif ($inComment) {
				if ($this->rest[$i] == $inComment[0] && (strlen($inComment) == 1 || strlen($inComment) == 2 && $this->rest[$i + 1] == $inComment[1])) {
					// comment finished
					$len = $i - $start + strlen($inComment);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inComment));
					$inComment = 0;
					$i -= $len;
					$l -= $len;
					$this->keys['commentsLen'] += $len;
				}
			} else {
				if ($this->rest[$i] == '\'' || $this->rest[$i] == '"') {
					$inString = $this->rest[$i];
					$start = $i;
				} elseif ($this->rest[$i] == '/' && ($this->rest[$i + 1] == '*' || $this->rest[$i + 1] == '/')) {
					$inComment = $this->rest[$i + 1] == '*' ? '*/' : "\n";
					$start = $i;
					$i++;
				}
			}
		}
		return $this->rest;
	}
	
	
	/**
	 * js keywords (system functions, constants, ...)
	 */
	private function keywords($justOnEvents = false) {
		$onEvents = ['onclick', 'oncontextmenu', 'ondblclick', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove',
			'onmouseover', 'onmouseout', 'onmouseup', 'onkeydown', 'onkeypress', 'onkeyup', 'onabort', 'onbeforeunload',
			'onerror', 'onhashchange', 'onload', 'onpageshow', 'onpagehide', 'onresize', 'onscroll', 'onunload', 'onblur',
			'onchange', 'onfocus', 'onfocusin', 'onfocusout', 'oninput', 'oninvalid', 'onreset', 'onsearch', 'onselect',
			'onsubmit', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop',
			'oncopy', 'oncut', 'onpaste', 'onafterprint', 'onbeforeprint', 'onabort', 'oncanplay', 'oncanplaythrough',
			'ondurationchange', 'onemptied', 'onended', 'onerror', 'onloadeddata', 'onloadedmetadata', 'onloadstart',
			'onpause', 'onplay', 'onplaying', 'onprogress', 'onratechange', 'onseeked', 'onseeking', 'onstalled', 'onsuspend',
			'ontimeupdate', 'onvolumechange', 'onwaiting'];
		if ($justOnEvents) return $onEvents;
		
		return array_merge([
			'break','case','catch','const','continue',
			'default','delete','do',
			'else',
			'finally','for','function',
			'get','goto',
			'if','in','instanceof',
			'new',
			'prototype',
			'return',
			'set','static','switch',
			'this','throw','try','typeof',
			'var','void',
	
			'false','null','true','undefined','NaN','Infinity',
	
			'__proto__','__defineGetter__','__defineSetter__','hasOwnProperty','hasProperty',
	
			'Object', 'Function', 'Date', 'Math', 'String', 'Number', 'Boolean', 'Array',
	
			'abstract','boolean','byte','char','class','debugger','double','enum','export','extends',
			'final','float','implements','import','int','interface','long','native',
			'short','super','synchronized','throws','transient','volatile',
			
			'window','document','console','constructor',
			
			'alert', 'back', 'blur', 'cancelIdleCallback', 'captureEvents', 'clearImmediate', 'close',
			'confirm', 'disableExternalCapture', 'dispatchEvent', 'dump', 'enableExternalCapture', 'find',
			'focus', 'forward', 'getAttention', 'getAttentionWithCycleCount', 'getComputedStyle',
			'getDefaultComputedStyle', 'getSelection', 'home', 'matchMedia', 'maximize', 'minimize', 'moveBy',
			'moveTo', 'mozRequestAnimationFrame', 'open', 'openDialog', 'postMessage', 'print', 'prompt',
			'releaseEvents', 'removeEventListener', 'requestIdleCallback', 'resizeBy', 'resizeTo', 'restore',
			'routeEvent', 'scroll', 'scrollBy', 'scrollByLines', 'scrollByPages', 'scrollTo', 'setCursor',
			'setImmediate', 'setResizable', 'showModalDialog', 'sizeToContent', 'stop', 'updateCommands',
			'mozRequestAnimationFrame',
			
			'animationend', 'animationiteration', 'animationstart',
			'transitionend', 'onerror', 'onmessage', 'onopen', 'onmessage', 'onmousewheel', 'ononline', 'onoffline',
			'onpopstate', 'onshow', 'onstorage', 'ontoggle', 'onwheel', 'ontouchcancel', 'ontouchend', 'ontouchmove',
			'ontouchstart', 'CAPTURING_PHASE', 'AT_TARGET', 'BUBBLING_PHASE', 'bubbles', 'cancelable', 'currentTarget',
			'defaultPrevented', 'eventPhase', 'isTrusted', 'target', 'timeStamp', 'type', 'view', 'preventDefault',
			'stopImmediatePropagation', 'stopPropagation'
		], $onEvents);
	}
}
