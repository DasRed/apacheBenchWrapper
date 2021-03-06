<?php
use Zend\Console\ColorInterface;
use Zend\Console\Console;

require_once __DIR__ . '/vendor/autoload.php';

$console = Console::getInstance();

$parameters = getopt('c:p:h::', [
	'config:',
	'abPath::',
	'help::'
]);

switch (true)
{
	case $parameters === false:
	case array_key_exists('c', $parameters) === false && array_key_exists('config', $parameters) === false:
	case array_key_exists('h', $parameters) === true || array_key_exists('help', $parameters) === true:
		echo '
Usage: ' . $console->colorize('php ' . basename(__FILE__) . ' [options]', ColorInterface::LIGHT_GREEN) . '

Options are:
	-c config       Config file in json format
	-p abPath       Path to apache bench binaries. if not defined it will try to find
	-h help         Display usage information (this message)
';
		exit(0);
}

$configFile = array_key_exists('c', $parameters) === true ? $parameters['c'] : $parameters['config'];
$abPath = '';
$abPathHasDoubleQuotes = false;
if (array_key_exists('p', $parameters) !== false || array_key_exists('abPath', $parameters) !== false)
{
	$abPath = array_key_exists('p', $parameters) === true ? $parameters['p'] : $parameters['abPath'];
	$abPathHasDoubleQuotes = substr($abPath, 0, 1) === '"';
	$abPath = rtrim(trim($abPath, '"'), '\\/') . DIRECTORY_SEPARATOR;
}

if (file_exists($configFile) === false)
{
	$console->writeLine('Config file "' . $configFile . '" does not exists.', ColorInterface::LIGHT_RED);
	exit(1);
}
if (is_readable($configFile) === false)
{
	$console->writeLine('Config file "' . $configFile . '" is not readable.', ColorInterface::LIGHT_RED);
	exit(1);
}
if ($abPath !== '' && file_exists($abPath) === false)
{
	$console->writeLine('Apache Bench path "' . $abPath . '" does not exits.', ColorInterface::LIGHT_RED);
	exit(1);
}
if ($abPath !== '' && is_dir($abPath) === false)
{
	$console->writeLine('Apache Bench path "' . $abPath . '" is not a directory.', ColorInterface::LIGHT_RED);
	exit(1);
}

// load the config
$config = json_decode(file_get_contents($configFile));

foreach ($config as $benchTest)
{
	$tmpFile = tempnam(sys_get_temp_dir(), 'abw');
	$console->writeLine('Starting bench: ' . $console->colorize($benchTest->name, ColorInterface::LIGHT_GREEN) . ' ... ');

	$cmd = '';
	$cmd .= $abPath;
	$cmd .= isset($benchTest->ssl) === true && $benchTest->ssl === true ? 'abs' : 'ab';
	if ($abPathHasDoubleQuotes === true)
	{
		$cmd = '"' . $cmd . '"';
	}

	$cmd .= ' -n ' . $benchTest->requests;
	$cmd .= ' -c ' . $benchTest->concurrency;
	$cmd .= ' -g ' . $tmpFile;

	$timeout = 300;
	if (isset($benchTest->timeout) === true)
	{
		$timeout = $benchTest->timeout;
	}
	$cmd .= ' -s ' . $timeout;

	if (isset($benchTest->httpAuth) && isset($benchTest->httpAuth->username) && isset($benchTest->httpAuth->password))
	{
		$cmd .= ' -A "' . $benchTest->httpAuth->username . ':' . $benchTest->httpAuth->password . '"';
	}

	if (isset($benchTest->contentType) === true)
	{
		$cmd .= ' -T "' . $benchTest->contentType . '"';
	}

	if (isset($benchTest->postFiles))
	{
		foreach ($benchTest->postFiles as $postFile)
		{
			executeAb($cmd . ' -p "' . $postFile . '" "' . $benchTest->url . '"');
		}
	}
	else
	{
		executeAb($cmd . ' "' . $benchTest->url . '"');
	}

	// write gnupolotfile
	if (file_exists($tmpFile) === false)
	{
		continue;
	}

	$content = file($tmpFile);
	unlink($tmpFile);
	if (file_exists($benchTest->gnuPlotFile) === true)
	{
		if (isset($benchTest->deleteGnuPlotFile) && $benchTest->deleteGnuPlotFile === true)
		{
			unlink($benchTest->gnuPlotFile);
		}
		else
		{
			$content = array_splice($content, 1);
		}
	}
	file_put_contents($benchTest->gnuPlotFile, implode('', $content), FILE_APPEND);
}

function executeAb($cmd)
{
	$output = '';
	$result = null;

	exec($cmd, $output, $result);

	if ((int)$result !== 0)
	{
		Console::getInstance()->writeLine('Failed ' . $cmd, ColorInterface::LIGHT_RED);
		exit(1);
	}
}

exit(0);