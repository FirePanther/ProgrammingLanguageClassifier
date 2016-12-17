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
		
		// escapes
		$this->rest = preg_replace('~\\\\.~', '', $this->rest);
		// scan the whole string (this was the safest solution)
		if ($this->removeStringsAndComments() !== null) {
			// no html
			$this->rest = preg_replace('~<script[^>]*>.*</script>~is', '', $this->rest);
			// whitespaces
			$this->rest = str_replace(["\r\n", "\r"], "\n", $this->rest);
			$this->rest = preg_replace('~\s+~m', ' ', $this->rest);
			// regex
			$this->rest = preg_replace('~/[^/]+/[gim]+~i', ' ', $this->rest);
			// keywords
			$keywords = $this->keywords();
			$this->keys['keywords'] = 0;
			foreach ($keywords as $k) {
				$this->rest = preg_replace_callback('~\W('.preg_quote($k).')\s*(\([^\)]*\))?\b~', function($m) {
					if (strlen($m[1]) > 4) $this->keys['keywords']++;
					return '';
				}, $this->rest);
			}
			// functions
			$this->rest = preg_replace('~(\W)\w+\([^\)]*\)\s*(;|\{)~i', '', $this->rest);
			// special chars
			$this->rest = preg_replace('~(/|\*|=|;|\(|\)|\[|\]|\{|\}|\&|\|\||,|\?|\:|\!|<|>|\+|\-|\d*\.\d+e?|\d+\.\d*e?|0x\d+|\d+e?)~i', ' ', $this->rest);
			// objects, arrays, variables
			$this->rest = preg_replace('~\bnew \w+~', '', $this->rest);
			$this->rest = preg_replace('~(\)|\]|\})\.~i', '$1 ', $this->rest);
			do {
				$restBackup = $this->rest;
				$this->rest = preg_replace('~\[[^\]]+\]~i', '', $this->rest);
			} while($restBackup !== $this->rest);
			$this->keys['allVarsPhpValid'] = 0; // no variables at all
			$this->rest = preg_replace_callback('~([a-z\$_][a-z0-9\$_]*)(\.[a-z\$_][a-z0-9\$_]*)~i', function($m) {
				if ($this->keys['allVarsPhpValid'] === 0) $this->keys['allVarsPhpValid'] = 1;
				if (strpos($m[0], '(') === false) {
					if (!preg_match('~^\$[a-z_]\w*($|\.)~', $m[0])) { // php invalid
						$this->set('allVarsPhpValid', -1);
					}
				}
				return '';
			}, $this->rest);
			// remove whitespace
			$this->rest = str_replace(' ', '', $this->rest);
		} else {
			$this->errors = true;
		}
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
	private function keywords() {
		return [
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
			
			'window', 'document', 'console',
			
			'alert', 'back', 'blur', 'cancelIdleCallback', 'captureEvents', 'clearImmediate', 'close',
			'confirm', 'disableExternalCapture', 'dispatchEvent', 'dump', 'enableExternalCapture', 'find',
			'focus', 'forward', 'getAttention', 'getAttentionWithCycleCount', 'getComputedStyle',
			'getDefaultComputedStyle', 'getSelection', 'home', 'matchMedia', 'maximize', 'minimize', 'moveBy',
			'moveTo', 'mozRequestAnimationFrame', 'open', 'openDialog', 'postMessage', 'print', 'prompt',
			'releaseEvents', 'removeEventListener', 'requestIdleCallback', 'resizeBy', 'resizeTo', 'restore',
			'routeEvent', 'scroll', 'scrollBy', 'scrollByLines', 'scrollByPages', 'scrollTo', 'setCursor',
			'setImmediate', 'setResizable', 'showModalDialog', 'sizeToContent', 'stop', 'updateCommands',
			'mozRequestAnimationFrame',
			
			'onclick', 'oncontextmenu', 'ondblclick', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove',
			'onmouseover', 'onmouseout', 'onmouseup', 'onkeydown', 'onkeypress', 'onkeyup', 'onabort', 'onbeforeunload',
			'onerror', 'onhashchange', 'onload', 'onpageshow', 'onpagehide', 'onresize', 'onscroll', 'onunload', 'onblur',
			'onchange', 'onfocus', 'onfocusin', 'onfocusout', 'oninput', 'oninvalid', 'onreset', 'onsearch', 'onselect',
			'onsubmit', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop',
			'oncopy', 'oncut', 'onpaste', 'onafterprint', 'onbeforeprint', 'onabort', 'oncanplay', 'oncanplaythrough',
			'ondurationchange', 'onemptied', 'onended', 'onerror', 'onloadeddata', 'onloadedmetadata', 'onloadstart',
			'onpause', 'onplay', 'onplaying', 'onprogress', 'onratechange', 'onseeked', 'onseeking', 'onstalled', 'onsuspend',
			'ontimeupdate', 'onvolumechange', 'onwaiting', 'animationend', 'animationiteration', 'animationstart',
			'transitionend', 'onerror', 'onmessage', 'onopen', 'onmessage', 'onmousewheel', 'ononline', 'onoffline',
			'onpopstate', 'onshow', 'onstorage', 'ontoggle', 'onwheel', 'ontouchcancel', 'ontouchend', 'ontouchmove',
			'ontouchstart', 'CAPTURING_PHASE', 'AT_TARGET', 'BUBBLING_PHASE', 'bubbles', 'cancelable', 'currentTarget',
			'defaultPrevented', 'eventPhase', 'isTrusted', 'target', 'timeStamp', 'type', 'view', 'preventDefault',
			'stopImmediatePropagation', 'stopPropagation'
		];
	}
}
