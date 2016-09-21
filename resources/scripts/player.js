var video;

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
	initComplete: false,
	muteEnabled: false,
	muteVolume: 0,
	isPlaying: false,
	
	hideTimer: function(disable) {
		if (this.initComplete) {
			ge('controls').style.display = 'flex';
			
			if (this.hideTimeVar) {
				clearTimeout(this.hideTimeVar);
			}
			
			if (!disable) {
				this.hideTimeVar = setTimeout(function() {
					ge('controls').style.display = 'none';
				}, 3000);
			}
		}
	},
	
	play: function(trigger, seek) {
		if (seek) {
			video.currentTime = (video.duration - 7 > 0) ? video.duration - 7 : 0;
		}
		
		if (trigger) {
			video.play();
		}
		
		ge('btn-play').style.display = 'none';
		ge('btn-stop').style.display = '';
		
		this.isPlaying = true;
	},
	
	stop: function(trigger) {
		if (trigger) {
			video.pause();
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
				this.play(true, true);
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
		if (this.muteEnabled) {
			this.muteEnabled = false;
		}
		
		value = value > 1 ? 1 : value;
		value = value < 0 ? 0 : value;
		
		video.volume = value;
		if (save) {
			Storage.set('volume', value);
		}
		
		if (updateTrackBar) {
			ge('slider-volume').value = value;
			this.hideTimer();
		}
	},
	
	mute: function() {
		if (this.muteEnabled) {
			this.muteEnabled = false;
			video.volume = this.muteVolume;
			ge('slider-volume').value = this.muteVolume;
		} else {
			this.muteEnabled = true;
			this.muteVolume = video.volume;
			video.volume = 0;
			ge('slider-volume').value = 0;
		}
		
		this.hideTimer();
	}
}

ge('btn-play').addEventListener('click', function() {
	Controls.play(true, true);
});

ge('btn-stop').addEventListener('click', function() {
	Controls.stop(true);
});

ge('btn-screen').addEventListener('click', function() {
	Controls.fullscreen();
});

ge('video').addEventListener('ended', function() {
	Controls.stop(false);
});

ge('video').addEventListener('pause', function() {
	Controls.stop(false);
});

ge('video').addEventListener('error', function() {
	Controls.stop(false);
});

ge('video').addEventListener('suspend', function() {
	Controls.stop(false);
});

ge('video').addEventListener('play', function() {
	Controls.play(false, false);
});

ge('video').addEventListener('playing', function() {
	Controls.play(false, false);
});

ge('video').addEventListener('click', function(e) {
	e.preventDefault();
	return false;
});

ge('video').addEventListener('dblclick', function(e) {
	Controls.fullscreen();
	return false;
});

ge('video').addEventListener('contextmenu', function(e) {
	e.preventDefault();
	return false;
});

ge('video').addEventListener('mousemove', function(e) {
	Controls.hideTimer(false);
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
	// console.log(e);
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
	// console.log('keyup', e);
	if (e.keyCode === 38) { // arrow up
		Controls.volume(video.volume + 0.05, true, true);
		e.preventDefault();
	} else if (e.keyCode === 40) { // arrow down
		Controls.volume(video.volume - 0.05, true, true);
		e.preventDefault();
	}
});


ge('video').addEventListener("mousewheel", mouseWheelHandler, false); // IE9, Chrome, Safari, Opera
ge('video').addEventListener("DOMMouseScroll", mouseWheelHandler, false); // Firefox

function mouseWheelHandler(e) {
	// console.log(e);
	var delta = Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
	if (delta > 0) {
		Controls.volume(video.volume + 0.05, true, true);
	} else {
		Controls.volume(video.volume - 0.05, true, true);
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

function init() {
	if (Hls.isSupported()) {
		video = document.getElementById('video');
		var hls = new Hls();
		hls.loadSource('player.php?m3u8=1&channel=' + App.channel + '&quality=' + App.quality + '&_=' + Math.random());
		hls.attachMedia(video);
		hls.on(Hls.Events.MANIFEST_PARSED,function() {
			Controls.initComplete = true;
			Controls.hideTimer();
			Controls.play(true, false);
		});
		
		hls.on(Hls.Events.FRAG_LOADED,function() {
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
		video.volume = volume;
		ge('slider-volume').value = volume;

		setTimeout(function() {
			var i = document.createElement('input');
			i.type = 'text';
			i.style.opacity = 0;
			document.body.appendChild(i);
			i.focus();
			document.body.removeChild(i);
		}, 10);
	} else {
		MessageBar.show("HLS.js doesn't support this browser", true);
	}
}

document.addEventListener('DOMContentLoaded', function() {
	init();
});