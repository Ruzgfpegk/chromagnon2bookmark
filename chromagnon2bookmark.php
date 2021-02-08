<?php
// Chromagnon is a tool to extract data from Chrome's Session files, for instance to recover tabs after a crash
// With this script you can create an importable Bookmarks file out of Chromagnon's output

// First, create a log using https://github.com/JRBANCEL/Chromagnon/tree/SNSS

// Usage: php log2html.php SessionOrTabs.log
// -> produces "SessionOrTabs.html" in the same folder

$log        = $argv[1];
$dest       = preg_replace( '/\.log/', '.html', $log );
$logContent = file_get_contents( $log );

// Uncomment the following if the Chromagnon's STDOUT was written to a file through PowerShell (UTF-16LE output, VS UTF-8 for cmd)
//$logContent = iconv( 'UTF-16LE', 'UTF-8', $logContent );

$lines      = explode( PHP_EOL, $logContent );

$windows = [];

$activeWindow = 1; // Default window number (for Tabs, where no Window number is defined)

foreach ( $lines as $line ) {
	$matches = [];
	
	if ( preg_match( '/^SetTabWindow - Window: (?<window>\d+?), Tab: (?<tab>\d+)$/D', $line, $matches ) ) {
		// "Session" file
		$activeWindow = $matches['window'];
	} elseif ( preg_match( '/^UpdateTabNavigation - Tab: (?<tab>\d+?), Index: (?<index>\d+?), Url: (?<url>.*)$/D', $line, $matches ) ) {
		// "Session" and "Tabs" files
		// We only keep the latest tab URL (no history), so each new line in the "tab" would overwrite the previous
		$windows[ $activeWindow ][ $matches['tab'] ] = $matches['url'];
	}
}


// Preparing output
$destFd = fopen( $dest, 'w' );

// Output callback setup
function write_to_dest( $buffer ) {
	global $destFd;
	fwrite( $destFd, $buffer );
}

// Redirect STDOUT to the file
ob_start( 'write_to_dest' );

// Bookmark HTML format reference:
// https://docs.microsoft.com/en-us/previous-versions/windows/internet-explorer/ie-developer/platform-apis/aa753582(v=vs.85)?redirectedfrom=MSDN

echo <<<HEADER
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<!-- This is an automatically generated file.
It will be read and overwritten.
Do Not Edit! -->
<Title>Bookmarks</Title>
<H1>Bookmarks</H1>
<DL>
HEADER;

foreach ( $windows as $window => $tabs ) {
	echo '<DT><H3 FOLDED ADD_DATE="0">Window ID ' . $window . '</H3>' . PHP_EOL . '<DL><p>' . PHP_EOL;
	
	foreach ( $tabs as $tab => $url ) {
		echo '<DT><A HREF="' . $url . '" ADD_DATE="0" LAST_VISIT="0" LAST_MODIFIED="0">' . $url . '</A>' . PHP_EOL;
	}
	
	echo '</DL><p>' . PHP_EOL;
}

echo '</DL>' . PHP_EOL;

// Redirect end
ob_end_flush();
