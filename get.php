<?php

require 'vendor/autoload.php';

// ----------------------------------------------

$url = filter_input(INPUT_GET, 'url');
$next = filter_input(INPUT_GET, 'next') ? true : false;

if(!$url)
{
	exit('Error, URL missing!');
}

// ----------------------------------------------

$html = '';

$client = new GuzzleHttp\Client();
$res = $client->request('GET', $url);

if($res->getStatusCode() != 200)
{
	exit('Error, HTTP code ' . $res->getStatusCode());
}

$html = $res->getBody();

if(!$html)
{
	exit('Error, HTTP return empty body');
}

// ----------------------------------------------

$m = [];

preg_match_all('/label\: \'(360|480)p\'/i', $html, $m);

//print_r($m);
if(!isset($m[1][1]))
{
	exit('Error, Regx not match any thing! [1]');
}

preg_match_all('/source\: "http:\/\/(.*?)"/i', $html, $m);

if(!isset($m[1][2]))
{
	exit('Error, Regx not match any thing! [media]');
}

//print_r($m);
//exit;

// $m[2] -> 480p
$downloadLink = 'http://' . $m[1][2];

preg_match('/<title>(.*?)<\/title>/i', $html, $m);
if(!isset($m[1]))
{
	exit('Error, Regx not match any thing! [title]');
}

$title = $m[1];

// ----------------------------------------------
$m = [];

preg_match('/seriesId="(.*?)" mediaId="(.*?)"/i', $html, $m);
//print_r($m);
if(!empty($m[1]) && !empty($m[2]))
{
	// API
	$apiUrl = 'http://tv.zing.vn/xhr/video/get-video-of-series?seriesId=' . $m[1] . '&mediaId=' . $m[2] . '&itemPerPage=3&type=media&callback=PTStudio';
	$m = preg_replace("/[^(]*\((.*)\)/", "$1", file_get_contents($apiUrl));
	$m = json_decode($m, true);

	//print_r($m);
	//exit;
	
	if(empty($m['html']))
	{
		exit('Error, API return empty data');
	}

	preg_match_all('/itemprop="name" content="(.*?)"/i', $m['html'], $m2);
	//print_r($m2);

	preg_match_all('/href="(.*?)" title/i', $m['html'], $m);
	//print_r($m);
	//exit;

	$found = 0;
	$_url = str_replace('http://tv.zing.vn', '', $url);
	//echo $_url;
	foreach($m[1] as $v)
	{
		if($v == $_url)
		{
			break;
		}

		$found++;
	}

	$found++;
	//echo $found;
	if(!empty($m[1][$found]))
	{
		$title = !empty($m2[1][$found-1]) ? $m2[1][$found-1] : $title;
		$nextUrl = 'http://tv.zing.vn' . $m[1][$found];
	}
}

$old_shell = is_file('zingtv.sh') ? file_get_contents('zingtv.sh') : '';
file_put_contents('zingtv.sh', $old_shell . 'aria2c -x8 --out="' . $title . '.mp4" ' . $downloadLink . PHP_EOL);


?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>ZingTV - Auto get link</title>
</head>
<body>
	<p>
		ZingTV URL: <?php echo $url; ?><br>
		<h2><?php echo $title; ?></h2>
		480p: <a href="<?php echo $downloadLink; ?>"><?php echo $downloadLink; ?></a>
		
		<?php if($next && !empty($nextUrl)): ?>
		<hr>
		Tìm thấy tập tiếp theo, tự chuyển hướng sau 1s...<br>
		Tập tiếp theo: <?php echo htmlspecialchars($nextUrl); ?>...<br>
		Nếu không tự chuyển vui lòng bấm vào <a href="get.php?next=on&url=<?php echo urlencode($nextUrl); ?>">đây</a>.
		
		<pre>
		<?php echo print_r($m2[1], true); ?>
		</pre>
		
		<script>
			setTimeout(function(){
				location.href = 'get.php?next=on&url=<?php echo urlencode($nextUrl); ?>'} , 1000
			);
		</script>
		<?php else: ?>
		<hr>
		<p style="color: red;">Không tìm thấy tập tiếp theo</p>
		<?php endif; ?>
	</p>
</body>
</html>
