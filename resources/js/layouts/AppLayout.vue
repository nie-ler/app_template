<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import FlashMessage from '@/components/FlashMessage.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <FlashMessage />
        <slot />
    </AppLayout>
</template>
