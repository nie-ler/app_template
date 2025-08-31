<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { type NavItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Central Logs',
        href: route('central.logs.index'),
    },
];

const props = defineProps<{
  logs: {
    data: any[];
    links: any[];
  };
  availableActions: string[];
  filters: {
    sort: string;
    direction: string;
    selectedActions: string[];
  };
}>();

// Sorting functions
function sort(field: string) {
    router.get(
        route('central.logs.index', {
            sort: field,
            direction: props.filters.sort === field && props.filters.direction === 'asc' ? 'desc' : 'asc',
            actions: props.filters.selectedActions
        }),
        {},
        { preserveState: true }
    );
}

// Get sort direction icon
function getSortIcon(field: string) {
    if (props.filters.sort !== field) return '↕';
    return props.filters.direction === 'asc' ? '↑' : '↓';
}

// Action filtering
const selectedActions = ref<string[]>(props.filters.selectedActions || []);

function toggleAction(action: string) {
    const index = selectedActions.value.indexOf(action);
    if (index === -1) {
        selectedActions.value.push(action);
    } else {
        selectedActions.value.splice(index, 1);
    }
    updateFilters();
}

function resetFilters() {
    selectedActions.value = [];
    updateFilters();
}

function updateFilters() {
    router.get(
        route('central.logs.index', {
            sort: props.filters.sort,
            direction: props.filters.direction,
            actions: selectedActions.value
        }),
        {},
        { preserveState: true }
    );
}

function formatDate(timestamp) {
    return new Date(timestamp).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
    });
};

</script>

<template>
    <Head title="Central Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Central Audit Logs</h2>
                    <span class="px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100 rounded-full">
                        Central System
                    </span>
                </div>
                
                <!-- Action Filter Section -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100 dark:bg-gray-800/50 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Filter by Actions</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <button
                            v-for="action in availableActions"
                            :key="action"
                            @click="toggleAction(action)"
                            :class="[
                                'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                selectedActions.includes(action)
                                    ? 'bg-purple-500 text-white hover:bg-purple-600'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            ]"
                        >
                            {{ action }}
                        </button>
                    </div>
                    <button
                        @click="resetFilters"
                        class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-full hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 transition-colors"
                    >
                        Reset Filters
                    </button>
                </div>

                <!-- Table -->
                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('id')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">ID</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('id') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('user_id')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">User</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('user_id') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('referenced_user_id')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Referenced User</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('referenced_user_id') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('action')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Action</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('action') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('created_at')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Date/Time</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('created_at') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Metadata</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ log.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">                                    <div class="items-center">
                                    <div class="text-sm text-gray-900 dark:text-gray-300">{{ log.user?.name || 'Unknown' }} #{{ log.user_id }}</div>
                                    <div class="text-xs text-gray-900 dark:text-gray-300">{{ log.user?.email || 'Unknown' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">
                                    <div class="text-sm text-gray-900 dark:text-gray-300">{{ log.referenced_user?.name || 'Unknown' }} #{{ log.referenced_user_id }}</div>
                                    <div class="text-xs text-gray-900 dark:text-gray-300">{{ log.referenced_user?.email || 'Unknown' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ formatDate(log.created_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="max-w-sm truncate">{{ log.metadata }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-center gap-2">
                        <template v-for="link in logs.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                                    link.active
                                        ? 'bg-purple-500 text-white'
                                        : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700'
                                ]"
                                preserve-scroll
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-3 py-1.5 text-sm font-medium rounded-md text-gray-400 bg-gray-100 dark:bg-gray-800 dark:text-gray-600 cursor-not-allowed"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

