<template>
  <div class="otp-row">
    <input
      v-for="(_, i) in length"
      :key="i"
      :ref="'box' + i"
      :type="type"
      inputmode="numeric"
      autocomplete="one-time-code"
      maxlength="1"
      class="input otp-box"
      :value="digits[i]"
      @input="onInput(i, $event)"
      @keydown="onKeydown(i, $event)"
      @paste="onPaste"
      @focus="$event.target.select()"
    />
  </div>
</template>

<script>
export default {
  name: 'InputOtp',
  props: {
    modelValue: { type: String, default: '' },
    length: { type: Number, default: 4 },
    type: { type: String, default: 'password' },
  },
  emits: ['update:modelValue', 'enter'],
  data() {
    return { digits: Array.from({ length: this.length }, () => '') };
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(v) {
        const s = String(v || '').replace(/\D/g, '').slice(0, this.length);
        this.digits = Array.from({ length: this.length }, (_, i) => s[i] || '');
      },
    },
  },
  methods: {
    focusBox(i) {
      // Refs inside v-for are arrays in Vue 3 — unwrap them.
      const r = this.$refs['box' + i];
      const el = Array.isArray(r) ? r[0] : r;
      if (el) {
        el.focus();
        if (el.select) el.select();
      }
    },
    emitValue() {
      this.$emit('update:modelValue', this.digits.join(''));
    },
    onInput(i, e) {
      const v = String(e.target.value || '').replace(/\D/g, '').slice(-1);
      this.digits[i] = v;
      this.emitValue();
      if (v && i < this.length - 1) {
        const next = i + 1;
        this.$nextTick(() => this.focusBox(next));
      }
    },
    onKeydown(i, e) {
      if (e.key === 'Backspace' && !this.digits[i] && i > 0) this.focusBox(i - 1);
      if (e.key === 'Enter') this.$emit('enter');
    },
    onPaste(e) {
      e.preventDefault();
      const t = String(e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, this.length);
      t.split('').forEach((c, j) => { this.digits[j] = c; });
      this.emitValue();
      if (t.length) this.focusBox(Math.min(t.length, this.length - 1));
    },
  },
};
</script>
