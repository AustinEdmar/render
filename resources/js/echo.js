import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

if (window.Echo) {
    window.Echo.disconnect();
    delete window.Echo;
}
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: '',  // Empty key
    wsHost: 'reverb.localhost',  // Explicit hostname
    wsPort: 8081,  // Explicit port
    wssPort: 8081,  // Explicit port
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    namespace: 'App.Events'  // Make sure namespace matches your events
});

/*  window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'qqmsdqbnqvuhmij41258',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'reverb.localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8081,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8081,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    
});  */


