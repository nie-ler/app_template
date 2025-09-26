<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Modal from '@/components/Modal.vue';
//import Pagination from '@/components/Pagination.vue';
import debounce from 'lodash/debounce';

const props = defineProps<{
    files: {
        data: any[];
        links: any[];
    };
    tenant: string;
    filters: {
        search?: string;
        mime_type?: string;
        sort?: string;
        direction?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Files',
        href: route('tenant.files.show', { tenant: props.tenant }),
    },
];

const showUploadModal = ref(false);
const showDeleteModal = ref(false);
const search = ref(props.filters.search || '');
const selectedType = ref(props.filters.mime_type || '');
const fileToDelete = ref(null);

const form = useForm({
    file: null as File | null,
    description: '',
});

const deleteForm = useForm({});

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString();
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.file = input.files[0];
    }
}

function submitFile() {
    if (!form.file) return;

    form.post(route('tenant.files.store', { tenant: route().params.tenant }), {
        preserveScroll: true,
        onSuccess: () => {
            showUploadModal.value = false;
            form.reset();
        },
    });
}

function confirmDelete(file: any) {
    fileToDelete.value = file;
    showDeleteModal.value = true;
}

function deleteFile() {
    if (!fileToDelete.value) return;

    deleteForm.delete(route('tenant.files.destroy', { 
        tenant: route().params.tenant,
        file: fileToDelete.value.id 
    }), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            fileToDelete.value = null;
        },
    });
}

const debouncedSearch = debounce(() => {
    router.get(route('tenant.files.index', { tenant: route().params.tenant }), {
        search: search.value,
        mime_type: selectedType.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

function filterByType() {
    router.get(route('tenant.files.index', { tenant: route().params.tenant }), {
        search: search.value,
        mime_type: selectedType.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function sort(field: string) {
    router.get(route('tenant.files.index', { tenant: route().params.tenant }), {
        search: search.value,
        mime_type: selectedType.value,
        sort: field,
        direction: props.filters.sort === field && props.filters.direction === 'asc' ? 'desc' : 'asc',
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function getSortIcon(field: string) {
    if (props.filters.sort !== field) return '↕';
    return props.filters.direction === 'asc' ? '↑' : '↓';
}
</script>



<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- User List -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Tenant File Management</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage files for {{props.tenant}}</p>
                    </div>
                    <span class="px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100 rounded-full">
                        {{props.files.data.length}} Files
                    </span>
                </div>


            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Files
                </h2>
                <button
                    @click="showUploadModal = true"
                    class="btn-primary"
                >
                    Upload Files
                </button>
            </div>



        <div class="py-12">
            <div class="max-w-7xl mx-auto lg:px-8">
                <!-- Filters -->
                <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search files..."
                                class="w-full input-control"
                                @input="debouncedSearch"
                            >
                        </div>
                        <select v-model="selectedType" class="input-control" @change="filterByType">
                            <option value="">All Types</option>
                            <option value="image/">Images</option>
                            <option value="application/pdf">PDFs</option>
                            <option value="text/">Text</option>
                            <option value="application/">Documents</option>
                        </select>
                    </div>
                </div>

                <!-- Files List -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="table-header" @click="sort('original_name')">
                                    Name {{ getSortIcon('original_name') }}
                                </th>
                                <th scope="col" class="table-header">Type</th>
                                <th scope="col" class="table-header" @click="sort('size')">
                                    Size {{ getSortIcon('size') }}
                                </th>
                                <th scope="col" class="table-header" @click="sort('created_at')">
                                    Uploaded {{ getSortIcon('created_at') }}
                                </th>
                                <th scope="col" class="table-header">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="file in files.data" :key="file.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="table-cell">{{ file.original_name }}</td>
                                <td class="table-cell">{{ file.mime_type }}</td>
                                <td class="table-cell">{{ formatFileSize(file.size) }}</td>
                                <td class="table-cell">{{ formatDate(file.created_at) }}</td>
                                <td class="table-cell">
                                    <div class="flex gap-2">
                                        <Link :href="route('tenant.files.preview', { tenant: $page.props.auth.user.tenant_id, file: file.id })" 
                                              class="btn-secondary" target="_blank">
                                            Preview
                                        </Link>
                                        <Link :href="route('tenant.files.download', { tenant: $page.props.auth.user.tenant_id, file: file.id })" 
                                              class="btn-secondary">
                                            Download
                                        </Link>
                                        <button @click="confirmDelete(file)" class="btn-danger">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <!--
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
                </div>-->
            </div>
        </div>
                     </div>
              </div>

        <!-- Upload Modal -->
        <Modal :show="showUploadModal" @close="showUploadModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Upload File
                </h2>

                <form @submit.prevent="submitFile" class="mt-6">
                    <div>
                        <input
                            type="file"
                            ref="fileInput"
                            class="input-control"
                            @change="handleFileChange"
                        >
                    </div>

                    <div class="mt-4">
                        <textarea
                            v-model="form.description"
                            placeholder="Description (optional)"
                            class="input-control"
                            rows="3"
                        ></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-4">
                        <button type="button" class="btn-secondary" @click="showUploadModal = false">
                            Cancel
                        </button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            Upload
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Delete File
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete this file? This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end gap-4">
                    <button type="button" class="btn-secondary" @click="showDeleteModal = false">
                        Cancel
                    </button>
                    <button type="button" class="btn-danger" @click="deleteFile" :disabled="deleteForm.processing">
                        Delete File
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>




