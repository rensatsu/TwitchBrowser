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

$resVersion = 64;
// $resVersion = time() . "dev";

$clru=urlencode($CONFIG['twitch']['home']);
$need=explode("/", $CONFIG['twitch']['home']);
if (strtolower($_SERVER['HTTP_HOST']) != strtolower($need[2])) {
	header("Location: {$CONFIG['twitch']['home']}");
	exit;
}

$content = '';

if (isset($_REQUEST['code'])){
	$result = check_auth_code($_REQUEST['code']);
	if ($result[0] === TRUE) {
		header("Location: ?");
		exit;
	} else {
		$_SESSION['link_twapi_error'] = $result[1];
		header("Location: ?");
		exit;
	}
}

if (!empty($_SESSION['link_twapi_error'])) {
	$errorblock = $_SESSION['link_twapi_error'];
	$_SESSION['link_twapi_error'] = false;
	unset($_SESSION['link_twapi_error']);
}

if (isset($_POST['logout'])) {
	$result = twitch_logout();
	header("Content-type: application/json");
	echo json_encode($result);
	exit;
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

$userNameField = '';
if ($twitchAuth) {
	$showUserNameAs = htmlspecialchars($twitchAuth);
	$userNameField = "<li><p class='navbar-text'><span class='fa fa-user-circle'></span> {$showUserNameAs}</p></li>";
}

?><!DOCTYPE html>
<html>
	<head>
		<title>Twitch Browser</title>
		<link rel='shortcut icon' href='<?=$static?>/img/twitch-favicon.ico'/>
		<link href='<?=$static?>/bootstrap-3.3/css/bootstrap.min.css?_v=<?=$resVersion?>' rel='stylesheet'>
		<link href='<?=$static?>/node_modules/font-awesome/css/font-awesome.min.css?_v=<?=$resVersion?>' rel='stylesheet'>
		<link href='resources/styles/app.css?_v=<?=$resVersion?>' rel='stylesheet'>
		<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no'>
	</head>
	<body class='twitch-browser'>
		<nav class='navbar navbar-default navbar-fixed-top noselect'>
			<div class='container-fluid'>
				<div class='navbar-header'>
					<button type='button' class='navbar-toggle collapsed' data-toggle='collapse' data-target='#navbar-collapse'>
						<span class='icon-bar'></span>
						<span class='icon-bar'></span>
						<span class='icon-bar'></span>
					</button>
					<a class='navbar-brand ajax' href='?main=1'>Twitch Browser</a>
				</div>
				<div class='collapse navbar-collapse' id='navbar-collapse'>
					<ul class='nav navbar-nav navbar-right'>
						<li>
							<p class='navbar-text'>v0.<?=$resVersion?></p>
						</li>
						<li>
							<a href='javascript:' id='goto-settings'>
								<span class='fa fa-cog'></span>
								Settings
							</a>
						</li>
						<li>
							<a href='javascript:' id='goto-channel'>
								<span class='fa fa-search'></span>
								Search
							</a>
						</li>
						<li class='hide'>
							<a href='javascript:' id='goto-auth'>
								<span class='fa fa-user-circle'></span>
								Authorization
							</a>
						</li>
						<?=$userNameField?>
					</ul>
				</div>
			</div>
		</nav>
		
		<div class='window' id='channel-preview' tabindex='-1'>
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
		
		<div id='goto-win-inner' class='lmd-window'>
			<div class='lmd-inner'>
				<h3 class='noselect'>
					Search
					<button class='btn btn-sm btn-danger pull-right lmd-close'>
						<span class='fa fa-times fa-fw'></span>
					</button>
				</h3>
				<div class='lmd-content'>
					<div>Enter channel title, game title or twitch link:</div>
					<div><input type='text' id='goto-win-prompt' class='form-control' /></div>
					<div>
						<button id='goto-win-confirm' class='btn btn-primary'>
							Go <span class='fa fa-arrow-right'></span>
						</button>
					</div>
					<div id='search-results'></div>
				</div>
			</div>
		</div>
		
		<div id='settings-win-inner' class='lmd-window'>
			<div class='lmd-inner'>
				<h3 class='noselect'>
					Settings
					<button class='btn btn-sm btn-danger pull-right lmd-close'>
						<span class='fa fa-times fa-fw'></span>
					</button>
				</h3>
				<div class='lmd-content'>
					<ul class='nav nav-tabs' id='settings-win-tabs'>
						<li class='active'><a href='#settings-win-tab-app' data-toggle='tab'>Application</a></li>
						<li><a href='#settings-win-tab-sl' data-toggle='tab'>Streamlink</a></li>
					</ul>
					<div class='tab-content'>
						<div class='tab-pane fade in active' role='tabpanel' id='settings-win-tab-app'>
							<div>
								<h4>Preferred Stream Quality</h4>
								<div class='btn-group' data-toggle='buttons' id='quality-selector-container'>
									<label class='btn btn-default'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='audio,mobile,low,medium,high,source'> Audio only
									</label>
									<label class='btn btn-default'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='mobile,low,medium,high,source'> Mobile
									</label>
									<label class='btn btn-default'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='low,medium,high,source'> Low
									</label>
									<label class='btn btn-default'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='medium,high,source'> Medium
									</label>
									<label class='btn btn-default'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='high,source'> High
									</label>
									<label class='btn btn-default active'>
										<input type='radio' name='quality-selector' autocomplete='off' data-value='source' checked> Source
									</label>
								</div>
							</div>
							<div id='settings-form-player'>
								<h4>Player Application</h4>
								<div class='btn-group' data-toggle='buttons' id='player-selector-container'>
									<label class='btn btn-default active'>
										<input type='radio' name='player-selector' autocomplete='off' data-value='inapp' checked> In-app
									</label>
									<label class='btn btn-default'>
										<input type='radio' name='player-selector' autocomplete='off' data-value='streamlink'> Streamlink
									</label>
								</div>
							</div>
							<div>
								<h4>Other Settings</h4>
							</div>
							<div>
								<label class='btn btn-default btn-block text-left'>
									<input type='checkbox' autocomplete='off' id='chat-checkbox' class='hide'>
									<span class='fa fa-square fa-fw' id='chat-checkbox-icon'></span>
									Show Chat window by default
								</label>
							</div>
							<div id='settings-logout'>
								<button class='btn btn-default btn-block text-left' id='goto-logout'>
									<span class='fa fa-sign-out fa-fw'></span>
									Log out
								</button>
							</div>
						</div>
						<div class='tab-pane fade' role='tabpanel' id='settings-win-tab-sl'>
							<div id='settings-form-streamlink-help'>
								<h4>Streamlink Settings</h4>
								<div class='alert alert-info'>
									<span class='fa fa-fw fa-info-circle'></span>
									Streamlink player is not active.
									<div>This option is only available in a launcher.</div>
								</div>
							</div>
							<div id='settings-form-streamlink-python'>
								<h4>Python Location</h4>
								<div>
									<input type='text' placeholder='Path to the Python executable' id='player-sl-python' class='form-control' />
								</div>
							</div>
							<div id='settings-form-streamlink-script'>
								<h4>Streamlink Location</h4>
								<div>
									<input type='text' placeholder='Path to the Streamlink script' id='player-sl-script' class='form-control' />
								</div>
							</div>
							<div id='player-sl-min'>
								<h4>Additional Settings</h4>
								<label class='btn btn-default btn-block text-left'>
									<input type='checkbox' autocomplete='off' id='player-sl-min-checkbox' class='hide'>
									<span class='fa fa-square fa-fw' id='player-sl-min-checkbox-icon'></span>
									Minimize Browser when Streamlink starts
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script>
			var userAuthorized = '<?=$twitchAuth?>';
			if (typeof require !== 'undefined') {
				window.node = {
					available: true,
					require: require,
					ipcRenderer: require('electron').ipcRenderer
				};

				delete window.require;
				delete window.exports;
				delete window.module;
			} else {
				window.node = {
					available: false
				}
			}
		</script>

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