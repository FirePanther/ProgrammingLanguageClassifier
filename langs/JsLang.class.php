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
		
		// scan the whole string (this was the safest solution)
		if ($this->removeStringsAndComments() !== null) {
			// escapes
			$this->rest = preg_replace('~\\\\.~', '', $this->rest); // remove escape after string&comment removal, else /* \*/ will be buggy
			// no nowdoc/heredoc
			if (preg_match('~<<<\s*(\'|)([a-z0-9]+)\1\n.*\n\2\;~siU', $this->rest)) {
				$this->errors = true;
				return;
			}
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
			// regex
			do {
				$restBackup = $this->rest;
				$this->rest = preg_replace_callback('~/[^/]*/[gim]*( *(?:[\n,.;\)\]\:\?\}]))~', function($m) {
					$this->keys['keywords']++;
					$this->keys['keywordsLen'] += strlen($m[0]) - 1 - strlen($m[1]);
					return ' '.$m[1];
				}, $this->rest);
			} while($this->rest !== $restBackup);
			// e.g. php comments
			if (strpos($this->rest, '#') !== false) {
				$this->errors = true;
				return;
			}
			// - the ">" enables a bit of ecmascript 6 validity
			if (preg_match('~[^,\{\(\)\[\=\|\&\!\?\:>;]\{~', $this->rest)) {
				$this->errors = true;
				return;
			}
			// whitespaces
			$this->rest = preg_replace_callback('~[ ]+~', function($m) {
				$this->keys['demerit'] += strlen($m[0]) - 1;
				return ' ';
			}, $this->rest);
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
					else $this->keys['validPhpVarsLen'] -= strlen($m[0]);
				}
				return '';
			}, $this->rest);
			if ($this->keys['validPhpVarsLen'] > 0) {
				// most variables are php valid, decrease js, just a bit because it could still be a js var
				$this->keys['demerit'] += ceil($this->keys['validPhpVarsLen'] / 2);
			} elseif ($this->keys['validPhpVarsLen'] < 0) {
				// not all variables are php valid, increase js (better against php)
				$this->keys['demerit'] += $this->keys['validPhpVarsLen'];
			}
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
					$len = $i - $start + 1;
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + 1);
					$inString = 0;
					$i -= $len;
					$l -= $len;
					$this->keys['stringsLen'] += $len;
				} elseif ($this->rest[$i] === "\n") {
					return null; // completely cancel after syntax error
				}
			} elseif ($inComment) {
				if (substr($this->rest, $i, strlen($inComment)) == $inComment) {
					// comment finished
					$len = $i - $start + strlen($inComment);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inComment));
					$inComment = 0;
					$i -= $len - (strlen($inComment) - 1);
					$l -= $len;
					$this->keys['commentsLen'] += $len;
				}
			} else {
				$c = $this->rest[$i];
				// strings
				if ($c == '\'' || $c == '"') {
					$inString = $this->rest[$i];
					$start = $i;
				// default single- and multi-line comments
				} elseif (in_array(substr($this->rest, $i, 2), ['/*', '//'])) {
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
