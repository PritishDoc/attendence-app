/**
 * Attendify — Geolocation Helpers
 */

const Geo = {
    getCurrentPosition(options = {}) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported by this browser'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy }),
                (err) => {
                    const messages = { 1: 'Location permission denied', 2: 'Location unavailable', 3: 'Location request timed out' };
                    reject(new Error(messages[err.code] || 'Unknown geolocation error'));
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000, ...options }
            );
        });
    },

    // Haversine distance in meters
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    },

    async isWithinRadius(officeLat, officeLng, radius) {
        const pos = await this.getCurrentPosition();
        const distance = this.calculateDistance(pos.latitude, pos.longitude, officeLat, officeLng);
        return { withinRadius: distance <= radius, distance: Math.round(distance), position: pos };
    },

    watchPosition(callback) {
        if (!navigator.geolocation) return null;
        return navigator.geolocation.watchPosition(
            (pos) => callback({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy, speed: pos.coords.speed }),
            (err) => console.error('Watch error:', err),
            { enableHighAccuracy: true, maximumAge: 5000 }
        );
    },

    clearWatch(watchId) {
        if (watchId && navigator.geolocation) navigator.geolocation.clearWatch(watchId);
    }
};

window.Geo = Geo;
