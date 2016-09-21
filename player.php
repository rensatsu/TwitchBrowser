<?
require('../../scripts/engine/config.php');
require('../../scripts/engine/startsession.php');
require('../../scripts/engine/functions.php');
require('twitch_functions.php');
header('Content-type: text/html; charset=utf-8');

if (!isset($_REQUEST['channel'])) {
	header("HTTP/1.1 404 Not Found");
	echo "Channel is not defined!";
	echo "<!-- " . str_pad("", 1024, "#") . " -->";
	exit;
}

$channel = htmlspecialchars($_REQUEST['channel']);
$quality = isset($_REQUEST['quality']) ? htmlspecialchars($_REQUEST['quality']) : 'source';
$quality = ($quality == 'source') ? 'chunked' : $quality;
$quality = ($quality == 'audio') ? 'audio_only' : $quality;

if (isset($_REQUEST['m3u8'])) {
	$sigRequest = get("http://api.twitch.tv/api/channels/{$channel}/access_token");
	// $sigRequest = twget("/channels/{$channel}/access_token", false);
	$sigRequest = json_decode($sigRequest);

	if ($sigRequest === FALSE || !(isset($sigRequest->sig) && isset($sigRequest->token))) {
		header("HTTP/1.1 404 Not Found");
		echo "Error requesting playlist data!\n";
		var_dump($sigRequest);
		echo "\n<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}

	$sig = $sigRequest->sig;
	$token = $sigRequest->token;
	$random = mt_rand(10000, 99999);

	$listRequest = get("http://usher.twitch.tv/api/channel/hls/{$channel}.m3u8?player=twitchweb&allow_audio_only=true&allow_source=true&token={$token}&sig={$sig}&type=any&p={$random}");

	if ($listRequest === FALSE) {
		header("HTTP/1.1 404 Not Found");
		echo "Error requesting playlist! Channel may be offline!";
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}
	
	// var_dump($listRequest); exit;
	// header("Content-type: application/vnd.apple.mpegurl"); echo $listRequest; exit;
	
	$lines = explode("\n", $listRequest);
	// header("Content-type: text/plain"); var_dump($listRequest); exit;
	$streamUrl = false;
	for ($i = 0; $i < count($lines); $i++) {
		$line = $lines[$i];
		if (
			strpos($line, "#EXT-X-STREAM-INF") === 0 && (
				stripos($line, $quality) !== FALSE || checkNewQuality($line, $quality)
			)
		) {
			$streamUrl = $lines[$i+1];
			break;
		}
	}
	
	if ($streamUrl === FALSE) {
		header("HTTP/1.1 404 Not Found");
		echo "Error requesting playlist! Channel may be offline!";
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}
	
	/*
	http://video44.ams01.hls.ttvnw.net/hls50/xsmak_19181778176_390237201/chunked/py-index-live.m3u8?token=id=5410591941000316888,bid=19181778176,exp=1454013112,node=video44-1.ams01.hls.justin.tv,nname=video44.ams01,fmt=chunked&sig=9d8542e0c3cbd930e5931f3d6e2dd1b59a2d7891
	
	*/
	
	// header("Location: {$streamUrl}"); exit;
	
	$playlist = get($streamUrl);
	if ($playlist === FALSE) {
		header("HTTP/1.1 404 Not Found");
		echo "Error downloading playlist!";
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}
	
	$baseUrl = preg_replace("#\?(.*)#", "", $streamUrl);
	$baseUrl = str_replace("index-live.m3u8", "", $baseUrl);
	
	$playlist = preg_replace_callback(
		"#index-([0-9a-zA-Z\-\.]*)#",
		function($matches) use ($baseUrl, $channel) {
			return "player.php?channel={$channel}&proxy=" . base64_encode($baseUrl . "index-" . $matches[1]);
		},
		$playlist
	);
	
	if (isset($_REQUEST['type'])) {
		header("Content-type: {$_REQUEST['type']}");
	} else {
		header("Content-type: text/plain");
	}
	
	// $playlist = str_replace("#EXTM3U\n", "#EXTM3U\n#EXT-X-STREAMURL:{$streamUrl}\n", $playlist);
	echo $playlist;
} else if (isset($_REQUEST['proxy'])) {
	$link = base64_decode($_REQUEST['proxy']);
	$content = get($link);
	if ($content !== FALSE) {
		// echo $link . "\n";
		echo $content;
		exit;
	} else {
		header("HTTP/1.1 404 Not Found");
		echo "Unable to download fragment!<br>\n" . $link;
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}
} else {
	$ver = 7;
	// $ver = "t_" . time();
?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch HLS</title>
		<meta charset='utf-8' />
		<meta name='viewport' content='width=device-width, user-scalable=no, initial-scale=1' />
		<link rel='stylesheet' href='resources/styles/player.css?<?=$ver?>' />
	</head>
	<body>
		<video width='640' height='400' id='video' autoplay></video>
		
		<div id='message'></div>
		
		<div id='controls'>
			<div class='buttons'>
				<button id='btn-play'>&#9658; <u>P</u>lay</button>
				<button id='btn-stop'>&#9632; Sto<u>p</u></button>
				<button id='btn-screen'>&harr; <u>F</u>ullscreen</button>
			</div>
			<!-- <span class='spacer'>#</span> -->
			
			<input id='slider-volume' type='range' min='0' max='1' value='0.5' step='0.01' class='slider' />
		</div>

		<script>
			var App = {
				channel: "<?=$channel?>",
				quality: "<?=$quality?>"
			};
		</script>
		<script src='resources/scripts/hls.min.js'></script>
		<script src='resources/scripts/player.js?<?=$ver?>'></script>
	</body>
</html>
<?
}
?>