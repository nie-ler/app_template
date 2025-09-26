<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { type NavItem } from '@/types';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';


const { tenant, user } = defineProps<{
  user: Object
  tenant: string
  availableRoles: string[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tenant Users',
        href: route('users.index', { tenant }),
    },
    {
        title: 'Edit User',
        href: route('users.edit', { user: user.id, tenant }),
    },
];

const form = useForm({
  name: user.name,
  email: user.email,
  password: '',
  password_confirmation: '',
})

function updateUser() {
  form.put(route('users.update', { user: user, tenant: tenant }))
}

function deleteUser(id) {
  if (confirm('Diesen Nutzer wirklich löschen?')) {
    form.delete(route('users.destroy', { user: id, tenant: tenant }))
  }
}

function updateUserRole(userId: number, tenantId: string, newRole: string) {
  router.post(route('users.assign-role', { user: userId, tenant: tenantId}), {
    role: newRole
  }, {
    preserveScroll: true,
    preserveState: true,
  });
}
</script>

<template>
    <Head :title="`Edit User - ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Edit User</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update user information for {{ user.name }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span v-if="user.email_verified_at" 
                              class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">
                            Email Verified
                        </span>
                        <span v-else 
                              class="px-3 py-1 text-sm font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100">
                            Email Unverified
                        </span>
                    </div>
                </div>

                <form @submit.prevent="updateUser" class="space-y-6 max-w-2xl">
                    <!-- User Information -->
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                :class="{ 'border-red-300 dark:border-red-500': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                :class="{ 'border-red-300 dark:border-red-500': form.errors.email }"
                                required
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password (optional)</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                                :class="{ 'border-red-300 dark:border-red-500': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300"
                            />
                        </div>

                        <div>
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

                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="submit"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800"
                            :disabled="form.processing"
                        >
                            Save Changes
                        </button>

                        <button
                            type="button"
                            @click="deleteUser(user.id)"
                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800"
                            :disabled="form.processing"
                        >
                            Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
