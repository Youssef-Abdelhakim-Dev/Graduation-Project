document.addEventListener('DOMContentLoaded', () => {

    // ---------- TinyMCE Init ----------
    tinymce.init({
        selector: "#editor",
        height: 400,
        menubar: true,
        branding: false,
        skin: "oxide",
        content_css: "default",
        plugins: "link image media lists code table fullscreen wordcount emoticons autosave template autolink searchreplace help preview",
        toolbar: "undo redo | bold italic underline strikethrough | fontsizeselect | forecolor backcolor | emoticons | bullist numlist outdent indent | link image media | table | searchreplace fullscreen preview | code | restoredraft",
        contextmenu: "link image table | copy paste | code",
        autosave_interval: "20s",
        autosave_retention: "30m",
        autosave_prefix: "editor-autosave-",
        setup: (editor) => editor.on("init", () => console.log("TinyMCE Loaded Successfully"))
    });
    
    // ---------- CONFIG ----------
    const MAX_FILES = 10;
    const MAX_IMAGE_SIZE = 5 * 1024**2;
    const MAX_VIDEO_SIZE = 100 * 1024**2;
    const ALLOWED_IMAGE_TYPES = ['image/jpeg','image/png','image/gif','image/webp'];
    const ALLOWED_VIDEO_TYPES = ['video/mp4','video/webm','video/ogg'];
    
    // ---------- UI REFERENCES ----------
    const btnImage = document.getElementById('btnImage');
    const btnVideo = document.getElementById('btnVideo');
    const inpImage = document.getElementById('course-image');
    const inpVideo = document.getElementById('course-videos');
    const previewImages = document.getElementById('imagePreview');
    const previewVideos = document.getElementById('videoPreview');
    const form = document.getElementById('courseForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    const loadingWrapper = document.getElementById('loading');
    
    // ---------- STATE ----------
    let imageFiles = [];
    let videoFiles = [];
    let currentXHR = null;
    
    // ---------- HELPERS ----------
    const fmtBytes = bytes => {
        if (bytes === 0) return '0 B';
        const k = 1024, dm = 2;
        const sizes = ['B','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    };
    const iconForType = type => {
        if (!type) return 'fa-file';
        if (type.startsWith('image/')) return 'fa-image';
        if (type.startsWith('video/')) return 'fa-video';
        return 'fa-file';
    };
    const renderFilesPreview = (listEl, filesArray, type) => {
        listEl.innerHTML = '';
        filesArray.forEach((file, idx) => {
            const icon = iconForType(file.type);
            const badgeType = type==='image' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800';
            const chip = document.createElement('div');
            chip.className='flex items-center gap-2 p-2 rounded-lg bg-white shadow-sm';
            chip.style.minWidth='160px';
            chip.style.maxWidth='320px';
            chip.innerHTML=`
                <div class="flex-shrink-0"><i class="fa ${icon} text-xl ${type==='image'?'text-blue-500':'text-purple-500'}"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="truncate font-medium">${file.name}</div>
                    <div class="text-xs text-gray-500">${fmtBytes(file.size)} • ${file.type || 'unknown'}</div>
                    <div class="mt-1">
                        <span class="inline-block ${badgeType} px-2 py-0.5 rounded text-xs font-semibold">${type.toUpperCase()}</span>
                        <span class="inline-block bg-gray-100 px-2 py-0.5 rounded text-xs ml-2">allowed</span>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <button class="remove-file p-1 rounded text-gray-500 hover:text-red-500" data-idx="${idx}" data-kind="${type}" title="Remove file">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>`;
            if(type==='image'){const thumb=document.createElement('img');thumb.src=URL.createObjectURL(file);thumb.className='preview-img mr-2 rounded';thumb.style.width='84px';thumb.style.height='64px';thumb.style.objectFit='cover';chip.insertBefore(thumb, chip.firstChild);}
            if(type==='video'){const v=document.createElement('video');v.src=URL.createObjectURL(file);v.className='preview-video mr-2 rounded';v.style.width='120px';v.style.height='64px';v.muted=true;v.playsInline=true;chip.insertBefore(v,chip.firstChild);}
            listEl.appendChild(chip);
        });
        if(filesArray.length===0){const hint=document.createElement('div');hint.className='text-sm text-gray-400';hint.textContent='No files selected';listEl.appendChild(hint);}
    };
    
    document.addEventListener('click', ev => {
        const btn=ev.target.closest('.remove-file');
        if(!btn) return;
        const idx=parseInt(btn.dataset.idx,10);
        const kind=btn.dataset.kind;
        if(kind==='image'){if(idx>=0&&idx<imageFiles.length) imageFiles.splice(idx,1); renderFilesPreview(previewImages,imageFiles,'image');}
        else{if(idx>=0&&idx<videoFiles.length) videoFiles.splice(idx,1); renderFilesPreview(previewVideos,videoFiles,'video');}
    });
    
    btnImage.addEventListener('click',()=>inpImage.click());
    btnVideo.addEventListener('click',()=>inpVideo.click());
    
    inpImage.addEventListener('change', e => {
        const files=Array.from(e.target.files||[]);
        files.forEach(f => {if(ALLOWED_IMAGE_TYPES.includes(f.type)&&f.size<=MAX_IMAGE_SIZE) imageFiles.push(f);});
        if(imageFiles.length>MAX_FILES) imageFiles=imageFiles.slice(0,MAX_FILES);
        renderFilesPreview(previewImages,imageFiles,'image');
    });
    
    inpVideo.addEventListener('change', e => {
        const files=Array.from(e.target.files||[]);
        files.forEach(f => {if(ALLOWED_VIDEO_TYPES.includes(f.type)&&f.size<=MAX_VIDEO_SIZE) videoFiles.push(f);});
        if(videoFiles.length>MAX_FILES) videoFiles=videoFiles.slice(0,MAX_FILES);
        renderFilesPreview(previewVideos,videoFiles,'video');
    });
    
    form.addEventListener('submit', ev => {
        ev.preventDefault();
        Swal.fire({title:'Upload course?',text:'Are you ready to upload this course and files?',icon:'question',showCancelButton:true,confirmButtonText:'Yes, upload',cancelButtonText:'Cancel'})
        .then(result=>{if(result.isConfirmed) doUpload();});
    });
    
    async function doUpload(){
        try{
            if(submitBtn) submitBtn.disabled=true;
            if(btnImage) btnImage.disabled=true;
            if(btnVideo) btnVideo.disabled=true;
            loadingWrapper.classList.remove('hidden');
    
            const fd=new FormData(form);
            fd.set('description',tinymce.get('editor').getContent()||fd.get('description'));
            imageFiles.forEach(f=>fd.append('course_image[]',f,f.name));
            videoFiles.forEach(f=>fd.append('course_videos[]',f,f.name));
    
            const xhr=new XMLHttpRequest();
            currentXHR=xhr;
            xhr.onreadystatechange=function(){
                if(xhr.readyState!==4) return;
                cleanup();
                let raw=xhr.responseText||'';
                try{
                    const json=JSON.parse(raw);
                    if(json.status==='success'){
                        Swal.fire('Success','Course uploaded successfully!','success');
                        form.reset();
                        tinymce.get('editor').setContent('');
                        imageFiles=[]; videoFiles=[];
                        renderFilesPreview(previewImages,imageFiles,'image');
                        renderFilesPreview(previewVideos,videoFiles,'video');
                    }else{
                        Swal.fire('Server error',json.message||JSON.stringify(json),'error');
                    }
                }catch(err){
                    Swal.fire({title:'Unexpected server response',html:`<pre style="white-space:pre-wrap;text-align:left">${escapeHtml(raw).slice(0,4000)}</pre>`,icon:'error',width:800});
                    console.error('Raw server response:',raw);
                }
            };
            xhr.open('POST','',true);
            xhr.send(fd);
        }catch(err){cleanup(); Swal.fire('Error',err.message||'Unknown error','error');}
    }
    
    function cleanup(){
        currentXHR=null;
        if(submitBtn) submitBtn.disabled=false;
        if(btnImage) btnImage.disabled=false;
        if(btnVideo) btnVideo.disabled=false;
        loadingWrapper.classList.add('hidden');
    }
    
    function escapeHtml(str){if(!str) return ''; return str.replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
    
    renderFilesPreview(previewImages,imageFiles,'image');
    renderFilesPreview(previewVideos,videoFiles,'video');
    
    });