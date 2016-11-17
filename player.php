<?
require('../../scripts/engine/config.php');
if (empty($_REQUEST['channel']) || empty($_REQUEST['quality'])) {
	echo "No required parameters defined!";
	exit;
}

$channel = htmlspecialchars($_REQUEST['channel']);
$quality = isset($_REQUEST['quality']) ? htmlspecialchars($_REQUEST['quality']) : 'source';
$quality = ($quality == 'source') ? 'chunked' : $quality;
$quality = ($quality == 'audio') ? 'mobile' : $quality;

$resVersion = 17;
// $resVersion = time() . "dev";

?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch Player</title>
		<meta charset='utf-8' />
		<meta name='viewport' content='width=device-width, user-scalable=no, initial-scale=1' />
		<link rel='stylesheet' href='resources/styles/player.css?<?=$resVersion?>' />
		<link href='<?=$static?>/node_modules/font-awesome/css/font-awesome.min.css?_v=<?=$resVersion?>' rel='stylesheet'>
	</head>
	<body>
		<div id='channel-player'></div>
		<div id='channel-overlay'></div>
		
		<div id='message'></div>
		
		<div id='controls'>
			<div class='buttons'>
				<button id='btn-play'>
					<span class='fa fa-fw fa-play'></span> <u>P</u>lay
				</button>
				<button id='btn-stop'>
					<span class='fa fa-fw fa-stop'></span> Sto<u>p</u>
				</button>
				<button id='btn-screen'>
					<span class='fa fa-fw fa-arrows-alt'></span> <u>F</u>ullscreen
				</button>
			</div>
			
			<input id='slider-volume' type='range' min='0' max='1' value='0.5' step='0.01' class='slider' />
			
			<div class='buttons heading-buttons'>
				<button id='btn-popout' class='auto-width' title='Open in External Window [o]'>
					<span class='fa fa-fw fa-external-link'></span>
				</button>
				<button id='btn-chat' class='auto-width' title='Toggle Chat [c]'>
					<span class='fa fa-fw fa-comments'></span>
				</button>
				<button id='btn-close' class='auto-width' title='Close Stream [q]'>
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
		<script src="https://player.twitch.tv/js/embed/v1.js"></script>
		<script src='resources/scripts/player.js?<?=$resVersion?>'></script>
	</body>
</html>