# Mascot WebM set

Files:
- idle.webm - 10s loop, the default state. Mascot stays in one place, soft breathing/blink only.
- wave.webm - 5s one-shot
- wink.webm - 5s one-shot
- juggle.webm - 5s one-shot
- bounce.webm - 5s one-shot
- MascotPlayer.vue - Vue 3 component that loops idle, then every 8 to 15 seconds (random) plays a random variant once, then returns to idle. It never plays the same variant twice in a row.

All clips: 1040x768, 15 fps, VP9 with alpha (transparent). They sit cleanly over any background in Chromium / Android WebView.

Install:
1. Copy the 5 .webm files into public/mascot/ in your app.
2. Copy MascotPlayer.vue into your components folder (for example src/components/MascotPlayer.vue).
3. Use it in your main layout, fixed bottom-right, 220px wide, so it shows on every page without blocking clicks:
   <MascotPlayer />
   Add pointer-events: none to the video element if you do not want it to intercept clicks, and keep the width at 220px on desktop, smaller on mobile.
4. Run npm run build and check in light and dark mode: the mascot should show with no white box around it.
