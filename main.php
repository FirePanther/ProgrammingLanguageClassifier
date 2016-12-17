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
	
	echo 'checking '.$name.PHP_EOL;
	
	$ld = new LangDetect(file_get_contents($file));
	print_r($ld->getProbabilities(true));
	echo PHP_EOL;
}
