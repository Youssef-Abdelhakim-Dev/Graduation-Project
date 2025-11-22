
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Live video streaming and recording platform. Start your lecture, stream live, and save recordings effortlessly.">
<title>Live lecture</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* -----------------------------
   Body and Global Styles
----------------------------- */
body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 100vh;
}

/* -----------------------------
   Container
----------------------------- */
.container {
    background: white;
    padding: 20px 30px;
    border-radius: 15px;
    max-width: 600px;
    width: 100%;
    margin-top: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* -----------------------------
   Headings
----------------------------- */
h2 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

/* -----------------------------
   Input Fields
----------------------------- */
.input-field {
    margin-bottom: 15px;
}

.input-field input {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 16px;
}

/* -----------------------------
   Buttons
----------------------------- */
.btn {
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    margin-right: 5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-muted {
    background: #6c757d;
    color: white;
}

.btn-warning {
    background: #ffc107;
    color: black;
}

/* -----------------------------
   Utility Classes
----------------------------- */
.hidden {
    display: none;
}

/* -----------------------------
   Video Container
----------------------------- */
.video-container {
    margin-top: 20px;
    text-align: center;
}

video {
    width: 100%;
    max-height: 360px;
    border-radius: 10px;
    background: black;
}

/* -----------------------------
   Timer
----------------------------- */
.timer {
    margin-top: 10px;
    font-weight: bold;
    color: #333;
    text-align: center;
    font-size: 18px;
}

/* -----------------------------
   Progress Bar
----------------------------- */
.progress-container {
    margin-top: 10px;
    width: 100%;
    background: #eee;
    border-radius: 8px;
    overflow: hidden;
    height: 12px;
}

.progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
    transition: width 0.3s;
}

</style>
</head>
<body>

<main class="container">
<header>
<h2><i class="fa-solid fa-broadcast-tower" style="color:#ff5733"></i> Live lecture</h2>
</header>

<section class="input-field">
<input type="text" id="videoNameInput" placeholder="Enter video name" aria-label="Video Name">
</section>

<section>
<button id="startStreamBtn" class="btn btn-primary"><i class="fa-solid fa-play-circle"></i> Start Stream</button>
<button id="stopStreamBtn" class="btn btn-danger hidden"><i class="fa-solid fa-stop-circle"></i> Stop Stream</button>
<button id="muteToggleBtn" class="btn btn-muted hidden"><i class="fa-solid fa-volume-mute"></i> Mute</button>
<button id="pauseResumeBtn" class="btn btn-warning hidden"><i class="fa-solid fa-pause-circle"></i> Pause</button>
</section>

<section class="video-container">
<video id="videoPlayer" autoplay muted playsinline></video>
<div class="timer" id="timer"><i class="fa-solid fa-clock"></i> 00:00</div>
<div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
</section>
</main>

<script>
class StreamManager {
  constructor(){
    this.videoPlayer = document.getElementById("videoPlayer");
    this.videoNameInput = document.getElementById("videoNameInput");
    this.startBtn = document.getElementById("startStreamBtn");
    this.stopBtn = document.getElementById("stopStreamBtn");
    this.muteBtn = document.getElementById("muteToggleBtn");
    this.pauseBtn = document.getElementById("pauseResumeBtn");
    this.timerElement = document.getElementById("timer");
    this.progressBar = document.getElementById("progressBar");

    this.mediaRecorder = null;
    this.recordedChunks = [];
    this.videoStream = null;
    this.streamStarted = false;
    this.timerInterval = null;
    this.elapsedTime = 0;

    // WebSocket for signaling
    this.room = null;
    this.ws = new WebSocket("ws://localhost:4000");
    this.ws.onopen = ()=> console.log("✅ WS connected");
    this.ws.onerror = err=> console.error("❌ WS error", err);
    this.ws.onmessage = e=> this.handleWS(e);

    // WebRTC PeerConnection
    this.pc = null;

    // Event listeners
    this.startBtn.addEventListener("click", ()=> this.startStream());
    this.stopBtn.addEventListener("click", ()=> this.stopStream());
    this.muteBtn.addEventListener("click", ()=> this.toggleMute());
    this.pauseBtn.addEventListener("click", ()=> this.togglePause());
    window.addEventListener("beforeunload", e=> this.beforeUnload(e));
  }

  handleWS(event){
    try{
      const data = JSON.parse(event.data);
      if(data.room !== this.room) return;

      if(data.type === "answer"){
        console.log("✅ Answer received from student");
        this.pc?.setRemoteDescription(new RTCSessionDescription(data.sdp));
      } else if(data.type === "ice-candidate"){
        this.pc?.addIceCandidate(new RTCIceCandidate(data.candidate)).catch(console.error);
      }
    } catch(err){
      console.error("Invalid WS message:", event.data);
    }
  }

  async startStream(){
    if(!this.videoNameInput.value){
      Swal.fire({title:"⚠️ Enter Name", text:"Please enter lecture name.", icon:"warning"});
      return;
    }

    this.room = this.videoNameInput.value.trim();

    try{
      // Get camera/microphone
      this.videoStream = await navigator.mediaDevices.getUserMedia({video:true,audio:true});
      this.videoPlayer.srcObject = this.videoStream;

      // Start recording
      this.mediaRecorder = new MediaRecorder(this.videoStream);
      this.recordedChunks = [];
      this.mediaRecorder.ondataavailable = e=> this.recordedChunks.push(e.data);
      this.mediaRecorder.onstop = ()=> this.uploadVideo();
      this.mediaRecorder.start(1000);

      // Inform server about room join first
      this.ws.send(JSON.stringify({type:'join', room:this.room}));

      // Setup WebRTC
      this.pc = new RTCPeerConnection();
      this.videoStream.getTracks().forEach(track => this.pc.addTrack(track, this.videoStream));

      this.pc.onicecandidate = event=>{
        if(event.candidate){
          this.ws.send(JSON.stringify({type:"ice-candidate", candidate:event.candidate, room:this.room}));
        }
      };

      // Optional: debug connection state
      this.pc.onconnectionstatechange = ()=> console.log("Connection state:", this.pc.connectionState);

      // Create offer and send
      const offer = await this.pc.createOffer();
      await this.pc.setLocalDescription(offer);
      this.ws.send(JSON.stringify({type:"offer", sdp:offer, room:this.room}));

      this.streamStarted = true;
      this.startTimer();
      this.showControls(true);

    } catch(err){
      Swal.fire({title:"❌ Error", text:"Failed to access camera/mic.", icon:"error"});
      console.error(err);
    }
  }

  stopStream(){
    if(this.mediaRecorder && this.mediaRecorder.state !== "inactive") this.mediaRecorder.stop();
    if(this.videoStream) this.videoStream.getTracks().forEach(t=> t.stop());
    this.stopTimer();
    this.showControls(false);
    this.pc?.close();
  }

  toggleMute(){
    const audioTrack = this.videoStream?.getAudioTracks()[0];
    if(audioTrack){
      audioTrack.enabled = !audioTrack.enabled;
      this.muteBtn.innerHTML = audioTrack.enabled ? '<i class="fa-solid fa-volume-mute"></i> Mute' : '<i class="fa-solid fa-volume-up"></i> Unmute';
    }
  }

  togglePause(){
    if(!this.mediaRecorder) return;
    if(this.mediaRecorder.state === "paused"){
      this.mediaRecorder.resume();
      this.pauseBtn.innerHTML = '<i class="fa-solid fa-pause-circle"></i> Pause';
    } else if(this.mediaRecorder.state === "recording"){
      this.mediaRecorder.pause();
      this.pauseBtn.innerHTML = '<i class="fa-solid fa-play-circle"></i> Resume';
    }
  }

  startTimer(){
    this.elapsedTime = 0;
    this.timerInterval = setInterval(()=>{
      this.elapsedTime++;
      let m = Math.floor(this.elapsedTime/60), s = this.elapsedTime%60;
      this.timerElement.textContent = `⏱ ${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`;
    }, 1000);
  }

  stopTimer(){
    clearInterval(this.timerInterval);
    this.timerElement.textContent="";
    this.elapsedTime=0;
    this.progressBar.style.width="0%";
  }

  showControls(show){
    this.startBtn.classList.toggle("hidden", show);
    this.stopBtn.classList.toggle("hidden", !show);
    this.muteBtn.classList.toggle("hidden", !show);
    this.pauseBtn.classList.toggle("hidden", !show);
  }

  async uploadVideo(){
    const blob = new Blob(this.recordedChunks,{type:"video/webm"});
    const formData = new FormData();
    formData.append("video", blob, `${this.videoNameInput.value}.webm`);
    formData.append("filename", this.videoNameInput.value);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "upload_lectures.php", true);

    xhr.upload.onprogress = e => {
      if(e.lengthComputable){
        const percent = (e.loaded/e.total)*100;
        this.progressBar.style.width=`${percent}%`;
      }
    };

    xhr.onload = ()=>{
      try{
        const result = JSON.parse(xhr.responseText);
        if(result.success){
          Swal.fire({title:"✅ Stream Saved!", text:"Your stream was saved.", icon:"success"});
        } else {
          Swal.fire({title:"❌ Error!", text: result.error, icon:"error"});
        }
      } catch(err){
        Swal.fire({title:"❌ Upload Failed", text:"Check console.", icon:"error"});
        console.error(err);
      }
    };

    xhr.onerror = ()=> Swal.fire({title:"❌ Upload Failed", text:"Network error.", icon:"error"});
    xhr.send(formData);
  }

  async beforeUnload(e){
    if(this.streamStarted && this.mediaRecorder && this.mediaRecorder.state!=="inactive"){
      e.preventDefault(); e.returnValue="";
      const res = await Swal.fire({
        title:"⚠️ Unsaved Video!",
        text:"Video recording in progress. Save before leaving?",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Save",
        cancelButtonText:"Discard"
      });
      if(res.isConfirmed) this.mediaRecorder.stop();
    }
  }
}

new StreamManager();

</script>

</body>
</html>
