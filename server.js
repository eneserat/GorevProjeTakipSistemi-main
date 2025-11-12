const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, { cors: { origin: '*' } });

const path = require('path');
const fs = require('fs');
const multer = require('multer');
const mime = require('mime-types');

app.use(cors());
app.use(express.json());

let db;

mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'staj'
}).then(connection => {
    db = connection;
    console.log('MySQL bağlantısı başarılı');
}).catch(err => {
    console.error('MySQL bağlantı hatası:', err);
});

const UPLOAD_DIR = path.join(__dirname, 'uploads', 'chat');
fs.mkdirSync(UPLOAD_DIR, { recursive: true });

app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

const storage = multer.diskStorage({
    destination: (req, file, cb) => cb(null, UPLOAD_DIR),
    filename: (req, file, cb) => {
        const ext = mime.extension(file.mimetype) || 'bin';
        const base = Date.now() + '_' + Math.random().toString(36).slice(2,8);
        cb(null, base + '.' + ext);
    }
});

const allowed = [
    'image/png','image/jpeg','image/gif','image/webp','image/svg+xml',
    'application/pdf','text/plain',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
];

const fileFilter = (req, file, cb) => {
    if (allowed.includes(file.mimetype)) cb(null, true);
    else cb(new Error('Desteklenmeyen dosya türü'), false);
};

const upload = multer({
    storage,
    fileFilter,
    limits: { fileSize: 15 * 1024 * 1024 }
});
app.get('/users', async (req, res) => {
    const role = parseInt(req.query.role);
    const id = parseInt(req.query.id);

    let query = '';
    let params = [];

    if (role === 1) {
        query = 'SELECT id, username, pp FROM users WHERE id != ?';
        params = [id];
    } else {
        query = 'SELECT id, username, pp FROM users WHERE role = 1';
    }

    const [rows] = await db.execute(query, params);
    res.json(rows);
});

app.get('/messages', async (req, res) => {
    const { user1, user2 } = req.query;
    const [rows] = await db.execute(`
        SELECT * FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR
            (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    `, [user2, user1, user1, user2]);
    res.json(rows);
});
app.post('/upload', upload.single('file'), async (req, res) => {
    try {
        const sender_id = parseInt(req.body.sender_id);
        const receiver_id = parseInt(req.body.receiver_id);

        if (!req.file || !sender_id || !receiver_id) {
            return res.status(400).json({ error: 'Eksik veri' });
        }

        const file_url = `/uploads/chat/${req.file.filename}`;
        const file_name = req.file.originalname;
        const file_mime = req.file.mimetype;
        const file_size = req.file.size;

        const [result] = await db.execute(
            `INSERT INTO messages (sender_id, receiver_id, message, status, message_type, file_url, file_name, file_mime, file_size)
       VALUES (?, ?, ?, ?, 'file', ?, ?, ?, ?)`,
            [sender_id, receiver_id, '', 'sent', file_url, file_name, file_mime, file_size]
        );
        const messageId = result.insertId;

        io.to(`user_${sender_id}`).emit('message_saved', {
            tempId: req.body.tempId || null,
            message_id: messageId,
            status: 'sent'
        });

        io.to(`user_${receiver_id}`).emit('receive_message', {
            message_id: messageId,
            sender_id,
            message: '',
            message_type: 'file',
            file_url,
            file_name,
            file_mime,
            file_size
        });

        const room = io.sockets.adapter.rooms.get(`user_${receiver_id}`);
        if (room && room.size > 0) {
            await db.execute(
                "UPDATE messages SET status='delivered', delivered_at=NOW() WHERE id=? AND status='sent'",
                [messageId]
            );
            io.to(`user_${sender_id}`).emit('message_status', {
                message_id: messageId,
                status: 'delivered'
            });
        }

        res.json({
            ok: true,
            message_id: messageId,
            file_url, file_name, file_mime, file_size
        });
    } catch (e) {
        console.error(e);
        res.status(500).json({ error: 'Yükleme hatası' });
    }
});
io.on('connection', (socket) => {
    console.log('Yeni kullanıcı bağlandı');

    socket.on('join', (userId) => {
        socket.data.userId = parseInt(userId);
        socket.join(`user_${userId}`);
    });

    socket.on('send_message', async (data) => {
        const { tempId, sender_id, receiver_id, message } = data;

        const [result] = await db.execute(
            'INSERT INTO messages (sender_id, receiver_id, message, status) VALUES (?, ?, ?, ?)',
            [sender_id, receiver_id, message, 'sent']
        );
        const messageId = result.insertId;

        io.to(`user_${sender_id}`).emit('message_saved', {
            tempId,
            message_id: messageId,
            status: 'sent'
        });

        io.to(`user_${receiver_id}`).emit('receive_message', {
            message_id: messageId,
            sender_id,
            message
        });

        const room = io.sockets.adapter.rooms.get(`user_${receiver_id}`);
        if (room && room.size > 0) {
            await db.execute(
                "UPDATE messages SET status='delivered', delivered_at=NOW() WHERE id=? AND status='sent'",
                [messageId]
            );
            io.to(`user_${sender_id}`).emit('message_status', {
                message_id: messageId,
                status: 'delivered'
            });
        }
    });

    socket.on('message_delivered', async ({ message_id }) => {
        await db.execute(
            "UPDATE messages SET status='delivered', delivered_at=NOW() WHERE id=? AND status='sent'",
            [message_id]
        );
        const [[row]] = await db.execute("SELECT sender_id FROM messages WHERE id=?", [message_id]);
        if (row) {
            io.to(`user_${row.sender_id}`).emit('message_status', {
                message_id, status: 'delivered'
            });
        }
    });

    socket.on('conversation_seen', async ({ other_user_id }) => {
        const me = socket.data.userId;
        await db.execute(
            "UPDATE messages SET status='seen', seen_at=NOW() WHERE sender_id=? AND receiver_id=? AND status IN ('sent','delivered')",
            [other_user_id, me]
        );
        const [seenRows] = await db.execute(
            "SELECT id FROM messages WHERE sender_id=? AND receiver_id=? AND status='seen' ORDER BY id DESC LIMIT 50",
            [other_user_id, me]
        );
        seenRows.forEach(r => {
            io.to(`user_${other_user_id}`).emit('message_status', {
                message_id: r.id, status: 'seen'
            });
        });
    });
});
server.listen(3000, () => {
    console.log('Socket server 3000 portunda çalışıyor');
});
