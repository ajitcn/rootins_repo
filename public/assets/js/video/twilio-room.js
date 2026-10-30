(function (window) {
    'use strict';

    var currentRoom = null;
    var currentLocalTracks = [];
    var connectionPromise = null;

    function dispatch(root, name, detail) {
        if (!root || typeof root.dispatchEvent !== 'function') return;
        root.dispatchEvent(new CustomEvent('rootins:video:' + name, { detail: detail || {} }));
    }

    function trackKey(track) { return track && (track.sid || track.name || track.id || 'track'); }

    function attach(track, container, participantSid, muted) {
        if (!track || !container) return;
        var key = trackKey(track);
        if (container.querySelector('[data-track-key="' + key + '"]')) return;
        var element = track.attach();
        element.className = 'twilio-video-track';
        element.setAttribute('playsinline', '');
        element.setAttribute('autoplay', '');
        element.setAttribute('data-track-key', key);
        if (participantSid) element.setAttribute('data-participant', participantSid);
        element.muted = !!muted;
        container.appendChild(element);
        container.classList.add('has-track');
        if (track.kind === 'video') container.classList.add('has-video');
        var root = container.closest('[data-appointment-id]') || container.closest('main');
        if (root) {
            root.classList.add('is-video-connected');
            var placeholder = root.querySelector('.patient-video-placeholder, .doctor-video-stage-orb');
            if (placeholder) placeholder.classList.add('is-hidden');
            if (element.play) element.play().catch(function () {});
        }
    }

    function removeTrack(track) {
        if (!track || typeof track.detach !== 'function') return;
        track.detach().forEach(function (node) { node.remove(); });
    }

    function removeParticipantTracks(container, participantSid) {
        if (!container || !participantSid) return;
        container.querySelectorAll('[data-participant="' + participantSid + '"]').forEach(function (node) { node.remove(); });
    }

    function bindPublication(publication, container, participantSid, muted) {
        if (!publication) return;
        if (publication.isSubscribed && publication.track) attach(publication.track, container, participantSid, muted);
        publication.on('subscribed', function (track) { attach(track, container, participantSid, muted); });
        publication.on('unsubscribed', removeTrack);
    }

    function bindParticipant(participant, container) {
        if (!participant || !container) return;
        participant.tracks.forEach(function (publication) { bindPublication(publication, container, participant.sid, false); });
        participant.on('trackPublished', function (publication) { bindPublication(publication, container, participant.sid, false); });
        participant.on('trackSubscribed', function (track) { attach(track, container, participant.sid, false); });
        participant.on('trackUnsubscribed', removeTrack);
    }

    function stopTracks() {
        currentLocalTracks.forEach(function (track) {
            if (track && typeof track.stop === 'function') track.stop();
        });
        currentLocalTracks = [];
    }

    function releaseConnection() {
        connectionPromise = null;
        if (currentRoom) {
            try { currentRoom.disconnect(); } catch (error) {}
        }
        currentRoom = null;
        stopTracks();
    }

    function connect(token, root) {
        if (!window.Twilio || !window.Twilio.Video) return Promise.reject(new Error('Twilio Video SDK is unavailable.'));
        var local = root && root.querySelector('[data-video-local]');
        var remote = root && root.querySelector('[data-video-remote]');
        if (!local || !remote) return Promise.reject(new Error('Video containers are missing.'));
        if (connectionPromise) return connectionPromise;
        if (currentRoom) return Promise.resolve(currentRoom);

        dispatch(root, 'connecting');
        connectionPromise = window.Twilio.Video.createLocalTracks({
            audio: true,
            video: { width: { ideal: 1280, max: 1920 }, height: { ideal: 720, max: 1080 }, frameRate: { ideal: 24, max: 30 } }
        }).then(function (tracks) {
            currentLocalTracks = tracks;
            return window.Twilio.Video.connect(token, { tracks: tracks, dominantSpeaker: true });
        }).then(function (room) {
            currentRoom = room;
            connectionPromise = null;
            room.localParticipant.tracks.forEach(function (publication) { bindPublication(publication, local, room.localParticipant.sid, true); });
            room.localParticipant.on('trackPublished', function (publication) { bindPublication(publication, local, room.localParticipant.sid, true); });
            room.participants.forEach(function (participant) { bindParticipant(participant, remote); });
            room.on('participantConnected', function (participant) { bindParticipant(participant, remote); });
            room.on('participantDisconnected', function (participant) { removeParticipantTracks(remote, participant.sid); });
            room.on('reconnecting', function (error) { dispatch(root, 'reconnecting', { error: error || null }); });
            room.on('reconnected', function () { dispatch(root, 'reconnected'); });
            room.on('disconnected', function (roomError) {
                dispatch(root, 'disconnected', { error: roomError || null });
                if (currentRoom === room) currentRoom = null;
                stopTracks();
            });
            dispatch(root, 'connected', { room: room });
            return room;
        }).catch(function (error) {
            releaseConnection();
            dispatch(root, 'failed', { error: error });
            throw error;
        });
        return connectionPromise;
    }

    window.RootinsTwilioRoom = {
        connect: connect,
        mute: function () { if (currentRoom) currentRoom.localParticipant.audioTracks.forEach(function (p) { p.track.disable(); }); },
        unmute: function () { if (currentRoom) currentRoom.localParticipant.audioTracks.forEach(function (p) { p.track.enable(); }); },
        cameraOff: function () { if (currentRoom) currentRoom.localParticipant.videoTracks.forEach(function (p) { p.track.disable(); }); },
        cameraOn: function () { if (currentRoom) currentRoom.localParticipant.videoTracks.forEach(function (p) { p.track.enable(); }); },
        disconnect: function () {
            releaseConnection();
        },
        isConnected: function () { return !!currentRoom; }
    };
}(window));
