#!/usr/bin/php
<?php
const DEFAULT_TIME_SEPARATOR = ':';
CONST DEFAULT_OUTPUT_FORMAT = '%seq%.mp3';

main();

function main(): void {

    if (count($GLOBALS['argv']) === 1) {
        usage();
        exit();
    }

    validateCommand('ffmpeg');

    $file = getRequiredCommandArg('--file');
    validateFile($file);

    $trackList = getRequiredCommandArg('--track-list');
    validateFile($trackList);

    $outputDir = getRequiredCommandArg('--output-dir');
    validateDir($outputDir);

    $trackListFormat = getRequiredCommandArg('--track-list-format');
    validateTrackListFormat($trackListFormat);

    $outputFormat = getCommandArg('--output-format');
    if (is_null($outputFormat)) {
        $outputFormat = DEFAULT_OUTPUT_FORMAT;
    }

    $timeSeparator = getCommandArg('--time-separator');
    if (is_null($timeSeparator)) {
        $timeSeparator = DEFAULT_TIME_SEPARATOR;
    }

    $data = parseTrackList($trackList, $trackListFormat);

    foreach ($data as $item) {

        $from = formatTime($item['%from%'], $timeSeparator);
        $outputFile = $outputDir . '/' . buildOutputFileName($outputFormat, $item);

        if (isset($item['%to%'])) {
            $to = formatTime($item['%to%'], $timeSeparator);
            $cmd = sprintf('ffmpeg -i %s -c copy -ss %s -to %s %s', escapeshellarg($file), $from, $to, escapeshellarg($outputFile));
        } else {
            $cmd = sprintf('ffmpeg -i %s -c copy -ss %s %s', escapeshellarg($file), $from, escapeshellarg($outputFile));
        }

        exec($cmd, $output, $code);
    }

    echo "Done!\n\n";
}

function usage(): void {

    echo <<<EOF
Usage: /path/to/mp3split.php --file file.mp3 --track-list list.txt --track-list-format "%number%. %from% - %title%" --output-dir output/

Required:
--file Path to the mp3 file which you want to split
--track-list Path to track list file
--output-dir Path to output directory where to save the mp3 files
--track-list-format Specify the row format of the track list using parameters. 

The parameter is a string surrounded by "%" character which contains only a-z, A-Z, 0-9 chars inside. You can specify as many 
parameters as you wish and use them or not in --output-format to name the output files. The only one required parameter is 
%from% which is used to determine the start time of each track. As an option you can specify the %to% parameter which tells 
the script where the track ends. If you do not specify the %to% then we'll use the %from% from the next track to determine 
where to cut the current one.
The %seq% parameter always exists, when it's not specified it will be automatically generated and populated with values. 
This parameter can be useful when you want to number tracks in the order they were extracted.

Optional:
--output-format Specify the name for the output files
You can use the same parameters from track-list-format option including the %seq% parameter which always exists even when
it's not specified. The default value of --output-format is "%seq%.mp3".
--time-separator Specify the char used to separate the time. The default value is ":".

EOF;
    exit();
}

function validateCommand(string $command): void {

    $windows = strpos(PHP_OS, 'WIN') === 0;
    $test = $windows ? 'where' : 'command -v';

    if (!is_executable(trim(shell_exec("$test $command")))) {
        echo sprintf("Error: Command %s not found\n", $command);
        exit();
    }
}

function getCommandArg(string $param): ?string {

    $argv = $GLOBALS['argv'];

    $key = array_search($param, $argv, true);
    if (!$key || !isset($argv[$key + 1])) {
        return null;
    }

    return $argv[$key + 1];
}

function getRequiredCommandArg(string $param): string {

    $value = getCommandArg($param);
    if (is_null($value)) {
        echo sprintf("Error: Parameter %s is required\n\n", $param);
        usage();
    }

    return $value;
}

function validateFile(string $file): void {

    if (!is_file($file) || !is_readable($file)) {
        echo sprintf("Error: Can't read %s\n", $file);
        exit();
    }
}

function validateDir(string $dir): void {

    if (!is_dir($dir) || !is_writable($dir)) {
        echo sprintf("Error: Can't access %s\n", $dir);
        exit();
    }
}

function validateTrackListFormat(string $format) {

    // get all valid params
    $validParams = getValidParams($format);

    if (count($validParams) === 0 || !in_array('%from%', $validParams)) {
        echo "Error: You must specify %from% in --track-list-format\n";
        exit();
    }

    // check for duplicates
    if (count($validParams) > count(array_unique($validParams))) {
        echo "Error: There are duplicate parameters in --track-list-format\n";
        exit();
    }
}

function getValidParams(string $format): array {

    preg_match_all('/%[a-zA-Z0-9]+%/', $format, $matches);

    return $matches[0];
}

function parseTrackList(string $pathToTrackListFile, string $format): array {

    list($pattern, $params) = buildRegexPattern($format);

    $data = [];
    $fp = fopen($pathToTrackListFile, "r");
    while (($buffer = fgets($fp, 4096)) !== false) {

        $row = parseTrackListLine(trim($buffer), $pattern, $params);
        $data[] = $row;

        $index = count($data) - 1;
        $previousIndex = $index - 1;
        $data[$index]['%seq%'] = $index + 1;

        // check if %to% param is missing from previous row
        // and use %from% of the current one as a value
        if ($previousIndex >= 0 && !isset($data[$previousIndex]['%to%'])) {
            $data[$previousIndex]['%to%'] = $data[$index]['%from%'];
        }
    }

    // fix the %seq% prepending with zeroes
    $length = strlen(count($data));
    $data = array_map(function ($item) use ($length) {
        $item['%seq%'] = str_pad($item['%seq%'], $length, "0", STR_PAD_LEFT);
        return $item;
    }, $data);

    if (!feof($fp)) {
        echo "Error: unexpected fgets() fail\n";
        exit();
    }

    fclose($fp);

    return $data;
}

function buildRegexPattern(string $format): array {

    $format = trim($format);
    $format = escapeRegexSpecialChars($format);

    $validParams = getValidParams($format);
    $paramPositions = [];
    foreach ($validParams as $param) {
        $pos = strpos($format, $param);
        if ($pos !== false) {
            $paramPositions[$param] = $pos;
        }
    }
    asort($paramPositions);
    $sortedParams = array_keys($paramPositions);
    $format = str_replace($validParams, '(.*)', $format);
    $format = '/^' . $format . '$/';

    return [$format, $sortedParams];
}

function escapeRegexSpecialChars(string $str): string {

    $special_chars = ['\\', '.', '^', '$', '*', '+', '?', '{', '}', '[', ']', '(', ')', '|'];
    $escaped_chars = array_map(function($char) {
        return '\\' . $char;
    }, $special_chars);

    return str_replace($special_chars, $escaped_chars, $str);
}

function parseTrackListLine(string $line, string $pattern, array $params): array {

    preg_match($pattern, $line, $matches);
    unset($matches[0]);
    $matches = array_values($matches);

    if (count($matches) != count($params)) {
        echo sprintf("Error: Unable to parse the following line from the track list\n%s\n\nPlease provide proper --track-list-format option\n", $line);
        exit();
    }

    return array_combine($params, $matches);
}

function buildOutputFileName(string $format, array $params): string {

    return str_replace(array_keys($params), array_values($params), $format);
}

function formatTime(string $value, string $separator): string {

    $separatedTime = explode($separator, $value);
    while (count($separatedTime) < 3) {
        array_unshift($separatedTime, "00");
    }

    $value = strtotime(implode(':', $separatedTime));

    return date('H:i:s', $value);
}
