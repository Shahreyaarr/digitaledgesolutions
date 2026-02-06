/**
 * DigitalEdgeSolutions - Real-time Communication Server
 * Socket.io server for chat, video calls, and notifications
 */

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const jwt = require('jsonwebtoken');
const { createClient } = require('redis');

// Configuration
const PORT = process.env.PORT || 3001;
const JWT_SECRET = process.env.JWT_SECRET || 'your-super-secret-jwt-key-change-in-production';
const REDIS_URL = process.env.REDIS_URL || 'redis://localhost:6379';

// Initialize Express app
const app = express();
app.use(cors());
app.use(express.json());

// Create HTTP server
const server = http.createServer(app);

// Initialize Socket.io
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    },
    transports: ['websocket', 'polling']
});

// Redis client for pub/sub and session storage
let redisClient;
(async () => {
    try {
        redisClient = createClient({ url: REDIS_URL });
        await redisClient.connect();
        console.log('Connected to Redis');
    } catch (error) {
        console.log('Redis not available, using in-memory storage');
    }
})();

// In-memory storage (fallback when Redis is not available)
const connectedUsers = new Map();
const activeRooms = new Map();
const userSockets = new Map();

// JWT Authentication middleware for Socket.io
io.use(async (socket, next) => {
    try {
        const token = socket.handshake.auth.token || socket.handshake.query.token;
        
        if (!token) {
            return next(new Error('Authentication required'));
        }
        
        const decoded = jwt.verify(token, JWT_SECRET);
        socket.userId = decoded.sub;
        socket.userEmail = decoded.email;
        socket.userRole = decoded.role;
        
        next();
    } catch (error) {
        next(new Error('Invalid token'));
    }
});

// Socket connection handler
io.on('connection', (socket) => {
    console.log(`User connected: ${socket.userId} (${socket.userEmail})`);
    
    // Store user connection
    connectedUsers.set(socket.userId, {
        socketId: socket.id,
        userId: socket.userId,
        email: socket.userEmail,
        role: socket.userRole,
        status: 'online',
        lastActivity: Date.now()
    });
    
    userSockets.set(socket.userId, socket);
    
    // Update user status in Redis if available
    if (redisClient) {
        redisClient.hSet(`user:${socket.userId}`, {
            socketId: socket.id,
            status: 'online',
            lastActivity: Date.now().toString()
        });
        redisClient.expire(`user:${socket.userId}`, 86400); // 24 hours
    }
    
    // Broadcast user online status
    socket.broadcast.emit('user:online', {
        userId: socket.userId,
        status: 'online'
    });
    
    // ==================== CHAT HANDLERS ====================
    
    // Join a chat room
    socket.on('room:join', async (data) => {
        const { roomId, roomType = 'group' } = data;
        
        socket.join(roomId);
        
        // Store room membership
        if (!activeRooms.has(roomId)) {
            activeRooms.set(roomId, {
                id: roomId,
                type: roomType,
                members: new Set(),
                createdAt: Date.now()
            });
        }
        
        const room = activeRooms.get(roomId);
        room.members.add(socket.userId);
        
        // Notify other members
        socket.to(roomId).emit('room:user-joined', {
            userId: socket.userId,
            email: socket.userEmail,
            timestamp: Date.now()
        });
        
        // Send room members list
        const members = Array.from(room.members).map(userId => {
            const user = connectedUsers.get(userId);
            return user ? {
                userId: user.userId,
                email: user.email,
                status: user.status
            } : null;
        }).filter(Boolean);
        
        socket.emit('room:members', { roomId, members });
        
        console.log(`User ${socket.userId} joined room: ${roomId}`);
    });
    
    // Leave a chat room
    socket.on('room:leave', (data) => {
        const { roomId } = data;
        
        socket.leave(roomId);
        
        const room = activeRooms.get(roomId);
        if (room) {
            room.members.delete(socket.userId);
            
            socket.to(roomId).emit('room:user-left', {
                userId: socket.userId,
                email: socket.userEmail,
                timestamp: Date.now()
            });
        }
        
        console.log(`User ${socket.userId} left room: ${roomId}`);
    });
    
    // Send message
    socket.on('message:send', async (data) => {
        const { roomId, content, messageType = 'text', replyTo = null, fileUrl = null } = data;
        
        const message = {
            id: generateId(),
            roomId,
            senderId: socket.userId,
            senderEmail: socket.userEmail,
            content,
            messageType,
            replyTo,
            fileUrl,
            timestamp: Date.now(),
            edited: false
        };
        
        // Store message in Redis if available
        if (redisClient) {
            await redisClient.lPush(`room:${roomId}:messages`, JSON.stringify(message));
            await redisClient.lTrim(`room:${roomId}:messages`, 0, 999); // Keep last 1000 messages
        }
        
        // Broadcast to room members
        io.to(roomId).emit('message:received', message);
        
        console.log(`Message sent in room ${roomId} by ${socket.userId}`);
    });
    
    // Edit message
    socket.on('message:edit', async (data) => {
        const { messageId, roomId, newContent } = data;
        
        const editData = {
            messageId,
            roomId,
            newContent,
            editedAt: Date.now(),
            editedBy: socket.userId
        };
        
        io.to(roomId).emit('message:edited', editData);
    });
    
    // Delete message
    socket.on('message:delete', (data) => {
        const { messageId, roomId } = data;
        
        io.to(roomId).emit('message:deleted', {
            messageId,
            deletedBy: socket.userId,
            deletedAt: Date.now()
        });
    });
    
    // Typing indicator
    socket.on('typing:start', (data) => {
        const { roomId } = data;
        socket.to(roomId).emit('typing:started', {
            userId: socket.userId,
            email: socket.userEmail,
            roomId
        });
    });
    
    socket.on('typing:stop', (data) => {
        const { roomId } = data;
        socket.to(roomId).emit('typing:stopped', {
            userId: socket.userId,
            roomId
        });
    });
    
    // Reaction to message
    socket.on('message:react', (data) => {
        const { messageId, roomId, reaction } = data;
        
        io.to(roomId).emit('message:reaction', {
            messageId,
            userId: socket.userId,
            reaction,
            timestamp: Date.now()
        });
    });
    
    // ==================== VIDEO CALL HANDLERS ====================
    
    // Initiate video call
    socket.on('call:initiate', (data) => {
        const { roomId, callType = 'video', participants = [] } = data;
        
        const callData = {
            callId: generateId(),
            roomId,
            initiator: socket.userId,
            callType,
            participants: [socket.userId, ...participants],
            startedAt: Date.now(),
            status: 'ringing'
        };
        
        // Store call info
        activeRooms.set(`call:${callData.callId}`, callData);
        
        // Notify participants
        participants.forEach(participantId => {
            const participantSocket = userSockets.get(participantId);
            if (participantSocket) {
                participantSocket.emit('call:incoming', callData);
            }
        });
        
        socket.emit('call:initiated', callData);
    });
    
    // Accept call
    socket.on('call:accept', (data) => {
        const { callId } = data;
        const call = activeRooms.get(`call:${callId}`);
        
        if (call) {
            call.status = 'ongoing';
            
            // Notify all participants
            call.participants.forEach(participantId => {
                const participantSocket = userSockets.get(participantId);
                if (participantSocket) {
                    participantSocket.emit('call:accepted', {
                        callId,
                        acceptedBy: socket.userId,
                        acceptedAt: Date.now()
                    });
                }
            });
        }
    });
    
    // Reject call
    socket.on('call:reject', (data) => {
        const { callId } = data;
        const call = activeRooms.get(`call:${callId}`);
        
        if (call) {
            call.status = 'rejected';
            
            const initiatorSocket = userSockets.get(call.initiator);
            if (initiatorSocket) {
                initiatorSocket.emit('call:rejected', {
                    callId,
                    rejectedBy: socket.userId,
                    rejectedAt: Date.now()
                });
            }
        }
    });
    
    // End call
    socket.on('call:end', (data) => {
        const { callId } = data;
        const call = activeRooms.get(`call:${callId}`);
        
        if (call) {
            call.status = 'ended';
            call.endedAt = Date.now();
            
            // Notify all participants
            call.participants.forEach(participantId => {
                const participantSocket = userSockets.get(participantId);
                if (participantSocket) {
                    participantSocket.emit('call:ended', {
                        callId,
                        endedBy: socket.userId,
                        endedAt: Date.now(),
                        duration: call.endedAt - call.startedAt
                    });
                }
            });
            
            activeRooms.delete(`call:${callId}`);
        }
    });
    
    // WebRTC signaling
    socket.on('webrtc:offer', (data) => {
        const { targetUserId, offer } = data;
        const targetSocket = userSockets.get(targetUserId);
        
        if (targetSocket) {
            targetSocket.emit('webrtc:offer', {
                offer,
                from: socket.userId
            });
        }
    });
    
    socket.on('webrtc:answer', (data) => {
        const { targetUserId, answer } = data;
        const targetSocket = userSockets.get(targetUserId);
        
        if (targetSocket) {
            targetSocket.emit('webrtc:answer', {
                answer,
                from: socket.userId
            });
        }
    });
    
    socket.on('webrtc:ice-candidate', (data) => {
        const { targetUserId, candidate } = data;
        const targetSocket = userSockets.get(targetUserId);
        
        if (targetSocket) {
            targetSocket.emit('webrtc:ice-candidate', {
                candidate,
                from: socket.userId
            });
        }
    });
    
    // ==================== NOTIFICATION HANDLERS ====================
    
    // Send notification to specific user
    socket.on('notification:send', async (data) => {
        const { targetUserId, title, message, type = 'info', link = null } = data;
        
        const notification = {
            id: generateId(),
            userId: targetUserId,
            title,
            message,
            type,
            link,
            read: false,
            createdAt: Date.now()
        };
        
        // Store in Redis
        if (redisClient) {
            await redisClient.lPush(`user:${targetUserId}:notifications`, JSON.stringify(notification));
        }
        
        // Send to target user if online
        const targetSocket = userSockets.get(targetUserId);
        if (targetSocket) {
            targetSocket.emit('notification:received', notification);
        }
    });
    
    // Mark notification as read
    socket.on('notification:read', async (data) => {
        const { notificationId } = data;
        
        // Update in Redis
        if (redisClient) {
            const notifications = await redisClient.lRange(`user:${socket.userId}:notifications`, 0, -1);
            
            for (let i = 0; i < notifications.length; i++) {
                const notification = JSON.parse(notifications[i]);
                if (notification.id === notificationId) {
                    notification.read = true;
                    await redisClient.lSet(`user:${socket.userId}:notifications`, i, JSON.stringify(notification));
                    break;
                }
            }
        }
    });
    
    // ==================== PRESENCE HANDLERS ====================
    
    // Update user status
    socket.on('presence:update', (data) => {
        const { status } = data;
        
        const user = connectedUsers.get(socket.userId);
        if (user) {
            user.status = status;
            user.lastActivity = Date.now();
        }
        
        // Update in Redis
        if (redisClient) {
            redisClient.hSet(`user:${socket.userId}`, {
                status,
                lastActivity: Date.now().toString()
            });
        }
        
        // Broadcast status change
        socket.broadcast.emit('user:status-changed', {
            userId: socket.userId,
            status
        });
    });
    
    // Get online users
    socket.on('users:online', () => {
        const onlineUsers = Array.from(connectedUsers.values()).map(user => ({
            userId: user.userId,
            email: user.email,
            status: user.status
        }));
        
        socket.emit('users:online-list', onlineUsers);
    });
    
    // ==================== DISCONNECT HANDLER ====================
    
    socket.on('disconnect', async (reason) => {
        console.log(`User disconnected: ${socket.userId} (${reason})`);
        
        // Update user status
        const user = connectedUsers.get(socket.userId);
        if (user) {
            user.status = 'offline';
            user.lastActivity = Date.now();
        }
        
        // Update in Redis
        if (redisClient) {
            await redisClient.hSet(`user:${socket.userId}`, {
                status: 'offline',
                lastActivity: Date.now().toString()
            });
        }
        
        // Remove from active rooms
        activeRooms.forEach((room, roomId) => {
            if (room.members && room.members.has(socket.userId)) {
                room.members.delete(socket.userId);
                socket.to(roomId).emit('room:user-left', {
                    userId: socket.userId,
                    email: socket.userEmail,
                    timestamp: Date.now()
                });
            }
        });
        
        // Broadcast offline status
        socket.broadcast.emit('user:offline', {
            userId: socket.userId,
            status: 'offline'
        });
        
        // Clean up
        connectedUsers.delete(socket.userId);
        userSockets.delete(socket.userId);
    });
});

// Helper function to generate unique IDs
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// API Routes

// Get room messages
app.get('/api/rooms/:roomId/messages', async (req, res) => {
    const { roomId } = req.params;
    const { limit = 50, offset = 0 } = req.query;
    
    try {
        let messages = [];
        
        if (redisClient) {
            const rawMessages = await redisClient.lRange(`room:${roomId}:messages`, parseInt(offset), parseInt(offset) + parseInt(limit) - 1);
            messages = rawMessages.map(msg => JSON.parse(msg)).reverse();
        }
        
        res.json({
            success: true,
            data: messages
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Get user notifications
app.get('/api/users/:userId/notifications', async (req, res) => {
    const { userId } = req.params;
    const { limit = 20 } = req.query;
    
    try {
        let notifications = [];
        
        if (redisClient) {
            const rawNotifications = await redisClient.lRange(`user:${userId}:notifications`, 0, parseInt(limit) - 1);
            notifications = rawNotifications.map(notif => JSON.parse(notif));
        }
        
        res.json({
            success: true,
            data: notifications
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            message: error.message
        });
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        timestamp: Date.now(),
        connectedUsers: connectedUsers.size,
        activeRooms: activeRooms.size
    });
});

// Start server
server.listen(PORT, () => {
    console.log(`🚀 DigitalEdgeSolutions Real-time Server running on port ${PORT}`);
    console.log(`📡 WebSocket endpoint: ws://localhost:${PORT}`);
});

module.exports = { app, server, io };
