<?php
use Zend\Console\ColorInterface;
use Zend\Console\Console;

require_once __DIR__ . '/vendor/autoload.php';

$console = Console::getInstance();

$parameters = getopt('c:p:h::', ['config:', 'abPath:', 'help::']);

switch (true)
{
    case $parameters === false:
    case array_key_exists('c', $parameters) === false && array_key_exists('config', $parameters) === false:
    case array_key_exists('p', $parameters) === false && array_key_exists('abPath', $parameters) === false:
    case array_key_exists('h', $parameters) === true || array_key_exists('help', $parameters) === true:
        echo '
Usage: ' . $console->colorize('php start.php [options]', ColorInterface::LIGHT_GREEN) . '

Options are:
    -c config       Config file in json format
    -p abPath       Path to apache bench binaries
    -h help         Display usage information (this message)
';
        exit(0);
}

$configFile = array_key_exists('c', $parameters) === true ? $parameters['c'] : $parameters['config'];
$abPath = array_key_exists('p', $parameters) === true ? $parameters['p'] : $parameters['abPath'];
$abPathHasDoubleQuotes = $abPath[0] === '"';
$abPath = rtrim(trim($abPath, '"'), '\\/');

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
if (file_exists($abPath) === false)
{
    $console->writeLine('Apache Bench path "' . $abPath . '" does not exits.', ColorInterface::LIGHT_RED);
    exit(1);
}
if (is_dir($abPath) === false)
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

    $cmd = $abPath . DIRECTORY_SEPARATOR;
    $cmd .= isset($benchTest->ssl) === true && $benchTest->ssl === true ? 'abs' : 'ab';
    if ($abPathHasDoubleQuotes === true)
    {
        $cmd = '"' . $cmd . '"';
    }

    $cmd .= ' -n ' . $benchTest->requests;
    $cmd .= ' -c ' . $benchTest->concurrency;
    $cmd .= ' -g ' . $tmpFile;

    if (isset($benchTest->contentType) === true)
    {
        $cmd .= ' -T "' . $benchTest->contentType . '"';
    }

    if (isset($benchTest->postFiles))
    {
        foreach ($benchTest->postFiles as $postFile)
        {
            executeAb($cmd . ' -p "' . $postFile . '" ' . $benchTest->url);
        }
    }
    else
    {
        executeAb($cmd . ' ' . $benchTest->url);
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
        $content = array_splice($content, 1);
    }
    file_put_contents($benchTest->gnuPlotFile, implode('', $content), FILE_APPEND);
}


function executeAb($cmd)
{
    passthru($cmd, $result);

    if ((int)$result !== 0)
    {
        Console::getInstance()->writeLine('Failed ' . $cmd, ColorInterface::LIGHT_RED);
        exit(1);
    }
}

exit(0);