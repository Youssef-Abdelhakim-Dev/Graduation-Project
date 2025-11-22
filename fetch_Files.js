// fileFetcherWorker.js
// Worker fetches file as ArrayBuffer and posts progress messages.
// Use: worker.postMessage({ cmd:'fetch', url: 'path/to/file' });

self.onmessage = async (ev) => {
  const { cmd, url } = ev.data || {};
  if (cmd !== 'fetch' || !url) {
    self.postMessage({ type:'error', data:'Invalid command' });
    return;
  }
  try {
    const resp = await fetch(url);
    if(!resp.ok) {
      self.postMessage({ type:'error', data: 'Network response was not ok: ' + resp.status });
      return;
    }

    const contentLength = resp.headers.get('Content-Length') ? Number(resp.headers.get('Content-Length')) : null;

    // If body streaming supported -> track progress
    if(resp.body && resp.body.getReader) {
      const reader = resp.body.getReader();
      const chunks = [];
      let received = 0;
      while(true) {
        const { done, value } = await reader.read();
        if(done) break;
        chunks.push(value);
        received += value.length || value.byteLength || 0;
        if(contentLength) {
          const percent = Math.round((received / contentLength) * 100);
          self.postMessage({ type:'progress', data: percent });
        } else {
          // estimate or pulse
          self.postMessage({ type:'progress', data: Math.min(95, Math.round(received / 1024)) });
        }
      }
      // concat
      let totalLen = chunks.reduce((s,c)=> s + c.byteLength, 0);
      const tmp = new Uint8Array(totalLen);
      let offset = 0;
      for(const chunk of chunks) {
        tmp.set(new Uint8Array(chunk), offset);
        offset += chunk.byteLength;
      }
      // Transfer ArrayBuffer back to main thread
      self.postMessage({ type:'done', data: tmp.buffer }, [tmp.buffer]);
      return;
    } else {
      // fallback: arrayBuffer (may be slower)
      const ab = await resp.arrayBuffer();
      self.postMessage({ type:'progress', data: 100 });
      self.postMessage({ type:'done', data: ab }, [ab]);
      return;
    }
  } catch (err) {
    self.postMessage({ type:'error', data: err.message || String(err) });
  }
};
