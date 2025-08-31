<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/solid';

const show = ref(false);
const timeout = ref<NodeJS.Timeout | null>(null);

const page = usePage();

const flash = computed(() => ({
  success: page.props.flash?.success,
  error: page.props.flash?.error,
  errors: Object.keys(page.props.errors || {}).length > 0 
    ? Object.values(page.props.errors).flat() 
    : null
}));

const message = computed(() => 
  flash.value.success || flash.value.error || (flash.value.errors && flash.value.errors[0])
);

const type = computed(() => {
  if (flash.value.success) return 'success';
  if (flash.value.error) return 'error';
  if (flash.value.errors) return 'error';
  return null;
});

function dismiss() {
  show.value = false;
  if (timeout.value) {
    clearTimeout(timeout.value);
  }
}

onMounted(() => {
  console.log('Flash props:', page.props.flash); // #TODO remove
  console.log('Message value:', message.value);
  console.log('All page props:', page.props);
  if (message.value) {
    show.value = true;
    // Auto dismiss after 5 seconds
    timeout.value = setTimeout(() => {
      show.value = false;
    }, 5000);
  }
});
</script>

<template>
  <div
    v-if="show && message"
    class="fixed inset-x-0 top-4 z-50 flex justify-center px-4 py-2"
  >
    <div
      class="flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg"
      :class="{
        'bg-green-50 text-green-800 dark:bg-green-900/50 dark:text-green-100': type === 'success',
        'bg-red-50 text-red-800 dark:bg-red-900/50 dark:text-red-100': type === 'error'
      }"
    >
      <div class="flex items-center gap-2">
        <CheckCircleIcon v-if="type === 'success'" class="h-5 w-5 text-green-400 dark:text-green-300" />
        <ExclamationCircleIcon v-if="type === 'error'" class="h-5 w-5 text-red-400 dark:text-red-300" />
        <p class="text-sm font-medium">{{ message }}</p>
      </div>
      <button
        @click="dismiss"
        class="ml-4 rounded-md p-1 hover:bg-black/5 dark:hover:bg-white/5"
        :class="{
          'text-green-600 dark:text-green-400': type === 'success',
          'text-red-600 dark:text-red-400': type === 'error'
        }"
      >
        <XMarkIcon class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>
