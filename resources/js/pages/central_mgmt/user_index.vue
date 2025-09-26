<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { type NavItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Central Users',
        href: route('central.users.index'),
    },
];

const props = defineProps<{
    users: {
        data: any[];
        links: any[];
    };
    tenant: string;
    availableRoles: string[];
    availableTenants: string[];
    filters: {
        sort: string;
        direction: string;
        selectedRoles: string[];
        selectedTenants: string[];
        verificationStatus: string[];
    };
}>();

// Filter states
const selectedRoles = ref<string[]>(props.filters.selectedRoles || []);
const selectedTenants = ref<string[]>(props.filters.selectedTenants || []);
const selectedVerificationStatus = ref<string[]>(props.filters.verificationStatus || []);

// Sorting functions
function sort(field: string) {
    router.get(
        route('central.users.index', {
            sort: field,
            direction: props.filters.sort === field && props.filters.direction === 'asc' ? 'desc' : 'asc',
            roles: selectedRoles.value,
            tenants: selectedTenants.value,
            verification: selectedVerificationStatus.value
        }),
        {},
        { preserveState: true }
    );
}

function getSortIcon(field: string) {
    if (props.filters.sort !== field) return '↕';
    return props.filters.direction === 'asc' ? '↑' : '↓';
}

// Filter toggle functions
function toggleRole(role: string) {
    const index = selectedRoles.value.indexOf(role);
    if (index === -1) {
        selectedRoles.value.push(role);
    } else {
        selectedRoles.value.splice(index, 1);
    }
    updateFilters();
}

function toggleTenant(tenant: string) {
    const index = selectedTenants.value.indexOf(tenant);
    if (index === -1) {
        selectedTenants.value.push(tenant);
    } else {
        selectedTenants.value.splice(index, 1);
    }
    updateFilters();
}

function toggleVerification(status: string) {
    const index = selectedVerificationStatus.value.indexOf(status);
    if (index === -1) {
        selectedVerificationStatus.value.push(status);
    } else {
        selectedVerificationStatus.value.splice(index, 1);
    }
    updateFilters();
}

function resetFilters() {
    selectedRoles.value = [];
    selectedTenants.value = [];
    selectedVerificationStatus.value = [];
    updateFilters();
}

function updateFilters() {
    router.get(
        route('central.users.index', {
            sort: props.filters.sort,
            direction: props.filters.direction,
            roles: selectedRoles.value,
            tenants: selectedTenants.value,
            verification: selectedVerificationStatus.value
        }),
        {},
        { preserveState: true }
    );
}

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('central.users.store'))
}

function deleteUser(id) {
  if (confirm('Diesen Nutzer wirklich löschen?')) {
    router.delete(route('users.destroy', { 
      tenant: props.tenant,
      user: id
    }), {
      preserveScroll: false,
      preserveState: false,
      replace: true,
    });
  }
}

function updateUserRole(userId: number, tenantId: string, newRole: string) {
  router.post(route('users.assign-role', { user: userId, tenant: props.tenant }), {
    role: newRole
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
}
</script>

<template>
        <Head title="Central User Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- User List -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Central User Management</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage users across all tenants</p>
                    </div>
                    <span class="px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100 rounded-full">
                        {{ users.data.length }} Users
                    </span>
                </div>

                <!-- Filter Section -->
                <div class="mb-6 flex flex-wrap gap-4">
                    <!-- Tenant Filter -->
                    <div class="flex-1 p-4 bg-gray-50 rounded-lg border border-gray-100 dark:bg-gray-800/50 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Tenant</h3>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="tenant in availableTenants"
                                :key="tenant"
                                @click="toggleTenant(tenant)"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                    selectedTenants.includes(tenant)
                                        ? 'bg-purple-500 text-white hover:bg-purple-600'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ tenant }}
                            </button>
                        </div>
                    </div>

                    <!-- Role Filter -->
                    <div class="flex-1 p-4 bg-gray-50 rounded-lg border border-gray-100 dark:bg-gray-800/50 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Role</h3>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="role in availableRoles"
                                :key="role"
                                @click="toggleRole(role)"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                    selectedRoles.includes(role)
                                        ? 'bg-purple-500 text-white hover:bg-purple-600'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ role }}
                            </button>
                        </div>
                    </div>

                    <!-- Verification Status Filter -->
                    <div class="flex-1 p-4 bg-gray-50 rounded-lg border border-gray-100 dark:bg-gray-800/50 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Verification</h3>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="status in ['verified', 'unverified']"
                                :key="status"
                                @click="toggleVerification(status)"
                                :class="[
                                    'px-3 py-1.5 rounded-full text-sm font-medium transition-colors',
                                    selectedVerificationStatus.includes(status)
                                        ? 'bg-purple-500 text-white hover:bg-purple-600'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                ]"
                            >
                                {{ status }}
                            </button>
                        </div>
                    </div>

                    <!-- Reset Filter -->
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-100 dark:bg-gray-800/50 dark:border-gray-700 flex items-end">
                        <button
                            @click="resetFilters"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-full hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 transition-colors"
                        >
                            Reset All
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('name')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Name</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('name') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('email')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Email</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('email') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('tenant')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Tenant</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('tenant') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('email_verified_at')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('role') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('role')">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Role</span>
                                        <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('email_verified_at') }}</span>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-right">
                                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                                    {{ user.name ? user.name.charAt(0).toUpperCase() : '?' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">#{{ user.id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ user.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ user.tenant_id }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="user.deleted_at" 
                                          class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100">
                                        Deleted
                                    </span>
                                    <span v-else-if="user.email_verified_at" 
                                          class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">
                                        Verified
                                    </span>
                                    <span v-else 
                                          class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100">
                                        Unverified
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select 
                                        :value="user.current_role"
                                        @change="updateUserRole(user.id, user.tenant_id, $event.target.value)"
                                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                    >
                                        <option v-if="!user.current_role" value="">No Role</option>
                                        <option v-for="role in availableRoles" :key="role" :value="role" :selected="role === user.current_role">
                                            {{ role }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link
                                        :href="route('central.users.edit', { user: user.id })"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-4"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteUser(user.id)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div class="flex justify-center gap-2">
                        <template v-for="link in users.links" :key="link.label">
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

            <!-- New User Form -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">Add New User</h3>
                
                <form @submit.prevent="submit" class="space-y-4 max-w-2xl">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                            required
                        />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                            required
                        />
                    </div>
                    <div>
                        <button
                            type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800"
                            :disabled="form.processing"
                        >
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

