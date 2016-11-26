<?
function write_cache($id, $value, $ttl){
	link_cache_store('link_twitchbrowser_' . $id, $value, $ttl);
}

function read_cache($id){
	return link_cache_fetch('link_twitchbrowser_' . $id);
}

function check_auth_code($code) {
	global $CONFIG;
	
	$params = array(
		'client_id'     => $CONFIG['twitch']['clid'],
		'client_secret' => $CONFIG['twitch']['clsc'],
		'grant_type'    => 'authorization_code',
		'redirect_uri'  => urldecode($CONFIG['twitch']['home']),
		'code'          => $code,
		'state'         => null
	);
	
	$tmp = twpost('https://api.twitch.tv/kraken/oauth2/token', $params);
	
	if (isset($tmp->access_token)) {
		$_SESSION['link_twapi_token']=$tmp->access_token;
		setcookie('link_twapi_token', $tmp->access_token, time() + 30*24*60*60, '/');
		return array(true, '');
	} else {
		return array(false, "Can't get access_token from Twitch!<br><a href='?auth=1'>Try again</a>");
	}
	
	return array(false, 'Unknown error');
}

function twitch_logout() {
	$_SESSION['link_twapi_token'] = false;
	unset($_SESSION['link_twapi_token']);
	setcookie('link_twapi_token', '', time() - 30*24*60*60, '/');
	
	return array('result' => 'ok');
}

function get_auth_uri() {
	global $CONFIG;
	$clid = $CONFIG['twitch']['clid'];
	$clsc = $CONFIG['twitch']['clsc'];
	$clru = $CONFIG['twitch']['home'];
	return "https://api.twitch.tv/kraken/oauth2/authorize?response_type=code&client_id={$clid}&redirect_uri={$clru}&scope=user_read";
}

function msg($type, $text){
	$type=($type=='error') ? 'danger' : $type;
	return "<div class='alert alert-{$type}'>{$text}</div>";
}

function twpost($url, $data) {
	global $useragent, $CONFIG;
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FAILONERROR, 0); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Accept: application/vnd.twitchtv.v3+json",
			"Client-ID: {$CONFIG['twitch']['clid']}"
		)
	);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	$res = curl_exec($ch);
	curl_close($ch);
	return json_decode($res);
}

function twget($action, $age=300, $err_no_cache=false){
	global $useragent, $CONFIG;
	$url='https://api.twitch.tv/kraken/' . preg_replace('#^(\/)#', '', $action);
	$key=	(isset($_SESSION['link_twapi_token'])) ? $_SESSION['link_twapi_token'] : 
				(isset($_COOKIE['link_twapi_token']) ? $_COOKIE['link_twapi_token'] : false);
				
	// echo "<div class='well well-sm'>GET: {$action} | Key: {$key}</div>";
	
	$skey=($key) ? 'key=' . md5($key) : '';
	$hash=md5('twapi' . $url . $skey);
	$cache=($age) ? read_cache($hash) : false;
	
	if ($cache) {
		return $cache;
	} else if (!$cache && $err_no_cache) {
		return NULL;
	} else {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, 0); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		if ($key) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
														"Accept: application/vnd.twitchtv.v3+json",
														"Client-ID: {$CONFIG['twitch']['clid']}",
														"Authorization: OAuth {$key}"
													));
		} else {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					"Accept: application/vnd.twitchtv.v3+json",
					"Client-ID: {$CONFIG['twitch']['clid']}"
				)
			);
		}
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$res = curl_exec($ch);
		curl_close($ch);
		$jsdata = json_decode($res);
		if ($jsdata !== FALSE) {
			if (!isset($jsdata->error)) {
				write_cache($hash, $jsdata, $age);
			}
			return $jsdata;
		} else {
			return false;
		}
	}
}

function check_auth() {
	$tmp=twget('/user', false);
	$header="<div id='favblock'><div class='panel panel-default'><div class='panel-heading'><a class='btn btn-default btn-xs btn-nav-back-small' href='javascript:' onClick='get_favourites();' title='Refresh'><span class='fa fa-refresh'></span></a> Followed channels</div>";
	$footer="</div></div>";
	return isset($tmp->display_name) ? $tmp->display_name : false;
}

function replace_http($link){
	return preg_replace('@^https?\:\/\/@', 'https://', $link);
}

function get_top_games($onlyPanel = false, $onlyGames = false, $limit=12, $offset=0){
	$offset=intval($offset);
	$limit=intval($limit);
	
	$rnd = mt_rand(1000, 9999);

	if (!$onlyGames) {
		$header = "<div class='panel panel-default'><div class='panel-heading'><a href='?main=1&offset={$offset}&_={$rnd}' class='btn btn-default btn-xs btn-nav-back-small ajax' title='Refresh'><span class='fa fa-refresh'></span></a>Popular games</div><div class='panel-body noselect' id='page-popular-games'>";
		$footer = "</div></div>";
	} else {
		$header = '';
		$footer = '';
	}
	
	if (!$onlyPanel) {	
		if ($offset==0){
			$tmp=twget("/games/top?limit={$limit}");
		} else {
			$tmp=twget("/games/top?limit={$limit}&offset={$offset}");
		}
		
		if (isset($tmp->top)){
			$list='';
			$i=0;
			$list.="<div class='game-container'>";
			foreach($tmp->top as $game){
				if (isset($game->game->name) && trim($game->game->name)!=''){
					$vwr=($game->viewers > 1) ? 'viewers' : 'viewer';
					$chl=($game->channels > 1) ? 'channels' : 'channel';
					$ueg=urlencode($game->game->name);
					$gname=str_replace("'", "&#39;", $game->game->name);
					
					$t_chn=formatNum($game->channels) . ' ' . $chl;
					$t_vwr=formatNum($game->viewers) . ' ' . $vwr;

					$list.="<div class='game-box'>
								<div class='thumbnail'>
									<a href='?game={$ueg}' class='ajax'><img src='" . replace_http($game->game->box->large) . "'></a>
									<div class='caption'>
										<a href='?game={$ueg}' class='game-title ajax' title='{$gname}'>{$gname}</a>
										<p title='Channels'>
											<span class='fa fa-user fa-fw'></span> {$t_chn}
										</p>
										<p title='Viewers'>
											<span class='fa fa-eye fa-fw'></span> {$t_vwr}
										</p>
									</div>
								</div>
							</div>";
					$i++;
				}
				//$list.="</div>";
			}
			$list.="</div>";
			$pager ="<ul class='pager'>";
			$poffset=$offset-$limit;
			$noffset=$offset+$limit;
			$pager.=($offset<=0 || $poffset<0) ? "<li class='previous disabled'><a href='javascript:'>&larr; Previous</a></li>" : "<li class='previous'><a href='?main=1&offset={$poffset}' class='ajax'>&larr; Previous</a></li>";
			$pager.="<li class='next'><a href='?main=1&offset={$noffset}' class='ajax'>Next &rarr;</a></li>";
			$pager.="</ul>";
			$list.=$pager;
			return $header . $list . $footer;
		} else {
			return msg('error', "Can't retrieve top games!");
		}
	} else {
		return $header . $footer;
	}
}

function get_game_streams($game, $limit=12, $offset=0){
	$ueg=urlencode($game);
	$game=htmlspecialchars($game);
	$offset=intval($offset);
	$limit=intval($limit);
	if ($offset==0){
		$tmp=twget("/streams?limit={$limit}&game={$ueg}");
	} else {
		$tmp=twget("/streams?limit={$limit}&offset={$offset}&game={$ueg}");
	}
	//$header="<h2><a href='?main=1' class='glyph-link' title='Back'><span class='fa fa-arrow-left'></span></a> {$game}</h2>";
	$header="<div class='panel panel-default'><div class='panel-heading'><div class='btn-group'><a href='?main=1' class='btn btn-default ajax' title='Back'><span class='fa fa-arrow-left'></span></a><a class='btn btn-default ajax' href='?game={$ueg}'>{$game}</a></div></div><div class='panel-body'>";
	if (isset($tmp->streams)){
		$list = "<div class='game-container'>";
		$i=0;
		foreach($tmp->streams as $stream){
			//var_dump($stream);
			// $list.=($i % 4 == 0) ? (($i==0) ? "<div class='row'>" : "</div><div class='row'>") : '';
			$vwr=($stream->viewers > 1) ? 'viewers' : 'viewer';
			$t_vwr=formatNum($stream->viewers) . ' ' . $vwr;
			$strstatus=isset($stream->channel->status) ? $stream->channel->status : '[ No Title ]';
			$escstatus=str_replace("'", " ", $strstatus);
			// $list.="<div class='col-md-3 col-sm-6 col-xs-6'>
			$list.="<div class='game-box'>
						<div class='thumbnail'>
							<a href='javascript:' onclick='livestreamer(this);' data-live='http://twitch.tv/{$stream->channel->name}' title='Start in Livestreamer'>
								<img src='" . replace_http($stream->preview->medium) . "'>
							</a>
							<div class='caption'>
								<a href='?channel={$stream->channel->name}' class='game-title ajax' title='{$escstatus}'>{$strstatus}</a>
								<p>
									<span class='fa fa-user fa-fw'></span> {$stream->channel->display_name}
								</p>
								<p title='Viewers'>
									<span class='fa fa-eye fa-fw'></span> {$t_vwr}
								</p>
							</div>
						</div>
					</div>";
			$i++;
		}
		
		$list.="</div>";
		$pager ="<ul class='pager'>";
		$poffset=$offset-$limit;
		$noffset=$offset+$limit;
		$pager.=($offset<=0 || $poffset<0) ? "<li class='previous disabled'><a href='javascript:'>&larr; Previous</a></li>" : "<li class='previous'><a href='?game={$ueg}&offset={$poffset}' class='ajax'>&larr; Previous</a></li>";
		$pager.="<li class='next'><a href='?game={$ueg}&offset={$noffset}' class='ajax'>Next &rarr;</a></li>";
		$pager.="</ul>";
		$list.=$pager;
		$footer="</div></div>";
		return $header . $list . $footer;
	} else {
		return msg('error', "Can't retrieve streams!");
	}
}

function search_items($query){
	$q = urlencode($query);
	
	$resultsObject = array(
		'channels' => array(),
		'games' => array(),
		'errors' => array()
	);
	
	$tmp=twget("/search/channels?limit=5&type=suggest&live=true&query={$q}");
	if (isset($tmp->channels)){
		foreach($tmp->channels as $channel){
			$resultsObject['channels'][] = array(
				'status' => $channel->status,
				'display_name' => $channel->display_name,
				'name' => $channel->name,
				'game' => $channel->game,
				'logo' => replace_http($channel->logo),
				'followers' => $channel->followers
			);
		}
	} else {
		$resultsObject['errors'][] = array(
			'text' => 'Unable to get channel list',
			'data' => vardump($tmp)
		);
	}
	
	$tmp=twget("/search/games?limit=5&type=suggest&live=true&query={$q}");
	if (isset($tmp->games)){
		foreach($tmp->games as $game){
			$resultsObject['games'][] = array(
				'name' => $game->name,
				'logo' => replace_http($game->box->large)
			);
		}
	} else {
		$resultsObject['errors'][] = array(
			'text' => 'Unable to get games list',
			'data' => vardump($tmp)
		);
	}
	
	header("Content-type: application/json;charset=utf-8");
	echo json_encode($resultsObject);
}

function get_top_streams($limit=15, $offset=0){
	$offset=intval($offset);
	$limit=intval($limit);
	if ($offset==0){
		$tmp=twget("/streams?limit={$limit}", 180);
	} else {
		$tmp=twget("/streams?limit={$limit}&offset={$offset}");
	}
	$header="<div id='favblock'><div class='panel panel-default'><div class='panel-heading'><a class='btn btn-default btn-xs btn-nav-back-small' href='javascript:' onClick='get_favourites();' title='Refresh'><span class='fa fa-refresh'></span></a> Top channels</div>";
	$footer="</div></div>";
	if (isset($tmp->streams)){
		$header .= "<div class='follows-list'>";
		$list='';
		$i=0;
		$streams=array();
		foreach($tmp->streams as $stream){
			$streams[$stream->channel->name]=array('name'=>$stream->channel->name, 'viewers'=>$stream->viewers, 'game'=>$stream->game, 'status'=>isset($stream->channel->status) ? $stream->channel->status : '[Untitled broadcast]', 'logo'=>$stream->channel->logo, 'viewers'=>$stream->viewers, 'display_name'=>$stream->channel->display_name);
		}

		foreach($streams as $channel=>$stream){
			$list.=format_favourite(array($channel=>$stream), $channel, $stream['display_name'], $stream['logo'], true);
			$i++;
		}
		$list.="</div>";
		$footer="</div></div></div>";
		return $header . $list . $footer;
	} else {
		return msg('error', "Can't retrieve top streams!");
	}
}

function format_favourite($streams, $name, $display_name, $logo, $online){
	//echo "<pre>"; var_dump($streams); echo "</pre>";
	$onl_highlight=($online) ? ' follows-online' : '';
	$onl_play=($online) ? "<a  onclick='livestreamer(this);' data-live='http://twitch.tv/{$name}' href='javascript:' title='Start in Livestreamer'><img src='" . replace_http($logo). "' class='fav-avatar'></a>" : "<img src='" . replace_http($logo). "' class='fav-avatar'>";
	$viewers=($online) ? $streams[$name]['viewers'] : false;
	$vwr=($viewers) ? "<span class='follows-viewers'><span class='fa fa-eye fa-fw'></span> {$viewers}</span>" : '';
	$game=(isset($streams[$name]['game']) && $streams[$name]['game']!==NULL) ? "<b>{$streams[$name]['game']}</b> - " : '';
	$tooltip=($online) ? str_replace("'", "&#39;", $streams[$name]['game'] . ' - ' . $streams[$name]['status']) : '';
	$details=(isset($streams[$name]['status'])) ? "<div class='text-muted channel-info' title='{$tooltip}'>{$game}{$streams[$name]['status']}</div>" : "<div class='text-muted channel-info' title='Channel is offline'>Offline</div>";
	
	//$list.="<a class='ajax list-group-item{$onl_highlight}' href='?channel={$name}'><img src='" . replace_http($logo). "' class='fav-avatar'>{$vwr}<span style='font-weight:bold'>{$follow->channel->display_name}</span>{$details}</a>";
	return "
		<div class='follows-item{$onl_highlight}'>
			{$vwr}
			{$onl_play}
			<div class='follows-data'>
				<a href='?channel={$name}' class='chan-name-link ajax' title='Show channel info'>{$display_name}</a>
				{$details}
			</div>
		</div>";
}

function get_favourites($ajax = false) {
	//$user=urlencode($user);
	$tmp=twget('/user', false);
	$header="<div id='favblock'><div class='panel panel-default'><div class='panel-heading'><a class='btn btn-default btn-xs btn-nav-back-small' href='javascript:' onClick='get_favourites();' title='Refresh'><span class='fa fa-refresh'></span></a> Following</div>";
	$footer="</div></div>";
	if (isset($tmp->name)) {
		$user=$tmp->name;
		
		if ($ajax){
			$tmp=twget("/users/{$user}/follows/channels?limit=100", 600);
		} else {
			$tmp=twget("/users/{$user}/follows/channels?limit=100", 600, true);
		}
		
		if ($tmp!==NULL || $ajax) {
			if (isset($tmp->follows)) {
				$streams = array();
				$tmp2 = twget("/streams/followed?limit=100&stream_type=live", 60);
				if (isset($tmp2->streams)) {
					foreach($tmp2->streams as $stream) {
						$streams[$stream->channel->name] = array(
							'id'      => $stream->channel->_id,
							'name'    => $stream->channel->name,
							'viewers' => $stream->viewers,
							'game'    => $stream->game,
							'status'  => isset($stream->channel->status) ? $stream->channel->status : '[Untitled broadcast]'
						);
					}
				}

				$list="<div class='follows-list noselect'>";
				// var_dump($streams);
				foreach($tmp->follows as $follow){
					if (isset($follow->channel)){
						// echo "<pre>"; var_dump($follow); echo "</pre>";
						$online = isset($streams[$follow->channel->name]);
						if ($online){
							$list.=format_favourite($streams, $follow->channel->name, $follow->channel->display_name, $follow->channel->logo, true);
						}
					} else {
						
					}
				}
				
				foreach($tmp->follows as $follow){
					if (isset($follow->channel)){
						$online=(isset($streams[$follow->channel->name]));
						if (!$online){
							$list.=format_favourite($streams, $follow->channel->name, $follow->channel->display_name, $follow->channel->logo, false);
						}
					} else {
						
					}
				}
				$list.='</div>';
				return $header . $list . $footer;
			} else {
				return msg('error', "Can't retrieve favourites!");
			}
		} else {
			return $header . 
				"<div class='panel-body'><div class='center loading-placeholder'></div></div>" . 
				$footer;
		}
	} else {
		return get_top_streams();
	}
}

function get_channel_info($channel){
	$channel=urlencode($channel);
	$tmp=twget("/channels/{$channel}", 60);
	if (isset($tmp->display_name)){
		//var_dump($tmp);
		$ueg=($tmp->game===NULL) ? '' : urlencode($tmp->game);
		$shg=($tmp->game===NULL) ? 'is streaming' : "is playing <a href='?game={$ueg}' class='ajax'>{$tmp->game}</a>";
		$stream=twget("/streams/{$tmp->display_name}");
		$status=($tmp->status===NULL) ? '[ No title ]' : $tmp->status;
		$list='';
		
		if (isset($stream->stream)){
			$list.="<table class='channel-data-table'><tr><td style='width:40%'>";
			$list.="<div class='channel-data'><img src='" . replace_http($tmp->logo). "' class='fav-avatar'>{$tmp->display_name}<br>{$shg}</div>";
			$list.="</td><td>";
			$list.="<div class='btn-group btn-group-justified'><a class='btn btn-lg btn-primary' style='height:100%'  onclick='livestreamer(this);' data-live='http://twitch.tv/{$tmp->name}' href='javascript:'>Watch in Livestreamer</a><a class='btn btn-lg btn-primary' style='height:100%' href='irc://irc.twitch.tv:443/{$tmp->name}'>Open IRC Chat</a></div>";
			$list.="</td></tr></table>";
			// $list.=(isset($stream->stream->preview->large)) ? "<div class='preview-banner'><img src='" .replace_http($stream->stream->preview->large) . "' class='img-thumbnail'/></div>" : ((isset($tmp->profile_banner)) ? "<div class='preview-banner'><img src='" .replace_http($tmp->profile_banner). "' class='img-thumbnail'/></div>" : '');
			//$list.= "<iframe class='channel-preview-frame' src='player.php?channel={$tmp->name}'></iframe>";
			$list.= "<div data-channel='{$tmp->name}'></div>";
		} else {
			$list.="<table class='channel-data-table'><tr><td style='width:40%'>";
			$list.="<div class='channel-data'><img src='" . replace_http($tmp->logo). "' class='fav-avatar'>{$tmp->display_name}<br>is offline</div>";
			$list.="</td><td>";
			$list.="<div class='btn-group btn-group-justified'><a class='btn btn-lg btn-default' style='height:100%' onclick='livestreamer(this);' data-live='http://twitch.tv/{$tmp->name}' href='javascript:'>Watch in Livestreamer</a><a class='btn btn-lg btn-default' style='height:100%' href='irc://irc.twitch.tv:443/{$tmp->name}'>Open IRC Chat</a></div>";
			$list.="</td></tr></table>";
			$list.=(isset($tmp->video_banner)) ? "<div class='preview-banner'><img src='" . replace_http($tmp->video_banner). "' class='img-thumbnail'/></div>" : '';
		}
		$footer="<script>set_modalchannel_title(\"{$status}\");</script>";
		return $list . $footer;
	} else {
		return msg('error', "Can't retrieve channel info!");
	}
}

function checkNewQuality($line, $quality) {
	$mapper = array(
		'audio' => 'audio',
		'mobile' => '144p30',
		'low' => '240p30',
		'medium' => array('480p30', '360p30'),
		'high' => array('720p60', '720p30'),
		'source' => array('1080p60', '1080p30')
	);
	
	$detect = preg_match("([0-9]{3,4}p[0-9]{2,3})", $line, $detectMatches);
	
	if (!$detect) return false;
	if (!isset($mapper[$quality])) return false;
	if (is_string($mapper[$quality])) {
		return true;
	} else if (is_array($mapper[$quality])) {
		foreach ($mapper[$quality] as $qual) {
			if (in_array($qual, $detectMatches)) {
				return true;
			}
		}
	}

	return false;
}
?>