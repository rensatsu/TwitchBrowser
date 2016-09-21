<?
if (empty($_REQUEST['channel']) || empty($_REQUEST['quality'])) {
	echo "No required parameters defined!";
	exit;
}

$channel = htmlspecialchars($_REQUEST['channel']);
$quality = isset($_REQUEST['quality']) ? htmlspecialchars($_REQUEST['quality']) : 'source';
$quality = ($quality == 'source') ? 'chunked' : $quality;
$quality = ($quality == 'audio') ? 'mobile' : $quality;

$ver = 14;
// $ver = time() . "dev";

?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch Player</title>
		<meta charset='utf-8' />
		<meta name='viewport' content='width=device-width, user-scalable=no, initial-scale=1' />
		<link rel='stylesheet' href='resources/styles/player.css?<?=$ver?>' />
	</head>
	<body>
		<div id='channel-player'></div>
		<div id='channel-overlay'></div>
		
		<div id='message'></div>
		
		<div id='controls'>
			<div class='buttons'>
				<button id='btn-play'>&#9658; <u>P</u>lay</button>
				<button id='btn-stop'>&#9632; Sto<u>p</u></button>
				<button id='btn-screen'>&harr; <u>F</u>ullscreen</button>
			</div>
			
			<input id='slider-volume' type='range' min='0' max='1' value='0.5' step='0.01' class='slider' />
		</div>
		<script>
			var App = {
				channel: "<?=$channel?>",
				quality: "<?=$quality?>"
			};
		</script>
		<script src="https://player.twitch.tv/js/embed/v1.js"></script>
		<script src='resources/scripts/player.js?<?=$ver?>'></script>
	</body>
</html>