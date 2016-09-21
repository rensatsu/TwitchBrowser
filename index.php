<?
require('../../scripts/engine/config.php');
require('../../scripts/engine/startsession.php');
require('../../scripts/engine/functions.php');
require('config.php');
require('twitch_functions.php');
header('Content-type: text/html; charset=utf-8');

if (isset($_GET['_key'])){
	$res = array();
	foreach ($_GET as $k => $v) {
		if ($k == '_key') continue;
		$res[] = "{$k}={$v}";
	}
	$res = implode("&", $res);
	$res = strlen($res) > 0 ? "?{$res}" : '';

	header("Location: {$res}");
	exit;
}

$resVersion = 33;
$resVersion = time() . "dev";

$clru=urlencode($CONFIG['twitch']['home']);
$need=explode("/", $CONFIG['twitch']['home']);
if (strtolower($_SERVER['HTTP_HOST'])!=mb_strtolower($need[2])){
	header("Location: {$CONFIG['twitch']['home']}");
	exit;
}

$content='';

if (isset($_REQUEST['code'])){
	$result = check_auth_code($_REQUEST['code']);
	if ($result[0] === TRUE) {
		header("Location: ?");
		exit;
	} else {
		$errorblock = $result[1];
	}
}

if (isset($_REQUEST['game'])){
	if (isset($_REQUEST['offset'])){
		$mainblock=get_game_streams($_REQUEST['game'], $CONFIG['counts']['streams'], $_REQUEST['offset']);
	} else {
		$mainblock=get_game_streams($_REQUEST['game'], $CONFIG['counts']['streams']);
	}
} else if (isset($_REQUEST['main']) && isset($_REQUEST['offset'])){
	$mainblock=get_top_games(false, isset($_REQUEST['onlyGames']), $CONFIG['counts']['games'], $_REQUEST['offset']);
} else if (isset($_REQUEST['channel'])){
	$mainblock=get_channel_info($_REQUEST['channel']);
} else if (isset($_REQUEST['favourites'])){
	$mainblock=get_favourites(true);
} else if (isset($_REQUEST['auth'])){
	$mainblock = '';
	$url = get_auth_uri();
	header("Location: {$url}");
	exit;
} else if (isset($_POST['search'])){
	$mainblock = '';
	search_items($_POST['search']);
	exit;
} else {
	if (isset($_REQUEST['ajax'])) {
		$mainblock = get_top_games(false, isset($_REQUEST['onlyGames']), $CONFIG['counts']['games']);
	} else {
		$mainblock = get_top_games(true, isset($_REQUEST['onlyGames']));
	}
}

if (!isset($_REQUEST['ajax'])){
$sideblock=get_favourites();
$errorblock=(isset($errorblock)) ? msg('error', $errorblock) : '';
$admin=isLoggedIn();
$twitchAuth = check_auth();

?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch Browser</title>
		<link rel='shortcut icon' href='<?=$static?>/img/twitch-favicon.ico'/>
		<link href='<?=$static?>/bootstrap-3.3/css/bootstrap.min.css' rel='stylesheet'>
		<link href='resources/styles/app.css?_v=<?=$resVersion?>' rel='stylesheet'>
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no'>
	</head>
	<body class='twitch-browser'>
		<div class='window' id='channel-preview' tabindex='-1'>
			<div class='window-heading btn-toolbar'>
				<button class='btn btn-danger pull-right window-heading-close' type='button' title='Close'>
					<span class='glyphicon glyphicon-remove'></span>
				</button>
				<button class='btn btn-primary pull-right window-heading-chat' type='button' title='Toggle Chat'>
					<span class='glyphicon glyphicon-comment'></span>
				</button>
				<button class='btn btn-primary pull-right window-heading-stream' type='button' title='Open in Livestreamer'>
					<span class='glyphicon glyphicon-expand'></span>
				</button>
				<button class='btn btn-primary pull-right window-heading-popout' type='button' title='Open in New Window'>
					<span class='glyphicon glyphicon-film'></span>
				</button>
			</div>
			<div class='window-sidebar' id='channel-preview-chat'></div>
			<div class='window-body' id='channel-preview-body'></div>
		</div>
		
		<div id='page'>
			<!-- Error message -->
			<?=$errorblock?>
			<!-- / -->
			
			<!-- Content block -->
			<?=$mainblock?>
			<!-- / -->
		</div>
		<div id='page-sidebar'>
			<!-- Favorites block -->
			<?=$sideblock?>
			<!-- / -->
		</div>

		<div id='notifications' class='noselect'></div>
		<div id='bottom-bar' class='noselect'>
			<span class='dropup' id='quality-selector'>
				<button class='btn btn-xs btn-default bottom-bar-item dropdown-toggle' data-toggle='dropdown' type='button' title='Quality'>
					<span class='glyphicon glyphicon-signal'></span>
				</button>
				<ul class='dropdown-menu'>
					<li class='dropdown-header'>Preferred quality</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='audio,mobile,low,medium,high,source'>Audio only</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='mobile,low,medium,high,source'>Mobile</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='low,medium,high,source'>Low</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='medium,high,source'>Medium</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='high,source'>High</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='quality-selector' data-value='source'>Source</a>
					</li>
				</ul>
			</span>
			<span class='dropup' id='player-selector'>
				<button class='btn btn-xs btn-default bottom-bar-item dropdown-toggle' data-toggle='dropdown' type='button' title='Player'>
					<span class='glyphicon glyphicon-cog'></span>
				</button>
				<ul class='dropdown-menu'>
					<li class='dropdown-header'>Player Application</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='player-selector' data-value='inapp'>In-app</a>
					</li>
					<li class='dropdown-item'>
						<a href='javascript:' class='player-selector' data-value='livestreamer'>Livestreamer</a>
					</li>
				</ul>
			</span>
			<button class='btn btn-xs btn-default bottom-bar-item' type='button' id='goto-channel' title='Search game or channel'>
				<span class='glyphicon glyphicon-search'></span>
			</button>
			<button class='btn btn-xs btn-primary bottom-bar-item hide' type='button' id='goto-auth' title='Authorize'>
				<span class='glyphicon glyphicon-user'></span>
			</button>
			<span class='copy bottom-bar-item'>&copy; LinkSoft <? echo date('Y'); ?> / v0.<?=$resVersion?></span>
		</div>
		
		<div id='goto-win-backdrop'></div>
		<div id='goto-win-inner'>
			<h3>Search</h3>
			<div>Enter search query or twitch link:</div>
			<div><input type='text' id='goto-win-prompt' /></div>
			<div>
				<button id='goto-win-confirm' class='btn btn-primary'>
					<span class='glyphicon glyphicon-chevron-right'></span> Go
				</button>
			</div>
			<div id='search-results'></div>
		</div>
		
		<script>var userAuthorized = '<?=$twitchAuth?>';</script>
		<script src='<?=$static?>/node_modules/jquery/dist/jquery.min.js'></script>
		<script src='<?=$static?>/bootstrap-3.3/js/bootstrap.min.js'></script>
		<script src='resources/scripts/scripts.js?_v=<?=$resVersion?>'></script>
		
		<!-- Google Analytics -->
		<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

			ga('create', 'UA-9210291-4', 'auto');
			ga('send', 'pageview');
		</script>
		<!-- END: Google Analytics -->
		
		<!-- Piwik -->
		<script type="text/javascript">
		  var _paq = _paq || [];
		  _paq.push(["setDomains", ["*.ptdev.pw"]]);
		  _paq.push(['trackPageView']);
		  _paq.push(['enableLinkTracking']);
		  (function() {
			var u="https://stats.ptdev.pw/";
			_paq.push(['setTrackerUrl', u+'piwik.php']);
			_paq.push(['setSiteId', 1]);
			var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
			g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
		  })();
		</script>
		<noscript><p><img src="https://stats.ptdev.pw/piwik.php?idsite=1" style="border:0;" alt="" /></p></noscript>
		<!-- End Piwik Code -->
	</body>
</html><?
} else {
	echo $mainblock;
}
?>