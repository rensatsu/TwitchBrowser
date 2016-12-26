var player;

var Storage = {
	prefix: 'link_twitch_',
	
	check: function() {
		return (typeof(Storage) !== "undefined");
	},
	
	set: function(key, value) {
		if (!this.check()) return false;
		return localStorage.setItem(this.prefix + key, value);
	},
	
	get: function(key) {
		if (!this.check()) return false;
		return localStorage.getItem(this.prefix + key);
	},
	
	del: function(key) {
		if (!this.check()) return false;
		return localStorage.removeItem(this.prefix + key);
	}
}

function ge(el) {
	return document.getElementById(el);
}

var Controls = {
	hideTimeVar: false,
	hideTimeFilter: 0,
	hideTimeLast: 0,
	initComplete: false,
	muteEnabled: false,
	muteVolume: 0,
	playerVolume: 0.5,
	isPlaying: false,
	
	hideTimerFiltered: function() {
		var d1 = this.hideTimeLast;
		var d2 = new Date();
		var filterInterval = 600;
		var filterAmount = 10;
		
		if (d2 - d1 < filterInterval && this.hideTimeFilter++ > filterAmount) {
			this.hideTimer(false);
			this.hideTimeFilter = 0;
		} else if (d2 - d1 >= filterInterval) {
			this.hideTimeFilter = 0;
			this.hideTimeLast = d2;
		}
	},
	
	hideTimer: function(disable) {
		if (this.initComplete) {
			document.body.className = '';
			
			if (this.hideTimeVar) {
				clearTimeout(this.hideTimeVar);
			}
			
			if (!disable) {
				this.hideTimeVar = setTimeout(function() {
					document.body.className = 'nocontrols';
				}, 4000);
			}
		}
	},
	
	play: function(trigger, seek) {
		if (seek) {
			video.currentTime = (video.duration - 15 > 0) ? video.duration - 15 : 0;
		}
		
		if (trigger) {
			player.play();
		}
		
		ge('btn-play').style.display = 'none';
		ge('btn-stop').style.display = '';
		
		this.isPlaying = true;
	},
	
	stop: function(trigger) {
		if (trigger) {
			player.pause();
		}
		
		ge('btn-play').style.display = '';
		ge('btn-stop').style.display = 'none';
		
		this.isPlaying = false;
	},
	
	togglePlayback: function() {
		if (this.initComplete) {
			if (this.isPlaying) {
				this.stop(true);
			} else {
				this.play(true);
			}
		}
	},
	
	volume: function(value, save, updateTrackBar) {
		if (player.muted) {
			this.mute();
		}
		
		value = value > 1 ? 1 : value;
		value = value < 0 ? 0 : value;
		
		setVal = Math.pow(value, 2);
		this.playerVolume = value;
				
		player.volume = setVal;
		
		if (save) {
			Storage.set('volume', value);
		}
		
		if (updateTrackBar) {
			ge('slider-volume').value = value;
			this.hideTimer();
		}
	},
	
	mute: function() {
		if (player.muted) {
			player.muted = false;
			ge('btn-mute-overlay').style.display = 'none';
			ge('slider-volume').value = this.playerVolume;
		} else {
			player.muted = true;
			ge('btn-mute-overlay').style.display = 'block';
			ge('slider-volume').value = 0;
		}

		this.hideTimer();
	},
	
	messageParent: function(buttonType) {
		parent.postMessage('twitch_browser_player=' + buttonType, '*');
	}
}

ge('btn-play').addEventListener('click', function() {
	Controls.play(true, true);
});

ge('btn-stop').addEventListener('click', function() {
	Controls.stop(true);
});

ge('btn-screen').addEventListener('click', function() {
	Controls.messageParent('fullscreen');
});

ge('btn-popout').addEventListener('click', function() {
	Controls.messageParent('popout');
});

ge('btn-chat').addEventListener('click', function() {
	Controls.messageParent('chat');
});

ge('btn-close').addEventListener('click', function() {
	Controls.messageParent('close');
});

ge('btn-mute').addEventListener('click', function() {
	Controls.mute();
});

ge('controls').addEventListener('mousemove', function(e) {
	Controls.hideTimer(true);
});

ge('controls').addEventListener('mouseleave', function(e) {
	Controls.hideTimer(false);
});

ge('slider-volume').addEventListener('change', function(e) {
	Controls.volume(this.value, true, false);
});

ge('slider-volume').addEventListener('input', function(e) {
	Controls.volume(this.value, false, false);
});

document.addEventListener('keypress', function(e) {
	if (e.keyCode === 109) { // m
		Controls.mute();
		e.preventDefault();
	} else if (e.keyCode === 99) { // c
		Controls.messageParent('chat');
		e.preventDefault();
	} else if (e.keyCode === 111) { // o
		Controls.messageParent('popout');
		e.preventDefault();
	} else if (e.keyCode === 113) { // q
		Controls.messageParent('close');
		e.preventDefault();
	} else if (e.keyCode === 32 || e.keyCode === 112) { // space or p
		Controls.togglePlayback();
		e.preventDefault();
	} else if (e.keyCode === 102) { // f
		Controls.messageParent('fullscreen');
		e.preventDefault();
	}
});

document.addEventListener('keydown', function(e) {
	if (e.keyCode === 38) { // arrow up
		Controls.volume(player.volume + 0.05, true, true);
		e.preventDefault();
	} else if (e.keyCode === 40) { // arrow down
		Controls.volume(player.volume - 0.05, true, true);
		e.preventDefault();
	}
});

ge('channel-overlay').addEventListener('click', function(e) {
	e.preventDefault();
	return false;
});

ge('channel-overlay').addEventListener('dblclick', function(e) {
	Controls.messageParent('fullscreen');
	return false;
});

ge('channel-overlay').addEventListener('contextmenu', function(e) {
	e.preventDefault();
	return false;
});

ge('channel-overlay').addEventListener('mousemove', function(e) {
	// Controls.hideTimer(false);
	Controls.hideTimerFiltered();
});

ge('channel-overlay').addEventListener("mousewheel", mouseWheelHandler, false);
ge('channel-overlay').addEventListener("DOMMouseScroll", mouseWheelHandler, false);

function mouseWheelHandler(e) {
	var delta = Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
	var currentVol = Controls.playerVolume;
	if (delta > 0) {
		Controls.volume(currentVol + 0.05, true, true);
	} else {
		Controls.volume(currentVol - 0.05, true, true);
	}
	return false;
}

var MessageBar = {
	show: function(text, fatal) {
		if (fatal) {
			Controls.stop(true);
			// Controls.initComplete = false;
			// ge('controls').style.display = 'none';
			ge('message').className = 'fatal';
		} else {
			ge('message').className = 'nonfatal';
		}
		ge('message').style.display = 'block';
		ge('message').innerHTML = text;
	},
	
	hide: function() {
		ge('message').style.display = 'none';
		ge('message').innerHTML = '';
	}
}

function init() {
	App.quality = App.quality == 'source' ? 'chunked' : App.quality;
			
	var options = {
		width: "100%",
		height: "100%",
		channel: App.channel,
		autoplay: false,
		html5: true,
		branding: false,
		quality: App.quality
	};
	
	if (Hls.isSupported()) {
		player = document.getElementById('video');
		var hls = new Hls();
		hls.attachMedia(player);
		hls.on(Hls.Events.MEDIA_ATTACHED, function() {
			hls.loadSource('player.php?m3u8=1&channel=' + App.channel + '&quality=' + App.quality + '&_=' + Math.random());
			
			Controls.initComplete = true;
			ge('controls').style.display = 'flex';
			Controls.hideTimer();
			
			var volume = 0.5;
			
			if (Storage.get('volume')) {
				var volumeTest = parseFloat(Storage.get('volume'));
				
				if (volumeTest >= 0 && volumeTest <= 1) {
					volume = volumeTest;
				}
			}

			Controls.volume(volume, true, true);
			
			hls.on(Hls.Events.MANIFEST_PARSED, function() {
				Controls.initComplete = true;
				ge('controls').style.display = 'flex';
				ge('channel-overlay').style.display = 'block';
				Controls.hideTimer();
				Controls.play(true);
			});
		});
		
		hls.on(Hls.Events.FRAG_LOADED, function() {
			MessageBar.hide();
		});
		
		hls.on(Hls.Events.ERROR, function(type, e) {
			if (e.fatal && e.details == 'bufferStalledError') {
				MessageBar.show("Bufferizing...", false);
				Controls.play(true, false);
			} else if (e.fatal && e.details == 'manifestParsingError') {
				MessageBar.show("Playlist failed to load, retrying.", false);
				Controls.play(true, false);
			} else if (e.fatal) {
				switch(data.type) {
					case Hls.ErrorTypes.NETWORK_ERROR:
						// try to recover network error
						console.log("fatal network error encountered, try to recover");
						hls.startLoad();
						break;
					case Hls.ErrorTypes.MEDIA_ERROR:
						console.log("fatal media error encountered, try to recover");
						hls.recoverMediaError();
						break;
					default:
						// cannot recover
						hls.destroy();
						break;
				}
				MessageBar.show("HLS.js Playback Error, look for details in console", true);
			}
			
			if (!e.fatal && e.type == 'networkError') {
				MessageBar.show("Chunk loading timed out, probably connectivity issues.", false);
			}
			
			console.log("HLS.js error", type, e);
		});
	
		var volume = 0.5;
		if (Storage.get('volume')) {
			var volumeTest = Storage.get('volume');
			
			if (volumeTest >= 0 && volumeTest <= 1) {
				volume = volumeTest;
			}
		}

		Storage.set('volume', volume);
		player.volume = volume;
		ge('slider-volume').value = volume;

		setTimeout(function() {
			var i = document.createElement('input');
			i.type = 'text';
			i.style.opacity = 0;
			document.body.appendChild(i);
			i.focus();
			document.body.removeChild(i);
		}, 10);
		
		Controls.messageParent('init');
	} else {
		MessageBar.show("HLS.js doesn't support this browser", true);
	}
	
	/*
	player.addEventListener(Twitch.Player.READY, function() {
		Controls.initComplete = true;
		ge('controls').style.display = 'flex';
		Controls.hideTimer();
		
		var volume = 0.5;
		if (Storage.get('volume')) {
			var volumeTest = parseFloat(Storage.get('volume'));
			
			if (volumeTest >= 0 && volumeTest <= 1) {
				volume = volumeTest;
			}
		}

		Controls.volume(volume, true, true);
		
		Controls.play(true);
	});
	
	player.addEventListener(Twitch.Player.ENDED, function() {
		MessageBar.show("Stream has ended", true);
		console.log('twitch event ended');
	});
	
	player.addEventListener(Twitch.Player.OFFLINE, function() {
		MessageBar.show("Stream went offline", true);
		console.log('twitch event offline');
	});
	
	player.addEventListener(Twitch.Player.PLAY, function() {
		ge('channel-overlay').style.display = 'block';
		console.log('twitch event play');
	});
	
	player.addEventListener(Twitch.Player.ONLINE, function() {
		MessageBar.hide();
		Controls.play(true);
		console.log('twitch event online');
	});
	*/
	
	if (location.href.indexOf('&popout=1') != -1) {
		document.getElementById('btn-popout').style.display = 'none';
		document.getElementById('btn-close').style.display = 'none';
	}
}

document.addEventListener('DOMContentLoaded', function() {
	init();
});