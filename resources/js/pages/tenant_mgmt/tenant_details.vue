<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { type BreadcrumbItem } from '../../types';

const props = defineProps<{
    tenant: {
        id: string;
        name: string;
        email: string;
        created_at: string;
        data: any;
    };
    users: {
        id: number;
        name: string;
        email: string;
    }[];
    currentOwner: {
        id: number;
        name: string;
        email: string;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tenant Settings',
        href: route('tenant.settings', { tenant: props.tenant.id }),
    },
];

// Form for tenant details
const tenantForm = useForm({
    name: props.tenant.name,
    email: props.tenant.email,
});

// Form for changing owner
const ownerForm = useForm({
    user_id: props.currentOwner?.id || '',
});

// Submit tenant details form
function updateTenant() {
    tenantForm.put(route('tenant.settings.update', { tenant: props.tenant.id }), {
        preserveScroll: true,
        onSuccess: () => {
            tenantForm.reset();
        },
    });
}

// Submit owner change form
function changeOwner() {
    if (confirm('Are you sure you want to change the tenant owner? This is a significant change.')) {
        ownerForm.put(route('tenant.settings.change-owner', { tenant: props.tenant.id }), {
            preserveScroll: true,
            onSuccess: () => {
                ownerForm.reset();
            },
        });
    }
}

// Format date helper
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Delete tenant function
function deleteTenant() {
    if (confirm('WARNING: Are you sure you want to delete this tenant? This action CANNOT be undone and will delete all tenant data.')) {
        if (confirm('Please confirm again that you want to delete this tenant. All data will be permanently lost.')) {
            router.delete(route('tenant.settings.delete', { tenant: props.tenant.id }), {
                preserveScroll: true,
            });
        }
    }
}
</script>

<template>
    <Head title="Tenant Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Tenant Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Tenant Settings</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage your tenant information and settings</p>
                    </div>
                    <span class="px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100 rounded-full">
                        ID: {{ tenant.id }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tenant Details Form -->
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Tenant Details</h3>
                        
                        <form @submit.prevent="updateTenant" class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input
                                    id="name"
                                    v-model="tenantForm.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                    required
                                />
                                <InputError :message="tenantForm.errors.name ? tenantForm.errors.name[0] : null" />
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                <input
                                    id="email"
                                    v-model="tenantForm.email"
                                    type="email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                    required
                                />
                                <InputError :message="tenantForm.errors.name ? tenantForm.errors.name[0] : null" />                                
                                <div v-if="tenantForm.errors.email" class="text-red-500 text-sm mt-1">
                                    {{ tenantForm.errors.email }}
                                </div>
                            </div>
                            
                            <div>
                                <button
                                    type="submit"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800"
                                    :disabled="tenantForm.processing"
                                >
                                    Update Tenant Information
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Tenant Owner Form -->
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Tenant Ownership</h3>
                        
                        <div class="mb-4">
                            <div class="flex items-center mb-2">
                                <div class="mr-4 text-sm font-medium text-gray-700 dark:text-gray-300">Current Owner:</div>
                                <div v-if="currentOwner" class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mr-2">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                            {{ currentOwner.name.charAt(0).toUpperCase() }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ currentOwner.name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ currentOwner.email }}</div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-yellow-600 dark:text-yellow-400">
                                    No owner assigned
                                </div>
                            </div>
                        </div>
                        
                        <form @submit.prevent="changeOwner" class="space-y-4">
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Change Owner To</label>
                                <select
                                    id="user_id"
                                    v-model="ownerForm.user_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                    required
                                >
                                    <option value="" disabled>Select a user</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                                <div v-if="ownerForm.errors.user_id" class="text-red-500 text-sm mt-1">
                                    {{ ownerForm.errors.user_id }}
                                </div>
                            </div>
                            
                            <div>
                                <button
                                    type="submit"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 dark:focus:ring-offset-gray-800"
                                    :disabled="ownerForm.processing"
                                >
                                    Change Tenant Owner
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Tenant Information -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Tenant Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tenant ID</div>
                        <div class="text-base font-medium text-gray-900 dark:text-gray-100">{{ tenant.id }}</div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</div>
                        <div class="text-base font-medium text-gray-900 dark:text-gray-100">{{ formatDate(tenant.created_at) }}</div>
                    </div>
                    
                    <!-- Add more tenant information as needed -->
                </div>
                
                <div class="mt-6" v-if="tenant.data">
                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-2">Additional Information</h4>
                    <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <pre class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ JSON.stringify(tenant.data, null, 2) }}</pre>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-900/50 p-6 mt-6">
                <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-4">Danger Zone</h3>
                
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800/30 p-6">
                    <h4 class="text-md font-medium text-red-700 dark:text-red-300 mb-2">Delete Tenant</h4>
                    <p class="text-sm text-red-600 dark:text-red-400 mb-4">
                        Permanently delete this tenant and all of its data. This action cannot be undone.
                        All users, files, and other tenant data will be permanently removed.
                    </p>
                    
                    <button
                        type="button"
                        @click="deleteTenant"
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800"
                    >
                        Delete Tenant
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
