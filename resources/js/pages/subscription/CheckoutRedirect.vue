<script setup>
import { onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

const { props } = usePage()

onMounted(() => {
  if (props.checkoutUrl) {
    window.location.href = props.checkoutUrl
  }
})
</script>

<template>
  <div class="flex items-center justify-center h-screen">
    <p>Weiterleitung zu Stripe Checkout …</p>
  </div>
</template>
