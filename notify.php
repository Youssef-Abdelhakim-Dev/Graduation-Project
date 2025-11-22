<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Live Lecture - Student</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
/* -----------------------------
   Body & Container
----------------------------- */
body {
    font-family: Arial, sans-serif;
    background: #f0f2f5;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 100vh;
}

.container {
    background: white;
    padding: 25px 30px;
    border-radius: 15px;
    max-width: 650px;
    width: 100%;
    margin-top: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

h2 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

/* -----------------------------
   Inputs & Buttons
----------------------------- */
.input-field { margin-bottom: 15px; }
.input-field input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 16px;
}

.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 5px;
}

.btn-primary { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-muted { background: #6c757d; color: white; }
.btn-warning { background: #ffc107; color: black; }

.hidden { display: none; }

/* -----------------------------
   Video & Timer
----------------------------- */
.video-container { margin-top: 20px; text-align: center; }
video {
    width: 100%;
    max-height: 360px;
    border-radius: 10px;
    background: black;
}

.timer { 
    margin-top: 10px; 
    font-weight: bold; 
    color: #333; 
    text-align: center; 
    font-size: 18px; 
}

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
    background: linear-gradient(90deg,#28a745,#ffc107,#dc3545);
    transition: width 0.3s;
}

/* -----------------------------
   Offline / Warning
----------------------------- */
.status { text-align:center; margin-top:15px; font-size:14px; color:#555; }
.status.offline { color:#dc3545; font-weight:bold; }
.status.slow { color:#ffc107; font-weight:bold; }
</style>
</head>
<body>

<div class="container">
    <h2>📡 Live Lecture - Student</h2>

    <div class="input-field">
        <input type="text" id="lectureName" placeholder="Enter Lecture Name">
    </div>

    <div style="text-align:center; margin-bottom:10px;">
        <button id="joinBtn" class="btn btn-primary">Join Lecture</button>
        <button id="leaveBtn" class="btn btn-danger hidden">Leave Lecture</button>
    </div>

    <div class="video-container">
        <video id="remoteVideo" autoplay playsinline></video>
        <div class="timer" id="timer">⏱ 00:00</div>
        <div class="progress-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
    </div>

    <div class="status" id="statusText">Status: Not connected</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
class StudentClient {
    constructor(wsUrl, remoteVideoEl, timerEl, progressEl, statusEl) {
        this.wsUrl = wsUrl;
        this.remoteVideoEl = remoteVideoEl;
        this.timerEl = timerEl;
        this.progressEl = progressEl;
        this.statusEl = statusEl;

        this.ws = null;
        this.pc = null;
        this.room = null;
        this.connected = false;
        this.queue = [];
        this.timerInterval = null;
        this.elapsedTime = 0;
        this.reconnectAttempts = 0;

        this.initWebSocket();
        this.monitorConnection();
    }

    initWebSocket() {
        this.ws = new WebSocket(this.wsUrl);

        this.ws.onopen = () => {
            console.log("✅ WebSocket connected");
            this.statusEl.textContent = "Status: Connected";
            this.connected = true;
            this.reconnectAttempts = 0;
            while(this.queue.length > 0) {
                this.ws.send(this.queue.shift());
            }
        };

        this.ws.onmessage = async (event) => {
            try {
                const data = JSON.parse(event.data);
                if(!this.pc) return;

                switch(data.type){
                    case 'offer':
                        console.log("✅ Offer received");
                        await this.pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
                        const answer = await this.pc.createAnswer();
                        await this.pc.setLocalDescription(answer);
                        this.send({ type: 'answer', sdp: answer, room: data.room });
                        break;

                    case 'ice-candidate':
                        try { await this.pc.addIceCandidate(new RTCIceCandidate(data.candidate)); } 
                        catch(e){ console.error("ICE Error:", e); }
                        break;

                    default: console.warn("Unknown message type:", data.type);
                }
            } catch(e){ console.error("Invalid WS message:", e); }
        };

        this.ws.onclose = () => {
            console.warn("⚠️ WebSocket closed");
            this.connected = false;
            this.statusEl.textContent = "Status: Disconnected";
            this.handleLectureEnd();
            if(this.reconnectAttempts < 5){
                setTimeout(()=>{ this.reconnectAttempts++; this.initWebSocket(); }, 2000);
            }
        };

        this.ws.onerror = (err) => {
            console.error("WebSocket error:", err);
            this.statusEl.textContent = "Status: Error";
        };
    }

    send(obj){
        const msg = JSON.stringify(obj);
        if(this.connected) this.ws.send(msg);
        else this.queue.push(msg);
    }

    sanitizeRoom(room){ return room.replace(/[^a-zA-Z0-9-_ ]/g,'').trim() || 'Lecture'; }

    async joinLecture(roomName){
        if(!roomName) return;

        this.room = this.sanitizeRoom(roomName);

        // Create PeerConnection
        this.pc = new RTCPeerConnection();

        // Start timer
        this.elapsedTime = 0;
        clearInterval(this.timerInterval);
        this.timerEl.textContent = "⏱ 00:00";
        this.timerInterval = setInterval(()=>{
            this.elapsedTime++;
            let m = Math.floor(this.elapsedTime/60), s=this.elapsedTime%60;
            this.timerEl.textContent = `⏱ ${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        }, 1000);

        // Track remote stream
        this.pc.ontrack = event => {
            console.log("🎥 Remote track received");
            this.remoteVideoEl.srcObject = event.streams[0];
        };

        // ICE candidates
        this.pc.onicecandidate = event => {
            if(event.candidate) this.send({type:'ice-candidate',candidate:event.candidate,room:this.room});
        };

        // Connection state change
        this.pc.onconnectionstatechange = () => {
            console.log("Connection state:", this.pc.connectionState);
            if(["disconnected","failed","closed"].includes(this.pc.connectionState)){
                this.handleLectureEnd();
            }
        };

        // Join room
        this.send({type:'join', room:this.room});
        this.statusEl.textContent = "Status: Joined room '"+this.room+"'";
        document.getElementById('joinBtn').classList.add('hidden');
        document.getElementById('leaveBtn').classList.remove('hidden');
    }

    leaveLecture(){
        if(this.pc){
            this.pc.close();
            this.pc = null;
        }
        clearInterval(this.timerInterval);
        this.remoteVideoEl.srcObject = null;
        document.getElementById('joinBtn').classList.remove('hidden');
        document.getElementById('leaveBtn').classList.add('hidden');
        this.statusEl.textContent = "Status: Left lecture";
        Swal.fire({
            title:"❌ Lecture Ended",
            text:"You left the lecture",
            icon:"warning"
        });
    }

    handleLectureEnd(){
        clearInterval(this.timerInterval);
        if(this.remoteVideoEl.srcObject){
            this.remoteVideoEl.srcObject.getTracks().forEach(track=>track.stop());
            this.remoteVideoEl.srcObject=null;
        }
        document.getElementById('joinBtn').classList.remove('hidden');
        document.getElementById('leaveBtn').classList.add('hidden');
        Swal.fire({
            title:"❌ Lecture Ended",
            text:"The live lecture has ended.",
            icon:"warning"
        });
    }

    monitorConnection(){
        setInterval(()=>{
            if(!navigator.onLine){
                this.statusEl.textContent="Status: Offline";
                this.statusEl.classList.add("offline");
            } else {
                this.statusEl.classList.remove("offline");
                const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                if(conn && conn.downlink < 1.0){
                    this.statusEl.textContent="Status: Slow network";
                    this.statusEl.classList.add("slow");
                } else {
                    if(this.connected)this.statusEl.textContent="Status: Connected";
                    this.statusEl.classList.remove("slow");
                }
            }
        },3000);
    }
}

// --- USAGE ---
const client = new StudentClient(
    "ws://localhost:4000",
    document.getElementById('remoteVideo'),
    document.getElementById('timer'),
    document.getElementById('progressBar'),
    document.getElementById('statusText')
);

document.getElementById('joinBtn').onclick = () => {
    const lectureName = document.getElementById('lectureName').value;
    if(!lectureName){
        Swal.fire({title:"⚠️ Enter Lecture Name", icon:"warning"});
        return;
    }
    client.joinLecture(lectureName);
};

document.getElementById('leaveBtn').onclick = ()=> client.leaveLecture();
</script>
</body>
</html>
