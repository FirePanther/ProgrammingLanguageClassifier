# Programming Language Classifier

Identifies the programming language of a full source code or just a snippet.
It returns the guessed file extension, so if your source code has 20% HTML
with 80% CSS and JS it will return HTML.

I wrote it for a syntax highlighter for myself, it's not perfect but I
couldn't find a better solution for the (for me) required Web Languages:
html, css, php, and js

## Sample

```php
<?php
require 'LangDetect.class.php';

$code = 'alert("Hi");';

// you can set the languages like: new LangDetect($code, 'php, js');
$ld = new LangDetect($code);
print_r($ld->getProbabilities(true));
```

## Currently supported languages

I currently support CSS, HTML, JS (especially tested with ECMAscript 5.5),
and PHP (tested with PHP <7).

btw. you could contribute :)

## We did a little contest

That I needed this function wasn't the only reason for writing a script :D  
We did a little contest and developed this for one to two days.

You can find more tricky tests of this contest here: https://su.at/f1

Code by @webfashionist: https://github.com/webfashionist/DevClassify  
Code by @marco-a: will be pushed later  
Code by @DRP96: will be pushed later

# License

## [MIT License](https://su.at/mit)

### Copyright © 2016 Suat Secmen

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
