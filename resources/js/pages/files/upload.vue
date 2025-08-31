<template>
    <AppLayout>
        <Head title="Upload Files" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Upload Files
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <!-- Dropzone Area -->
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        :class="[
                            'border-2 border-dashed rounded-lg p-12 text-center transition-all',
                            isDragging 
                                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' 
                                : 'border-gray-300 dark:border-gray-700'
                        ]"
                    >
                        <div v-if="!files.length">
                            <svg 
                                class="mx-auto h-12 w-12 text-gray-400" 
                                stroke="currentColor" 
                                fill="none" 
                                viewBox="0 0 48 48"
                            >
                                <path 
                                    d="M24 8L24 32M16 16L24 8L32 16" 
                                    stroke-width="2" 
                                    stroke-linecap="round"
                                />
                                <path 
                                    d="M8 32V36C8 37.1046 8.89543 38 10 38H38C39.1046 38 40 37.1046 40 36V32" 
                                    stroke-width="2" 
                                    stroke-linecap="round"
                                />
                            </svg>
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                Drag and drop files here, or
                                <button 
                                    type="button" 
                                    class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 font-medium"
                                    @click="$refs.fileInput.click()"
                                >
                                    browse
                                </button>
                            </p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                Supported file types: PDF, Images, Documents (max 10MB each)
                            </p>
                        </div>
                        <div v-else class="space-y-4">
                            <TransitionGroup 
                                name="list" 
                                tag="ul" 
                                class="space-y-2"
                            >
                                <li 
                                    v-for="(file, index) in files" 
                                    :key="file.name"
                                    class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded"
                                >
                                    <div class="flex items-center space-x-3">
                                        <span class="text-gray-600 dark:text-gray-300">{{ file.name }}</span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatFileSize(file.size) }}
                                        </span>
                                    </div>
                                    <button 
                                        @click="removeFile(index)"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    >
                                        Remove
                                    </button>
                                </li>
                            </TransitionGroup>
                            <button 
                                type="button" 
                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 text-sm font-medium"
                                @click="$refs.fileInput.click()"
                            >
                                Add more files
                            </button>
                        </div>
                    </div>

                    <!-- Hidden File Input -->
                    <input
                        type="file"
                        ref="fileInput"
                        @change="handleFileSelect"
                        multiple
                        class="hidden"
                        accept=".pdf,.doc,.docx,.txt,image/*"
                    >

                    <!-- File Descriptions -->
                    <div v-if="files.length" class="mt-6 space-y-4">
                        <div v-for="(file, index) in files" :key="file.name">
                            <label :for="'description-'+index" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description for {{ file.name }} (optional)
                            </label>
                            <textarea
                                :id="'description-'+index"
                                v-model="descriptions[index]"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Upload Button -->
                    <div class="mt-6 flex justify-end space-x-4">
                        <Link
                            :href="route('files.index', { tenant: $page.props.auth.user.tenant_id })"
                            class="btn-secondary"
                        >
                            Cancel
                        </Link>
                        <button
                            @click="uploadFiles"
                            :disabled="isUploading || !files.length"
                            :class="[
                                'btn-primary',
                                (isUploading || !files.length) && 'opacity-50 cursor-not-allowed'
                            ]"
                        >
                            <svg
                                v-if="isUploading"
                                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            {{ isUploading ? 'Uploading...' : 'Upload Files' }}
                        </button>
                    </div>

                    <!-- Progress Section -->
                    <div v-if="uploadProgress.length" class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                            Upload Progress
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="(progress, index) in uploadProgress"
                                :key="index"
                                class="flex items-center space-x-4"
                            >
                                <div class="flex-1">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ files[index].name }}
                                        </span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ progress }}%
                                        </span>
                                    </div>
                                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full">
                                        <div
                                            class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                                            :style="{ width: `${progress}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const files = ref<File[]>([]);
const descriptions = ref<string[]>([]);
const isUploading = ref(false);
const uploadProgress = ref<number[]>([]);

function handleDrop(e: DragEvent) {
    isDragging.value = false;
    if (!e.dataTransfer?.files) return;
    
    const newFiles = Array.from(e.dataTransfer.files);
    addFiles(newFiles);
}

function handleFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files) return;
    
    const newFiles = Array.from(input.files);
    addFiles(newFiles);
    input.value = ''; // Reset input
}

function addFiles(newFiles: File[]) {
    // Validate file size and type
    const validFiles = newFiles.filter(file => {
        const isValidSize = file.size <= 10 * 1024 * 1024; // 10MB
        const isValidType = /^(image\/|application\/pdf|application\/msword|application\/vnd.openxmlformats-officedocument.wordprocessingml.document|text\/plain)/i.test(file.type);
        
        return isValidSize && isValidType;
    });

    files.value.push(...validFiles);
    descriptions.value.push(...Array(validFiles.length).fill(''));
}

function removeFile(index: number) {
    files.value.splice(index, 1);
    descriptions.value.splice(index, 1);
    uploadProgress.value.splice(index, 1);
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function uploadFiles() {
    if (!files.value.length || isUploading.value) return;
    
    isUploading.value = true;
    uploadProgress.value = Array(files.value.length).fill(0);

    try {
        // Upload files sequentially
        for (let i = 0; i < files.value.length; i++) {
            const formData = new FormData();
            formData.append('file', files.value[i]);
            formData.append('description', descriptions.value[i]);

            await new Promise((resolve, reject) => {
                router.post(
                    route('files.store', { tenant: route().params.tenant }),
                    formData,
                    {
                        forceFormData: true,
                        onProgress: (progress) => {
                            uploadProgress.value[i] = Math.round((progress.loaded * 100) / progress.total);
                        },
                        onSuccess: () => resolve(null),
                        onError: reject,
                    }
                );
            });
        }

        // Redirect to files index on success
        router.visit(route('files.index', { tenant: route().params.tenant }), {
            only: ['files'],
        });
    } catch (error) {
        console.error('Upload failed:', error);
    } finally {
        isUploading.value = false;
    }
}
</script>

