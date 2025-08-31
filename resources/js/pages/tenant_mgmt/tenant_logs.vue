<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { type NavItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { ref } from 'vue';

const props = defineProps<{
  logs: {
    data: any[];
    links: any[];
  };
  tenant: string;
  availableActions: string[];
  filters: {
    sort: string;
    direction: string;
    selectedActions: string[];
  };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Logs',
        href: route('tenant.logs.index', { tenant: props.tenant }),
    },
];

// Track expanded metadata rows
const expandedRows = ref<number[]>([]);

// Format metadata for display
function formatMetadata(metadata: any): string {
    try {
        if (typeof metadata === 'string') {
            // If it's already a string, try to parse it as JSON
            return JSON.stringify(JSON.parse(metadata), null, 2);
        }
        // If it's an object, stringify it
        return JSON.stringify(metadata, null, 2);
    } catch (e) {
        // If parsing fails, return as is
        return String(metadata);
    }
}

// Check if metadata should be expandable
function shouldShowExpand(metadata: any): boolean {
    if (!metadata) return false;
    const formatted = formatMetadata(metadata);
    return formatted.split('\n').length > 3 || formatted.length > 100;
}

// Toggle metadata expansion for a specific row
function toggleMetadata(logId: number) {
    const index = expandedRows.value.indexOf(logId);
    if (index === -1) {
        expandedRows.value.push(logId);
    } else {
        expandedRows.value.splice(index, 1);
    }
}

// Check if a row is expanded
function isExpanded(logId: number): boolean {
    return expandedRows.value.includes(logId);
}


// Sorting functions
function sort(field: string) {
    router.get(
        route('tenant.logs.index', { 
            tenant: props.tenant,
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
        route('tenant.logs.index', {
            tenant: props.tenant,
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
    <Head :title="`Logs - ${tenant}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Audit Logs</h2>
                    <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100 rounded-full">
                        {{ tenant }}
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
                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="items-center">
                                        <div class="text-sm text-gray-900 dark:text-gray-300">{{ log.user?.name || 'Unknown' }} #{{ log.user_id }}</div>
                                        <div class="text-xs text-gray-900 dark:text-gray-300">{{ log.user?.email || 'Unknown' }}</div>
                                    </div></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="items-center">
                                        <div class="text-sm text-gray-900 dark:text-gray-300">{{ log.referenced_user?.name || 'Unknown' }} #{{ log.referenced_user_id }}</div>
                                        <div class="text-xs text-gray-900 dark:text-gray-300">{{ log.referenced_user?.email || 'Unknown' }}</div>
                                    </div></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ formatDate(log.created_at) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col gap-2">
                                        <div :class="{ 
                                            'whitespace-pre-wrap font-mono text-xs': isExpanded(log.id),
                                            'max-w-sm truncate': !isExpanded(log.id) 
                                        }">
                                            {{ formatMetadata(log.metadata) }}
                                        </div>
                                        <div>
                                        <button
                                            v-if="shouldShowExpand(log.metadata)"
                                            @click="toggleMetadata(log.id)"
                                            class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-xs font-medium"
                                        >
                                            {{ isExpanded(log.id) ? 'Show Less' : 'Show More' }}
                                        </button>
                                        </div>
                                    </div>
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
                                        ? 'bg-blue-500 text-white'
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

