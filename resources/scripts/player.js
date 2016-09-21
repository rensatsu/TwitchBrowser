var player;
var nsfwCheckComplete = false;
var startTime = new Date();

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
		var filterAmount = 20;
		
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
	
	fullscreen: function() {
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
	},
	
	volume: function(value, save, updateTrackBar) {
		if (player.getMuted()) {
			player.setMuted(false);
		}
		
		value = value > 1 ? 1 : value;
		value = value < 0 ? 0 : value;
		
		setVal = Math.pow(value, 2);
		this.playerVolume = value;
				
		player.setVolume(setVal);
		
		if (save) {
			Storage.set('volume', value);
		}
		
		if (updateTrackBar) {
			ge('slider-volume').value = value;
			this.hideTimer();
		}
	},
	
	mute: function() {
		if (player.getMuted()) {
			player.setMuted(false);
			ge('slider-volume').value = this.playerVolume;
		} else {
			player.setMuted(true);
			ge('slider-volume').value = 0;
		}

		this.hideTimer();
	}
}

ge('btn-play').addEventListener('click', function() {
	Controls.play(true);
});

ge('btn-stop').addEventListener('click', function() {
	Controls.stop(true);
});

ge('btn-screen').addEventListener('click', function() {
	Controls.fullscreen();
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
	} else if (e.keyCode === 32 || e.keyCode === 112) { // space or p
		Controls.togglePlayback();
		e.preventDefault();
	} else if (e.keyCode === 102) { // f
		Controls.fullscreen();
		e.preventDefault();
	}
});

document.addEventListener('keydown', function(e) {
	if (e.keyCode === 38) { // arrow up
		Controls.volume(video.volume + 0.05, true, true);
		e.preventDefault();
	} else if (e.keyCode === 40) { // arrow down
		Controls.volume(video.volume - 0.05, true, true);
		e.preventDefault();
	}
});

ge('channel-overlay').addEventListener('click', function(e) {
	e.preventDefault();
	return false;
});

ge('channel-overlay').addEventListener('dblclick', function(e) {
	Controls.fullscreen();
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

ge('channel-overlay').addEventListener("mousewheel", mouseWheelHandler, false); // IE9, Chrome, Safari, Opera
ge('channel-overlay').addEventListener("DOMMouseScroll", mouseWheelHandler, false); // Firefox

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
			Controls.initComplete = false;
			ge('controls').style.display = 'none';
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

function checkPlayerNSFW() {
	if (new Date() - startTime > 3000) {
		nsfwCheckComplete = true;
		ge('channel-overlay').style.display = 'block';
	} else {
		nsfwCheckComplete = false;
		ge('channel-overlay').style.display = 'none';
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
	
	player = new Twitch.Player("channel-player", options);
	
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
		startTime = new Date();
		ge('channel-overlay').style.display = 'block';
		console.log('twitch event play');
	});
	
	player.addEventListener(Twitch.Player.PAUSE, function() {
		if (!nsfwCheckComplete) {
			checkPlayerNSFW();
		}
		
		console.log('twitch event pause');
	});
	
	player.addEventListener(Twitch.Player.ONLINE, function() {
		MessageBar.hide();
		Controls.play(true);
		console.log('twitch event online');
	});

	setTimeout(function() {
		var i = document.createElement('input');
		i.type = 'text';
		i.style.opacity = 0;
		document.body.appendChild(i);
		i.focus();
		document.body.removeChild(i);
	}, 10);
}

document.addEventListener('DOMContentLoaded', function() {
	init();
});