<?php
/**
 * @author           Suat Secmen (https://su.at)
 * @copyright        2016 Suat Secmen
 * @license          MIT License <https://su.at/mit>
 */
require 'LangDetect.class.php';

$files = glob('test/*');

$num = count($files);
if (!$num) die('No files in test/'.PHP_EOL);

echo "This can take a while\nchecking ".$num.' file'.($num == 1 ? '' : 's')."\n";

foreach ($files as $file) {
	$name = basename($file);
	if ($name[0] == '.' || !is_readable($file)) continue;
	
	echo "checking \033[0;35m$name\033[0m".PHP_EOL;
	
	$ld = new LangDetect(file_get_contents($file));
	$prob = $ld->getProbabilities(true);
	print_r($prob);
	
	$top = array_shift(array_keys($prob));
	
	$fileExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	
	if ($fileExtension !== $top) {
		echo "\033[0;31mfailed\033[0m".PHP_EOL.PHP_EOL;
	} else {
		echo "\033[0;32mcorrect\033[0m".PHP_EOL.PHP_EOL;
	}
}
