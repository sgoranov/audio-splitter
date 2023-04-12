# mp3split

This is a PHP script which wraps ffmpeg used to split large mp3/audio files to small chunks, defined 
in track list file. You can specify the format of the track list and name of the output files using command line
arguments.

## Requirements

 - [PHP 7.1 or greater](https://www.php.net/)
 - [ffmpeg](https://ffmpeg.org/)

## Install

Download the script or clone the repo:

```bash
git clone git@github.com:sgoranov/mp3split.git
```

Make sure the script is executable:

```bash
chmod +x /path/to/mp3split.php
```

## Usage

Before start using the script you have to define where each track starts. This can be done with `--track-list-format` option
using parameters. For example the following track list defines the starting of the track just after the
track number and before the name of the track.

```text
01. 00:00 - Track 1
02. 04:20 - Track 2
03. 07:56 - Track 3
04. 12:50 - Track 4
05. 16:17 - Track 5
```

It's appropriate to map also the track number and the track name. So to handle this track list 
you can define the `--track-list-format` as `%seq%. %from% - %title%`. So the command to execute will look 
like this one below:

```bash
/path/to/mp3split.php --file /path/to/file.mp3 --track-list /path/to/list.txt --track-list-format "%seq%. %from% - %title%" --output-dir /path/to/output_dir/
```

The script will parse the track list then it will use the `%from%` parameter to determine where each 
track starts to split the input file on smaller chunks. It will save the tracks in `/path/to/output_dir`
and will automatically generate the name using the `%seq%` parameter.
If you want to name the output files differently then you can use the `--output-format` option.
For example adding `--output-format "%seq% - %title%.mp3"` to the command above will generate 
names like `01 - Track 1.mp3`, `02. Track 2.mp3` etc.

In conclusion keep in mind that you must pass `--track-list-format` and the `%from%` 
parameter is mandatory. The `%seq%` parameter always exists, even if it's not specified
and can be used with `--output-format` if needed. The `%to%` parameter is optional and if
it's not passed then we'll use the %from% from the next track to determine where to cut the current one.
You can define as many parameters as you wish and use them or not with `--output-format`.
