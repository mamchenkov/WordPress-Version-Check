#!/usr/bin/php
<?php
/**
 * This script expects a single parameter with a filesystem
 * path, where it will look for /wp-includes/version.php files,
 * which usually belong to WordPress.
 * 
 * For each found WordPress installation it will perform the check
 * of installed version versus the official latest version from
 * WordPress.org
 * 
 * A list of checked sites will be printed out in the end, with
 * both OK and NOK results and some stats.
 * 
 * NOTE: The script is purely informational, it will not take any action.
 * 
 * @author Leonid Mamchenkov <leonid@exwebris.com>
 */

/**
 * URL of the WordPress.org API for version check
 */
define('WP_API_URL', 'http://api.wordpress.org/core/version-check/1.6/');
/**
 * Regex for WordPress version file name
 */
define('WP_VERSION_REGEX', '#/wp-includes/version.php$#');

$targetFolder = empty($argv[1]) ? '' : $argv[1];

if (empty($targetFolder)) {
	die("Usage: $argv[0] /path/to/folder\n");
}

$latestWordPressVersion = getLatestVersion();
if (empty($latestWordPressVersion)) {
	die("Failed to fetch latest version. Try again later.\n");
}

print "Latest WordPress version: $latestWordPressVersion\n";

$files = findVersionFiles($targetFolder);
if (empty($files)) {
	print "Did not find any WordPress version files in $targetFolder\n";
	exit;
}

$stats = array();
$stats['OK'] = 0;
$stats['NOK'] = 0;
foreach ($files as $file) {
	$installationLocation = getInstallationLocation($file);
	$installedVersion = getInstalledVersion($file);
	
	if ($installedVersion == $latestWordPressVersion) {
		print "OK : $installationLocation ($installedVersion)\n";
		$stats['OK']++;
	}
	else {
		print "NOK : $installationLocation ($installedVersion)\n";
		$stats['NOK']++;
	}
}
printStats($stats);

/**
 * Recursively search through a given directory to find all
 * WordPress version files.
 * 
 * @param string $dir Path to the top level foler
 * @return array List of found files
 */
function findVersionFiles($dir) {
	$result = array();

	$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
	foreach($objects as $name => $object){
		if (preg_match(WP_VERSION_REGEX, $object->getPathname())) {
			$result[] = $object->getPathname();
		}
	}
	asort($result);

	return $result;
}

/**
 * Find WordPress installation location from version file
 * 
 * This simply chops off /wp-includes/version.php from the end of
 * the version file location.
 * 
 * @param string $file Location of the version file
 * @return string
 */
function getInstallationLocation($file) {
	$result = preg_replace(WP_VERSION_REGEX, '', $file);
	return $result;
}

/**
 * Fetch the information about latest version from WordPress.org
 * 
 * @return string latest WordPress version
 */
function getLatestVersion() {
	$result = '';

	$curl = curl_init(WP_API_URL);
	curl_setopt($curl, CURLOPT_FAILONERROR, true); 
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
	$response = curl_exec($curl);

	if (!empty($response)) {
		$response = unserialize($response);
	}

	if (!empty($response['offers'][0]['current'])) {
		$result = $response['offers'][0]['current'];
	}

	return $result;
}

/**
 * Figure out installed WordPress version from the file
 * 
 * @param string $file WordPress version file
 * @return string
 */
function getInstalledVersion($file) {
	$result = '';

	unset($wp_version);
	require_once $file;
	if (isset($wp_version)) {
		$result = $wp_version;
	}

	return $result;
}

/**
 * Print some stats
 * 
 * @param array $stats Stats data
 * @return void
 */
function printStats($stats) {
	$total = $stats['OK'] + $stats['NOK'];
	$health = $stats['OK'] * 100 / $total;
	print "Stats: checked a total of $total installations. ";
	print $stats['OK'] . " are OK.";
	print $stats['NOK'] . " are not OK.";
	print "Health: $health%";
	print "\n";
}

?>
