<template>
  <!-- Put idle.webm, wave.webm, wink.webm, juggle.webm, bounce.webm in public/mascot/ -->
  <video
    ref="videoEl"
    src="/mascot/idle.webm"
    autoplay
    loop
    muted
    playsinline
    class="mascot-video"
  />
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const videoEl = ref(null)
const variants = ['wave', 'wink', 'juggle', 'bounce']
let last = ''
let timer = null

function playIdle() {
  const v = videoEl.value
  if (!v) return
  v.src = '/mascot/idle.webm'
  v.loop = true
  v.play().catch(() => {})
}

function playRandom() {
  const v = videoEl.value
  if (!v) return
  let next
  do {
    next = variants[Math.floor(Math.random() * variants.length)]
  } while (next === last && variants.length > 1)
  last = next
  v.loop = false
  v.src = `/mascot/${next}.webm`
  v.play().catch(() => {})
  v.onended = () => {
    playIdle()
    schedule()
  }
}

function schedule() {
  clearTimeout(timer)
  // random 8 to 15 seconds, so it feels alive, not mechanical
  timer = setTimeout(playRandom, 8000 + Math.random() * 7000)
}

onMounted(() => {
  playIdle()
  schedule()
})

onUnmounted(() => {
  clearTimeout(timer)
})
</script>

<style scoped>
.mascot-video {
  width: 220px;
  height: auto;
  display: block;
  /* transparent WebM sits cleanly over any background */
}
</style>
