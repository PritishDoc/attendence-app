# Socket Server — Phase 2

This directory will contain the Node.js + Socket.io real-time tracking server.

## Planned Features
- Live employee location broadcasting
- Real-time dashboard updates
- Movement tracking and route recording
- Active employee status

## Setup (Phase 2)
```bash
npm init -y
npm install express socket.io mysql2 cors
node server.js
```

## Architecture
```
Employee Browser (Geolocation API)
        ↓
Socket.io Client
        ↓
Node.js Server (port 3001)
        ↓
Admin Dashboard (Live Map Updates)
```
