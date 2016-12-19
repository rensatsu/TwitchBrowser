// localStorage wrapper
var LS = {
	prefix: 'link_twitch_',

	get: function (key) {
		return localStorage.getItem(this.prefix + key);
	},

	set: function (key, value) {
		return localStorage.setItem(this.prefix + key, value);
	},

	del: function (key) {
		return localStorage.removeItem(this.prefix + key);
	}
}

function getRandom (min, max) {
	return Math.floor(Math.random() * max + min);
}

var Settings = {
	quality: 'source',
	player: 'inapp',
	livestreamer: '',
	readNotices: '',
	chat: false
}

var Notices = {
	'p': "New settings window. Option to show chat by default.",
	'r': "Due to Twitch API changes, this application may not work. In the future, most probably, it will be discontinued"
};

var loadingAnimation = "<div class='loading'><img src='resources/images/ring.svg' alt='Loading' /><p>Loading...</p></div>";
var PlayerArray = [];
var HLSArray = [];
var LPArray = [];
var supportedPlayers = ['inapp', 'livestreamer'];
var transparentGif = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
var isPopout = false;

var Search = {
	performDelay: false,
	isClosed: true,
	
	open: function(noClearInput) {
		var noClearInput = noClearInput || false;
		$('#goto-win-inner').removeClass('lmd-close').addClass('lmd-open');
		if (!noClearInput) {
			$('#goto-win-prompt').val('');
		}
		this.isClosed = false;
		$('#goto-win-prompt').focus();
		$('#search-results').html('');
	},
	
	close: function() {
		$('#goto-win-inner').addClass('lmd-close').removeClass('lmd-open');
		this.isClosed = true;
	},
	
	changeEvent: function(el) {
		var owner = this;
		if ($(el).val().length >= 3) {
			if (owner.performDelay) {
				clearTimeout(owner.performDelay);
			}
			
			owner.performDelay = setTimeout(function() {
				owner.perform($(el).val());
			}, 500);
		}
	},
	
	init: function() {
		var owner = this;
		$('#goto-win-prompt').on('keypress keyup', function(e) {
			owner.changeEvent(this);
		});
	},
	
	perform: function(query) {
		var owner = this;
		console.log("Search request: ", query);
		$.ajax({
			method: 'POST',
			url: '?ajax=1',
			data: {
				ajax: 1,
				search: query
			},
			dataType: 'json',
			timeout: 10000,
			success: function(d) {
				if (owner.isClosed) {
					owner.open(true);
				}
				
				$('#search-results').scrollTop(0).html('');
				
				if (d.channels.length > 0) {
					$('#search-results').append("<div id='search-results-channels'><h4>Live channels</h4></div>");
					for (var i = 0; i < d.channels.length; i++) {
						var channel = d.channels[i];
						$('#search-results-channels').append("\
						<a class='search-result-channel' onclick='setChannel($(this).data(\"name\"));' data-name='" + channel.name + "' href='javascript:'>" +
							"<img src='" + (channel.logo.length > 0 ? channel.logo : transparentGif) + "'>" +
							"<div class='text-muted channel-info' title='" + channel.status + "'>" +
							"<div><b>" + channel.display_name + "</b></div>" +
							"<div><b>" + (channel.game || '< No game >') + "</b> - " + channel.status + "</div></div>" +
						"</a>");
					}
				} else {
					$('#search-results').append("<div id='search-results-channels'><h4>Live channels</h4><p>Not found</p></div>");
				}
				
				if (d.games.length > 0) {
					$('#search-results').append("<div id='search-results-games'><h4>Games</h4></div>");
					for (var i = 0; i < d.games.length; i++) {
						var game = d.games[i];
						$('#search-results-games').append("\
						<a class='search-result-game' onclick='location.hash = $(this).data(\"link\"); Search.close();' data-link='game=" + game.name + "' href='javascript:'>" +
							"<img src='" + game.logo + "'>" +
							"<div class='text-muted channel-info' title='" + game.name + "'>" +
							"<div><b>" + game.name + "</b></div></div>" +
						"</a>");
					}
				} else {
					$('#search-results').append("<div id='search-results-games'><h4>Games</h4><p>Not found</p></div>");
				}
			}
		});
	}
}

var SettingsWindow = {
	open: function() {
		$('#settings-win-inner').removeClass('lmd-close').addClass('lmd-open');
	},
	
	close: function() {
		$('#settings-win-inner').addClass('lmd-close').removeClass('lmd-open');
	},
	
	init: function() {
		
	}
}

function ajax(params, selector, changehash, noScrollTop){
	var changehash = changehash || 0;
	var noScrollTop = noScrollTop || 0;
	
	if (noScrollTop == 0){
		$(selector).html(loadingAnimation);
	}
	
	$.ajax({
		method: 'GET',
		url: '?ajax=1&' + params,
		dataType: 'html',
		timeout: 10000,
		success: function(d){
			$(selector).html(d);
			if (changehash > 0) {
				location.hash=params;
			}
			init_ajax();
			if (noScrollTop == 0) {
				$('html, body').animate({scrollTop: 0}, 100);
			}
		}
	});
}

var alertsCount = 0;
function alert(text, type, timeout){
	var text = text || '';
	var type = type || 'default';
	var timeout = timeout > 0 ? timeout : 10;
	alertsCount++;
	if (type=='error') type='danger';
	$('#notifications').append("<div class='notification notification-" + type +
		"' id='alert-" + alertsCount +
		"' style='display:none'>" + text + "</div>");
	$('#alert-' + alertsCount).slideDown('fast');
	$('#alert-' + alertsCount).on('click', function(){
		$(this).slideUp('fast', function(){
			$(this).remove();
		});
	});
	
	var sendIndex = alertsCount;
	setTimeout(function(){
		$('#alert-' + sendIndex).slideUp('fast', function(){
			$('#alert-' + sendIndex).remove();
		});
	}, timeout * 1000);
	return true;
}

function livestreamer(el) {
	var url = false;
	if (typeof el === 'string') {
		url = el;
	} else {
		url = $(el).data('live') || false;
	}
	
	if (url !== false){
		if (Settings.player === 'livestreamer') {
			if (window.node.available && Settings.livestreamer != '') {
				const fullUrl = url + ' ' + Settings.quality;
				window.node.ipcRenderer.send("shell-execute", {app: Settings.livestreamer, args: fullUrl});
				
				alert("Starting stream: " + url);
				return true;
			} else if (!window.node.available) {
				alert("Livestreamer option is only available in a launcher");
				return false;
			} else if (Settings.livestreamer == '') {
				alert("Livestreamer path is not defined, check settings");
				return false;
			}
		} else if (Settings.player === 'inapp') {
			var channel = url.match(/twitch\.tv\/([a-zA-Z0-9_]*)/);
			if (channel) {
				ChannelPreview.load("channel=" + channel[1]);
			}
			return true;
		} else {
			alert("Unsupported player!");
			return false;
		}
		
		return false;
	} else {
		alert("[DEBUG]: livestreamer: no channel");
		return false;
	}
}

function get_favourites(automatic){
	var automatic=automatic || 0;
	ajax('favourites=1', '#favblock', 0, automatic);
}

function set_modalchannel_title(title){
	var title=title || 'No title';
	$('#modal-channelLabel').html(title);
	$('#channel-preview-heading').html(title);
}

function init_ajax() {
	$('.ajax,a[href="?"]').each(function(){
		if (!$(this).hasClass('ajaxified')){
			$(this)
				.removeClass('ajax')
				.addClass('ajaxified')
				.unbind('click')
				.click(function(e){
					e.preventDefault();
					var th=$(this).attr('href').replace(/^(\?|#)/, '');
					location.hash=(th=='') ? '_=' + getRandom(10000, 99999) : th;
					
					//ajax($(this).attr('href').replace(/^(\?|#)/, ''), '#page');
					return false;
				});
		}
	});
}

var hash='';
var oldhash='';
var disable_hash_update=false;

function checkHash(){
	if (location.hash.replace(/^(\?|#)/, '') != hash){
		var tmphash=hash;
		hash=location.hash.replace(/^(\?|#)/, '');
		if (hash.indexOf('channel=')!=-1){
			oldhash=tmphash;
			ChannelPreview.load(hash);
		} else {
			ajax(hash, '#page');
			oldhash=tmphash;
			if (ChannelPreview.showing){
				disable_hash_update=true;
				ChannelPreview.destroyVideo();
				ChannelPreview.hide();
			}
		}
	}
}

function setQuality(quality) {
	Settings.quality = quality;
	
	$('#quality-selector-container label').removeClass('active');
	$('#quality-selector-container input[data-value="' + quality + '"]').prop('checked', 'checked').parent().addClass('active');
	
	LS.set('pref_quality', quality);
}

function setPlayer(player) {
	if (supportedPlayers.indexOf(player) > -1) {
		Settings.player = player;
		
		$('#player-selector-container label').removeClass('active');
		$('#player-selector-container input[data-value="' + player + '"]').prop('checked', 'checked').parent().addClass('active');
		
		if (player === 'inapp') {
			$('.window-heading-stream').hide();
			$('#settings-form-livestreamer').hide();
		} else {
			$('.window-heading-stream').show();
			$('#settings-form-livestreamer').show();
		}
		
		LS.set('pref_player', player);
	}
}

function setChannel(a) {
	var val = a || false;
	if (!val) {
		val = $('#goto-win-prompt').val();
	}
	
	Search.close();
	
	var channel = val.replace(/^(.*)\/([a-zA-Z0-9_]*)$/, "$2");
	location.hash = 'channel=' + channel;
}

function setLivestreamer(a) {
	var val = a || false;
	if (!val) {
		val = '';
	}
	
	val = val.replace(/\\\\/g, '/').replace(/\\/g, '/').replace(/"/g, '');
	LS.set('pref_livestreamer', val);
	Settings.livestreamer = val;
	$('#player-ls-location').val(val);
}

function setChat(a) {
	var val = (a === "true" || a === true) ? true : false;
	
	Settings.chat = val;
	
	LS.set('pref_chat', val);
	
	console.log("Changing chat status to: ", val);
	
	if (val) {
		$('#chat-checkbox').prop('checked', 'checked');
		$('#chat-checkbox-icon').removeClass('fa-square').addClass('fa-check-square');
	} else {
		$('#chat-checkbox-icon').removeClass('fa-check-square').addClass('fa-square');
	}
}

function showNotices() {
	var readKeys = Settings.readNotices.split(",");
	var mainKeys = Object.keys(Notices);
	for (var i in mainKeys) {
		if (readKeys.indexOf(mainKeys[i]) > -1) {
			continue;
		}
		alert(Notices[mainKeys[i]], 'default', 30);
	}
	LS.set('pref_notices', mainKeys.join(","));
}

function init_settings() {
	if (LS.get('pref_quality') === null) {
		setQuality(Settings.quality);
	} else {
		setQuality(LS.get('pref_quality'));
	}
	
	if (LS.get('pref_player') === null) {
		setPlayer(Settings.player);
	} else {
		setPlayer(LS.get('pref_player'));
	}
	
	if (LS.get('pref_notices') === null) {
		Settings.readNotices = '';
	} else {
		Settings.readNotices = LS.get('pref_notices');
	}
	showNotices();
	
	if (LS.get('pref_chat') === null) {
		setChat(Settings.chat);
	} else {
		setChat(LS.get('pref_chat'));
	}
	
	if (LS.get('pref_livestreamer') === null) {
		setLivestreamer(Settings.livestreamer);
	} else {
		setLivestreamer(LS.get('pref_livestreamer'));
	}
	
	$('#quality-selector-container input').unbind('change').on('change', function() {
		setQuality($(this).data('value'));
	});
	
	$('#player-selector-container input').unbind('change').on('change', function() {
		setPlayer($(this).data('value'));
	});
	
	$('#goto-channel').unbind('click').on('click', function() {
		Search.open();
	});
	
	$('#goto-settings').unbind('click').on('click', function() {
		SettingsWindow.open();
	});
	
	// $('#goto-win-prompt').on('keypress', function(e) {
	// 	if (e.keyCode == 13) {
	// 		e.preventDefault();
	// 		setChannel();
	// 	}
	// });
	
	$('#goto-win-confirm').unbind('click').on('click', function() {
		setChannel();
	});
	
	lmdWindows = document.querySelectorAll('.lmd-window');
	for (i = 0; i < lmdWindows.length; i++) {
		lmdWindows[i].addEventListener('click', function(e) {
			if (e.target.id == 'settings-win-inner') {
				e.preventDefault();
				SettingsWindow.close();
			} else if (e.target.id == 'goto-win-inner') {
				e.preventDefault();
				Search.close();
			}
		});
	}
	
	$('#settings-win-inner .lmd-close').unbind('click').on('click', function() {
		SettingsWindow.close();
	});
	
	$('#goto-win-inner .lmd-close,#goto-win-backdrop').unbind('click').on('click', function() {
		Search.close();
	});
	
	$('#goto-auth').unbind('click').on('click', function() {
		location.href = '?auth=1';
	});
	
	$('#goto-logout').unbind('click').on('click', function() {
		alert('Logging you out...');
		$.ajax({
			method: 'POST',
			url: '?',
			data: {
				ajax: 1,
				logout: 1
			},
			dataType: 'json',
			timeout: 10000,
			success: function() {
				location.reload();
			},
			error: function() {
				location.reload();
			}
		});
	});
	
	$('#chat-checkbox').unbind('change').on('change', function() {
		setChat($(this).prop('checked'));
	});
	
	$('#player-ls-location').unbind('change keypress').on('change keypress', function() {
		setLivestreamer($(this).val());
	});
}

function showAuth() {
	if (!userAuthorized) {
		$('#goto-auth').parent().removeClass('hide');
		$('#settings-logout').hide();
	}
}

function fullscreenWin() {
	if (
		document.fullscreenEnabled || 
		document.webkitFullscreenElement === null || 
		document.mozFullScreenEnabled ||
		document.msFullscreenEnabled
	) {
		var i = document.body;
		if (i.requestFullscreen) {
			i.requestFullscreen();
		} else if (i.webkitRequestFullscreen) {
			i.webkitRequestFullscreen();
		} else if (i.mozRequestFullScreen) {
			i.mozRequestFullScreen();
		} else if (i.msRequestFullscreen) {
			i.msRequestFullscreen();
		}
	} else {
		if (document.exitFullscreen) {
			document.exitFullscreen();
		} else if (document.webkitExitFullscreen) {
			document.webkitExitFullscreen();
		} else if (document.mozCancelFullScreen) {
			document.mozCancelFullScreen();
		} else if (document.msExitFullscreen) {
			document.msExitFullscreen();
		}
	}
}

function messagesHandler(e) {
	if (e.origin.indexOf(location.hostname) != -1) {
		var dataMatch = e.data.match(/twitch_browser_player=([\w\d\-_]*)/);
		if (dataMatch) {
			var action = dataMatch[1];
			console.log('TwitchPlayer Message:', action);
			
			switch (action) {
				case 'init':
					break;
				case 'chat':
					$('body').toggleClass('popup-sidebar-showing');
					break;
				case 'popout':
					ChannelPreview.popOut();
					break;
				case 'close':
					history.go(-1);
					break;
				case 'fullscreen':
					fullscreenWin();
					break;
			}
		}
	}
}

$(document).ready(function(){
	init_ajax();
	init_settings();
	
	if (!window.node.available) {
		$('#settings-form-player').hide();
		$('#settings-form-livestreamer').hide();
		setPlayer('inapp');
	}
	
	if (location.hash.indexOf('&popout=1') != -1) {
		isPopout = true;
	}

	if ((hash === '' || hash === '#') && $('#page-popular-games').length > 0) {
		// main page without top games yet
		ajax('main=1&offset=0&onlyGames=1', '#page-popular-games', 0, 0);
	}
	
	ChannelPreview.eventHide = function() {
		if (disable_hash_update){
			disable_hash_update=false;
		} else {
			hash=oldhash;
			location.hash=oldhash;
			oldhash='';
		}
	}
	
	$('.loading-placeholder').html(loadingAnimation);
	get_favourites(1);
	showAuth();
	setInterval(function(){ checkHash(); }, 100);
	setInterval(function(){ get_favourites(1); }, 90000);
	Search.init();
	
	window.addEventListener('message', messagesHandler);
});

var ChannelPreview = {
	elem: $('#channel-preview'),
	showing: false,
	eventHide: function() {},
	eventShow: function() {},
	channel: false,
	
	initVideo: function () {
		this.destroyVideo();
		if (this.channel === false) {
			return false;
		}
		
		$('#channel-preview-body').addClass('link-video');
		var quality = Settings.quality.split(",")[0];
		
		/* Twitch API *//*
		quality = quality == 'source' ? 'chunked' : quality;
		var options = {
			width: "100%",
			height: "100%",
			channel: this.channel,
			autoplay: true,
			html5: true,
			branding: false,
			quality: quality
		};
		
		var player = new Twitch.Player("channel-preview-body", options);
		player.addEventListener('ready', function() {
			console.log("Player is ready, starting playback");
			player.setVolume(0.5);
			player.play();
		});
		PlayerArray.push(player);
		/* - */
		
		/* Link Player */
		var videoOwner = document.getElementById('channel-preview-body');
		var frame = document.createElement('iframe');
		var random = Math.floor(Math.random() * 1000);
		frame.setAttribute('allowFullScreen', '');
		popOutAttr = isPopout ? 1 : 0;
		frame.src = 'player.php?channel=' + this.channel + '&quality=' + quality + '&popout=' + popOutAttr + '&_=' + random;
		videoOwner.appendChild(frame);
		LPArray.push(frame);
		/* - */
		
		this.loadChat(this.channel);
		
		return false;
	},
	
	destroyVideo: function () {
		var owner = this;

		for (var i = 0; i < PlayerArray.length; i++) {
			PlayerArray[i].pause();
		}
		
		for (var i = 0; i < HLSArray.length; i++) {
			HLSArray[i].destroy();
		}
		
		for (var i = 0; i < LPArray.length; i++) {
			LPArray[i].src = 'about:blank';
			LPArray[i].remove();
		}
		
		this.elem.find('.link-video').each(function() {
			$(this).html('');
		});
		
		$('#channel-preview-chat').html("");
		$('#channel-preview .window-heading').css({display: 'block'});
	},
	
	loadChat: function(channel) {
		$('#channel-preview-chat').html("<iframe frameborder='0' scrolling='no' src='https://www.twitch.tv/" +
			channel + "/chat' height='100%' width='100%'></iframe>");
			
		if (Settings.chat) {
			$('body').addClass('popup-sidebar-showing');
		}
	},
	
	popOut: function() {
		var owner = this;
		var newLink = (location.href.indexOf('#') != -1) ? location.href + '&popout=1' :
			location.href + '#&popout=1';
		window.open(newLink, '_blank', 'menubar=0,status=0,scrollbars=0,toolbar=0,width=1280,height=480', false);
		owner.destroyVideo();
		owner.hide();
	},
	
	show: function () {
		var owner = this;
		$('body').css({ overflow: 'hidden' });
		this.showing = true;
		this.elem.removeClass('lmd-close').addClass('lmd-open');
		this.elem.find('.window-heading-close')
			.unbind('click')
			.on('click', function() {
				history.go(-1);
			});
		this.elem.find('.window-heading-popout')
			.unbind('click')
			.on('click', function() {
				owner.popOut();
			});
		this.eventShow();
	},
	
	hide: function () {
		this.showing = false;
		$('body').css({ overflow: 'auto' });
		this.elem.addClass('lmd-close').removeClass('lmd-open');

		this.channel = false;
		$('body').removeClass('popup-sidebar-showing');
		this.eventHide();
	},
	
	load: function (hash) {
		var channel = hash.match(/channel=([a-zA-Z0-9_]*)/);
		if (channel) {
			var owner = this;
			this.channel = channel[1];
			location.hash = "channel=" + channel[1];
			this.elem.find('.window-heading-chat').unbind('click').on('click', function() {
				$('body').toggleClass('popup-sidebar-showing');
			});
			
			this.elem.find('.window-heading-stream').unbind('click').on('click', function() {
				livestreamer("https://twitch.tv/" + owner.channel);
				owner.destroyVideo();
				owner.hide();
			});
			
			this.initVideo();
			this.show();
		}
	}
}