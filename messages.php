<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Paneli</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">
    <style>
        body {
          margin: 0;
          display: flex;
          height: 100vh;
          font-family: 'Arial', sans-serif;
          }

          .sidebar {
           width: 250px;
           background: #f4f4f4;
           }

          .main {
          flex: 1;
          display: flex;
          flex-direction: column;
          }
         .topbar {
          height: 60px;
          background: #fff;
          border-bottom: 1px solid #ccc;
          }

         .chat-container {
          flex: 1;
          display: flex;
          }

        .users-panel {
            width: 25%;
            background: #fff;
            border-right: 2px solid #000;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .users-panel .user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            cursor: pointer;
        }

        .users-panel .user img {
            width: 60px;
            height: 600px;
        }

        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            background: #fff;
        }

        .messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message {
            padding: 10px 15px;
            border-radius: 20px;
            max-width: 70%;
            word-break: break-word;
            font-weight: bold;
            position:relative;
        }

        .message.left {
            align-self: flex-start;
            background-color: #f0f0f0;
            color: #000;
        }

        .message.right {
            align-self: flex-end;
            background-color: #d1e7ff;
            color: #000;
        }
        .message .status { display:block; margin-top:4px; font-size:11px; color:#555; }
        .message img { max-width:100%; height:auto; display:block; }
        .message iframe { background:#fff; }
        .message a.file-link { text-decoration:none; font-weight:600; }
        .message img.preview { max-width:260px; height:auto; border-radius:10px; cursor:zoom-in; display:block; }

        .send-box {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .send-box input {
            flex: 1;
            padding: 10px;
            border-radius: 20px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .send-box button {
            background: dodgerblue;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }

            .users-panel {
                width: 100%;
                border-right: none;
                border-bottom: 2px solid #000;
                height: auto;
                display: flex;
                overflow-x: auto;
            }

            .users-panel .user {
                flex: 0 0 auto;
                margin-right: 15px;
            }

            .chat-panel {
                padding: 10px;
            }
        }
        .message { position: relative; }
.message.right .status {
  font-size: 11px; color: #555; margin-top: 2px; display: block; text-align: right;
}
    </style>
</head>
<body>
<script>
    localStorage.setItem("id", "<?= $_SESSION['user_id'] ?>");
    localStorage.setItem("username", "<?= $_SESSION['username'] ?>");
    localStorage.setItem("role", "<?= $_SESSION['role'] ?>");
</script>
<?php
include 'includes/sidebar.php';
?>
<div class="main">
<?php
include "includes/header.php";
?>
<div class="chat-container">
    <div class="users-panel" id="users"></div>
    <div class="chat-panel">
        <div class="messages" id="messages"></div>
        <div class="send-box">
            <input type="text" id="messageInput" placeholder="Mesaj...">
            <input type="file" id="fileInput" style="display:none" />
            <button type="button" onclick="document.getElementById('fileInput').click()">📎</button>
            <button onclick="sendMessage()">Gönder</button>
        </div>

    </div>
</div>
</div>
<script src="https://cdn.socket.io/4.3.2/socket.io.min.js"></script>
<script>
    function openLightbox(url, filename='image'){
        const lb = document.getElementById('lightbox');
        document.getElementById('lightbox-img').src = url;
        const dl = document.getElementById('lightbox-download');
        dl.href = url; dl.download = filename;
        lb.style.display = 'flex';
    }
    function closeLightbox(){ document.getElementById('lightbox').style.display = 'none'; }
    document.getElementById('lightbox').addEventListener('click', e => {
        if(e.target.id === 'lightbox') closeLightbox();
    });
</script>
<script>
    const socket = io('http://localhost:3000');
    const userId = parseInt(localStorage.getItem("id"));
    const role = parseInt(localStorage.getItem("role"));
    const username = localStorage.getItem("username");

    let activeReceiver = null;

    socket.emit('join', userId);
function absPath(p){
    if(!p || !p.trim()) return `${window.location.origin}/uploads/profile/default.png`;
    return `${window.location.origin}/${p.replace(/^\/+/, '')}`;
  }
  function handleImgErr(img){
    const tried = img.dataset.tried || '';
    if(!tried){
      if (img.src.match(/\.jpg(\?|$)/i)) {
        img.src = img.src.replace(/\.jpg(\?|$)/i, '.png$1');
      } else {
        img.src = img.src.replace(/\.png(\?|$)/i, '.jpg$1');
      }
      img.dataset.tried = '1';
    } else {
      img.src = `${window.location.origin}/uploads/profile/default.png`;
    }
  }
   fetch(`http://localhost:3000/users?role=${role}&id=${userId}`)
  .then(r=>r.json())
  .then(users=>{
    const userList = document.getElementById('users');
    userList.innerHTML = "";
    users.forEach(user=>{
      const profileImage = absPath(user.pp);
      const div = document.createElement('div');
      div.classList.add('user');
      div.innerHTML = `
        <img src="${profileImage}" onerror="handleImgErr(this)"
             alt="" style="width:60px;height:60px;border-radius:50%;">
        ${user.username}
      `;
      div.onclick = () => {
        activeReceiver = user.id;
        document.getElementById("messages").innerHTML = "";
        fetchMessages(user.id);
      };
      userList.appendChild(div);
    });
  });
function uid() {
  return 'm_' + Math.random().toString(36).slice(2, 10);
}
    function sendMessage() {
    const msg = document.getElementById("messageInput").value;
    if (!msg || !activeReceiver) return;

    const tempId = uid();

    appendMessage(msg, 'right', { tempId, status: 'sent' });

    socket.emit('send_message', {
        tempId,
        sender_id: userId,
        receiver_id: activeReceiver,
        message: msg
    });

    document.getElementById("messageInput").value = "";
}

    socket.on('receive_message', (data) => {
        if (parseInt(data.sender_id) === activeReceiver) {
            appendMessage(data.message, 'left');
        }
    });

    function isImage(mime){ return /^image\//.test(mime); }
    function isPDF(mime){ return mime === 'application/pdf'; }

    function appendMessage(message, side, opts = {}) {
        const {
            message_id = null,
            tempId = null,
            status = null,
            message_type = 'text',
            file_url = null,
            file_name = null,
            file_mime = null,
            file_size = null
        } = opts;

        const div = document.createElement("div");
        div.classList.add("message", side);
        if (message_id) div.dataset.messageId = message_id;
        if (tempId) div.dataset.tempId = tempId;

        let body;

        if (message_type === 'file' && file_url) {
            body = document.createElement("div");

            if (file_mime && isImage(file_mime)) {
                const img = document.createElement('img');
                img.src = file_url;
                img.alt = file_name || 'image';
                img.className = 'preview';
                img.addEventListener('click', () => openLightbox(file_url, file_name || 'image'));
                body.appendChild(img);

                const actions = document.createElement('div');
                actions.style.marginTop = '6px';
                actions.style.display = 'flex';
                actions.style.gap = '10px';
                actions.style.alignItems = 'center';

                const down = document.createElement('a');
                down.href = file_url;
                down.download = 'image';
                down.className = 'file-link';
                down.textContent = 'İndir';
                actions.appendChild(down);

                if (file_name) {
                    const cap = document.createElement('span');
                    cap.style.fontSize = '12px';
                    cap.style.opacity = '.8';
                    actions.appendChild(cap);
                }
                body.appendChild(actions);
            } else if (file_mime && isPDF(file_mime)) {
                const a = document.createElement('a');
                a.href = file_url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = 'PDF dosyasını aç';
                body.appendChild(a);

                const embed = document.createElement('iframe');
                embed.src = file_url;
                embed.style.width = '260px';
                embed.style.height = '200px';
                embed.style.border = 'none';
                embed.style.borderRadius = '8px';
                embed.style.marginTop = '8px';
                body.appendChild(embed);
            } else {
                const a = document.createElement('a');
                a.href = file_url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = 'Dosyayı indir';
                body.appendChild(a);
                if (file_size) {
                    const s = document.createElement('div');
                    s.style.fontSize = '12px';
                    s.textContent = `(${Math.ceil(file_size/1024)} KB)`;
                    body.appendChild(s);
                }
            }
        } else {
            body = document.createElement("div");
            body.textContent = message;
        }

        div.appendChild(body);

        if (side === 'right') {
            const st = document.createElement('span');
            st.className = 'status';
            st.textContent = status || 'gönderiliyor…';
            div.appendChild(st);
        }

        document.getElementById("messages").appendChild(div);
        div.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
socket.on('message_saved', ({ tempId, message_id, status }) => {
    const el = document.querySelector(`.message.right[data-temp-id="${tempId}"]`);
    if (el) {
        el.dataset.messageId = message_id;
        el.removeAttribute('data-temp-id');
        const st = el.querySelector('.status');
        if (st) st.textContent = status;
    }
});
    socket.on('message_status', ({ message_id, status }) => {
    const el = document.querySelector(`.message.right[data-message-id="${message_id}"]`);
    if (el) {
        const st = el.querySelector('.status');
        if (st) st.textContent = status;
    }
});
    socket.on('receive_message', (data) => {
        const from = parseInt(data.sender_id);
        const msgId = parseInt(data.message_id);

        const opts = {
            message_id: msgId,
            message_type: data.message_type || 'text',
            file_url: data.file_url || null,
            file_name: data.file_name || null,
            file_mime: data.file_mime || null,
            file_size: data.file_size || null
        };

        if (from === activeReceiver) {
            if (opts.message_type === 'file') {
                appendMessage('', 'left', opts);
            } else {
                appendMessage(data.message, 'left', opts);
            }
            socket.emit('message_delivered', { message_id: msgId });
            socket.emit('conversation_seen', { other_user_id: from });
        } else {

        }
    });
    document.getElementById('fileInput').addEventListener('change', async function(){
        const file = this.files[0];
        this.value = '';
        if (!file || !activeReceiver) return;

        const tempId = uid();

        appendMessage('', 'right', {
            tempId,
            status: 'gönderiliyor…',
            message_type: 'file',
            file_name: file.name,
            file_mime: file.type,
            file_size: file.size
        });

        const fd = new FormData();
        fd.append('file', file);
        fd.append('sender_id', userId);
        fd.append('receiver_id', activeReceiver);
        fd.append('tempId', tempId);

        try {
            const res = await fetch('http://localhost:3000/upload', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();

            if (json && json.ok) {
                const el = document.querySelector(`.message.right[data-temp-id="${tempId}"]`);
                if (el) {
                    el.dataset.messageId = json.message_id;
                    el.removeAttribute('data-temp-id');
                    const st = el.querySelector('.status');
                    if (st) st.textContent = 'sent';

                    const body = el.querySelector('div');
                    if (body) body.innerHTML = '';

                    if (/^image\//.test(json.file_mime)) {
                        const img = document.createElement('img');
                        img.src = json.file_url;
                        img.className = 'preview';
                        img.addEventListener('click', () => openLightbox(json.file_url, json.file_name || 'image'));
                        body.appendChild(img);

                        const actions = document.createElement('div');
                        actions.style.marginTop = '6px';
                        actions.style.display = 'flex';
                        actions.style.gap = '10px';
                        actions.style.alignItems = 'center';

                        const down = document.createElement('a');
                        down.href = json.file_url;
                        down.download = json.file_name || 'image';
                        down.className = 'file-link';
                        down.textContent = 'İndir';
                        actions.appendChild(down);

                        if (json.file_name) {
                            const cap = document.createElement('span');
                            cap.style.fontSize = '12px';
                            cap.style.opacity = '.8';
                            actions.appendChild(cap);
                        }
                        body.appendChild(actions);
                    } else if (json.file_mime === 'application/pdf') {
                        const a = document.createElement('a');
                        a.href = json.file_url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.textContent = json.file_name || 'PDF dosyasını aç';
                        a.className = 'file-link';
                        body.appendChild(a);

                        const embed = document.createElement('iframe');
                        embed.src = json.file_url;
                        embed.style.width = '260px';
                        embed.style.height = '200px';
                        embed.style.border = 'none';
                        embed.style.borderRadius = '8px';
                        embed.style.marginTop = '8px';
                        body.appendChild(embed);
                    } else {
                        const a = document.createElement('a');
                        a.href = json.file_url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.download = json.file_name || 'dosya';
                        a.className = 'file-link';
                        a.textContent = json.file_name || 'Dosyayı indir';
                        body.appendChild(a);
                        if (json.file_size) {
                            const s = document.createElement('div');
                            s.style.fontSize = '12px';
                            s.textContent = `(${Math.ceil(json.file_size/1024)} KB)`;
                            body.appendChild(s);
                        }
                    }
                }
            }

        } catch (err) {
            console.error(err);
            const el = document.querySelector(`.message.right[data-temp-id="${tempId}"]`);
            if (el) {
                const st = el.querySelector('.status');
                if (st) st.textContent = 'hata';
            }
            alert('Dosya yüklenemedi.');
        }
    });

    function fetchMessages(receiverId) {
        fetch(`http://localhost:3000/messages?user1=${userId}&user2=${receiverId}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(m => {
                    const side = m.sender_id == userId ? 'right' : 'left';
                    const opts = {
                        message_id: m.id,
                        message_type: m.message_type || 'text',
                        file_url: m.file_url,
                        file_name: m.file_name,
                        file_mime: m.file_mime,
                        file_size: m.file_size
                    };
                    if (side === 'right') opts.status = m.status;
                    if (opts.message_type === 'file') {
                        appendMessage('', side, opts);
                    } else {
                        appendMessage(m.message, side, opts);
                    }
                });
                socket.emit('conversation_seen', { other_user_id: receiverId });
            });
}
</script>
</body>
</html>