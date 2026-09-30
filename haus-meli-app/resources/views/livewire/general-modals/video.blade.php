<div id="video-overlay" class="modal-overlay"
		style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:13000; align-items:center; justify-content:center;"
		onclick="closeHikeVideo(event)">
		<div style="position:relative; width:90%; max-width:800px; aspect-ratio:16/9;">
			<span style="position:absolute; top:-40px; right:0; font-size:3rem; color:#fff; cursor:pointer;"
				onclick="closeHikeVideo(null, true)">&times;</span>
			<video id="hike-video-player" controls
				style="width:100%; height:100%; border-radius:12px; background:#000;"></video>
		</div>
	</div>