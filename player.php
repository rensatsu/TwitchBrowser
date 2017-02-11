<?
require('../../scripts/engine/config.php');
require('../../scripts/engine/startsession.php');
require('../../scripts/engine/functions.php');
require('twitch_functions.php');
if (empty($_REQUEST['channel']) || empty($_REQUEST['quality'])) {
	echo "No required parameters defined!";
	exit;
}

$channel = htmlspecialchars($_REQUEST['channel']);
$quality = isset($_REQUEST['quality']) ? htmlspecialchars($_REQUEST['quality']) : 'source';
$quality = ($quality == 'source') ? 'chunked' : $quality;
$quality = ($quality == 'audio') ? 'mobile' : $quality;

if (isset($_REQUEST['m3u8'])) {
	$sigRequest = twget("http://api.twitch.tv/api/channels/{$channel}/access_token");
	// $sigRequest = twget("/channels/{$channel}/access_token", false);

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
		echo "Error requesting playlist! Channel may be offline or requested quality is unavailable!";
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}

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
			return "player.php?channel={$channel}&quality=proxy&proxy=" . base64_encode($baseUrl . "index-" . $matches[1]);
		},
		$playlist
	);
	
	if (isset($_REQUEST['type'])) {
		header("Content-type: {$_REQUEST['type']}");
	} else {
		header("Content-type: text/plain");
	}
	
	echo $playlist;
	exit;
} else if (isset($_REQUEST['proxy'])) {
	$link = base64_decode($_REQUEST['proxy']);
	$content = get($link);
	if ($content !== FALSE) {
		echo $content;
		exit;
	} else {
		header("HTTP/1.1 404 Not Found");
		echo "Unable to download fragment!<br>\n" . $link;
		echo "<!-- " . str_pad("", 1024, "#") . " -->";
		exit;
	}
}

$resVersion = 21;
// $resVersion = time() . "dev";

?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch Player</title>
		<meta charset='utf-8' />
		<meta name='viewport' content='width=device-width, user-scalable=no, initial-scale=1' />
		<link rel='stylesheet' href='resources/styles/player.css?_v=<?=$resVersion?>' />
		<link href='<?=$static?>/node_modules/font-awesome/css/font-awesome.min.css?_v=<?=$resVersion?>' rel='stylesheet'>
	</head>
	<body>
		<div id='channel-player'>
			<video id='video' autoplay></video>
		</div>
		<div id='channel-overlay'></div>
		
		<div id='message'></div>
		
		<div id='controls'>
			<div class='buttons'>
				<button id='btn-play' class='btn'>
					<span class='fa fa-fw fa-play'></span> <u>P</u>lay
				</button>
				<button id='btn-stop' class='btn'>
					<span class='fa fa-fw fa-stop'></span> Sto<u>p</u>
				</button>
				<button id='btn-screen' class='btn'>
					<span class='fa fa-fw fa-arrows-alt'></span> <u>F</u>ullscreen
				</button>
			</div>
			
			<div>
				<button id='btn-mute' class='btn-icon' title='Toggle Mute [m]'>
					<span class='fa-stack fa-lg'>
						<i class='fa fa-volume-off fa-stack-1x'></i>
						<i class='fa fa-ban fa-stack-1x' id='btn-mute-overlay'></i>
					</span>
				</button>
				<input id='slider-volume' type='range' min='0' max='1' value='0.5' step='0.01' class='slider' />
			</div>
			
			<div class='buttons heading-buttons'>
				<button id='btn-popout' class='btn auto-width' title='Open in External Window [o]'>
					<span class='fa fa-fw fa-external-link'></span>
				</button>
				<button id='btn-chat' class='btn auto-width' title='Toggle Chat [c]'>
					<span class='fa fa-fw fa-comments'></span>
				</button>
				<button id='btn-close' class='btn auto-width' title='Close Stream [q]'>
					<span class='fa fa-fw fa-times'></span>
				</button>
			</div>
		</div>
		<script>
			var App = {
				channel: "<?=$channel?>",
				quality: "<?=$quality?>"
			};
		</script>
		
		<script src='resources/scripts/hls.min.js'></script>
		<script src='resources/scripts/player.js?<?=$resVersion?>'></script>
	</body>
</html>