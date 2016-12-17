<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
require_once __DIR__.'/Lang.abstract.php';
class HtmlLang extends Lang {
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
		$this->keys['demerit'] = -2;
		
		$this->keys['stringsLen'] = 0;
		$this->keys['commentsLen'] = 0;
		
		// cancel completely if there is php starting tag
		if (strpos($this->code, '<?') !== false) {
			$this->errors = true;
			return;
		}
		// remove js/php strings and comments
		$this->removeStringsAndComments();
		
		// whitespaces
		$this->rest = preg_replace_callback('~\s+~', function($m) {
			$this->keys['demerit'] += strlen($m[0]) - 1;
			return ' ';
		}, $this->rest);
		// strings and textnodes
		$backupRest = $this->rest;
		$this->rest = preg_replace_callback('~>[^><]+<~', function($m) {
			$this->keys['demerit'] += strlen($m[0]) - 2;
			return '><';
		}, $this->rest);
		$this->rest = preg_replace('~("|\')[^\1]\1~', '', $this->rest);
		// doctype, comments, xml
		$this->rest = preg_replace('~<\!\-\-.*?\-\->~', '', $this->rest);
		$this->rest = preg_replace('~<\!.*?>~', '', $this->rest);
		$this->rest = preg_replace('~<\?xml .*?\?>~', '', $this->rest);
		// inline stuff
		$this->rest = preg_replace_callback('~<(script|style|svg)(.*?)>.*?</\1>~', function($m) {
			$this->keys['keywords']++;
			$this->keys['keywordsLen'] += (strlen($m[1]) + 2) * 2 + 1 + strlen($m[2]);
			return '';
		}, $this->rest);
		// tags
		$tags = $this->tags();
		foreach ($tags as $t) {
			$this->rest = preg_replace_callback('~</?'.$t.'( [^>]*)?>~', function($m) {
				$this->keys['keywords']++;
				$this->keys['keywordsLen'] += strlen($m[0]);
				return '';
			}, $this->rest);
		}
		// remove whitespace
		$this->rest = preg_replace_callback('~\s+~', function($m) {
			$this->keys['demerit'] += strlen($m[0]);
			return '';
		}, $this->rest);
	}
	
	/**
	 * @see Lang.abstract.php -> demerit()
	 */
	public function demerit() {
		return $this->keys['stringsLen'] + $this->keys['commentsLen'] + $this->keys['demerit'];
	}
	
	/**
	 * remove strings and comments just to be sure it's not a php or js file with much html
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
				if ($this->rest[$i] == $inComment[0] && (strlen($inComment) == 1 || strlen($inComment) == 2 && $this->rest[$i + 1] == $inComment[1])) {
					// comment finished
					$len = $i - $start + strlen($inComment);
					$this->rest = substr($this->rest, 0, $start).substr($this->rest, $i + strlen($inComment));
					$inComment = 0;
					$i -= $len - (strlen($inComment) - 1);
					$l -= $len;
					$this->keys['commentsLen'] += $len;
				}
			} else {
				if ($this->rest[$i] == '\'' || $this->rest[$i] == '"') {
					$inString = $this->rest[$i];
					$start = $i;
				} elseif ($this->rest[$i] == '#' || $this->rest[$i] == '/' && ($this->rest[$i + 1] == '*' || $this->rest[$i + 1] == '/')) {
					$inComment = $this->rest[$i] == '/' && $this->rest[$i + 1] == '*' ? '*/' : "\n";
					$start = $i;
					if ($this->rest[$i] !== '#') $i++;
				}
			}
		}
		return $this->rest;
	}
	
	/**
	 * html tags
	 */
	private function tags() {
		return [
			'a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio', 'b', 'base',
			'basefont', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'center',
			'cite', 'code', 'col', 'colgroup', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div',
			'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame',
			'frameset', 'head', 'header', 'hr', 'html', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'keygen',
			'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'menu', 'menuitem', 'meta', 'meter', 'nav',
			'noframes', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'pre', 'progress',
			'q', 'rp', 'rt', 'ruby', 's', 'samp', 'section', 'select', 'small', 'source', 'span', 'strike',
			'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead',
			'time', 'title', 'tr', 'track', 'tt', 'u', 'ul', 'var', 'video', 'wbr', 'h[1-6]'
		];
	}
}
