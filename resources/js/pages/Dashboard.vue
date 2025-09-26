<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    userCount: number;
    recentUsers: Array<{
        id: number;
        name: string;
        email: string;
        created_at: string;
        role: string | null;
        verified: boolean;
    }>;
    currentPlan: {
        id: number;
        name: string;
        description: string;
        features: Array<{
            code: string;
            name: string;
            value: string;
        }>;
    } | null;
    hasActiveSubscription: boolean;
    tenant: {
        id: string;
        name: string;
        email: string;
        tenant_slug: string;
    };
    currentUser: {
        id: number;
        name: string;
        email: string;
        current_role: string | null;
    };
    availableRoles: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
];

const selectedRole = ref(props.currentUser.current_role || '');

function updateUserRole() {
    router.post(route('dashboard.update-role'), {
        role: selectedRole.value
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <!-- Payment Notification Banner -->
            <div v-if="!hasActiveSubscription" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-medium text-red-800">Subscription Required</h3>
                            <p class="text-sm text-red-700">Please complete your payment to continue using all features.</p>
                        </div>
                    </div>
                    <div>
                        <a
                            href="/checkout"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Complete Payment
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- User Count Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Total Users</h3>
                        <span class="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100 p-2 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                        {{ userCount }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        in tenant {{ tenant.name }}
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="route('users.index', { tenant: tenant.id })" 
                            class="text-sm text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 flex items-center"
                        >
                            <span>View all users</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Current Plan Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Current Plan</h3>
                        <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100 p-2 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </span>
                    </div>
                    <div v-if="currentPlan" class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                        {{ currentPlan.name }}
                    </div>
                    <div v-else class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2">
                        No Active Plan
                    </div>
                    <div v-if="currentPlan" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ currentPlan.description }}
                    </div>
                    <div v-else class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        No subscription found
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="route('tenant.subscription.index', { tenant: tenant.id })" 
                            class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center"
                        >
                            <span>Manage subscription</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Developer Tools Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Developer Tools</h3>
                        <span class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100 p-2 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2">
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Switch Role (Development)</label>
                        <div class="mt-1 flex items-center gap-2">
                            <select 
                                v-model="selectedRole" 
                                id="role"
                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                            >
                                <option value="">No Role</option>
                                <option v-for="role in availableRoles" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <button 
                                @click="updateUserRole" 
                                class="inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                            >
                                Update
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Current Role: {{ currentUser.current_role || 'None' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Recent Users Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 md:col-span-2">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Recent Users</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Last 3 users added to your tenant</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Name</span>
                                    </th>
                                    <th class="px-6 py-3 text-left">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Email</span>
                                    </th>
                                    <th class="px-6 py-3 text-left">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Role</span>
                                    </th>
                                    <th class="px-6 py-3 text-left">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</span>
                                    </th>
                                    <th class="px-6 py-3 text-left">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Date</span>
                                    </th>
                                    <th class="px-6 py-3 text-right">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Action</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <tr v-for="user in recentUsers" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                                    {{ user.name ? user.name.charAt(0).toUpperCase() : '?' }}
                                                </span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ user.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ user.role || 'No role' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="user.verified" 
                                              class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">
                                            Verified
                                        </span>
                                        <span v-else 
                                              class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100">
                                            Unverified
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ user.created_at }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            :href="route('users.edit', { tenant: tenant.id, user: user.id })"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="recentUsers.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        No users found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <Link 
                            :href="route('users.index', { tenant: tenant.id })" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add New User
                        </Link>
                    </div>
                </div>

                <!-- Quick Links Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Quick Links</h2>
                    
                    <div class="space-y-4">
                        <Link 
                            :href="route('tenant.logs.index', { tenant: tenant.id })" 
                            class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-800 dark:text-blue-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100">Tenant Logs</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">View activity and audit logs</p>
                            </div>
                        </Link>
                        
                        <Link 
                            :href="route('users.index', { tenant: tenant.id })" 
                            class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-800 dark:text-purple-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100">User Management</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Manage tenant users and permissions</p>
                            </div>
                        </Link>
                        
                        <Link 
                            :href="route('tenant.settings', { tenant: tenant.id })" 
                            class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                        >
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-800 dark:text-green-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium text-gray-900 dark:text-gray-100">Tenant Settings</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Configure tenant preferences and subscription</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
