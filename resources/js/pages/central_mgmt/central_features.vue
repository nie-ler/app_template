<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import { ref } from 'vue';


interface Feature {
  id: number;
  code: string;
  name: string;
  description: string;
}

const props = defineProps<{
  plan: {
    id: number;
    name: string;
    features: any[];
  };
  
  filters: {
    sort: string;
    direction: string;
  };
}>();


const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Plans',
    href: route('plans.index'),
  },
  {
    title: `Features - ${props.plan.name}`,
    href: route('plans.features', { plan: props.plan.id }),
  },
];


// Sorting functions
function sort(field: string) {
  router.get(
    route('tenant.plans.features', {
      plan: props.plan.id,
      sort: field,
      direction: props.filters.sort === field && props.filters.direction === 'asc' ? 'desc' : 'asc',
    }),
    {},
    { preserveState: true }
  );
}

function getSortIcon(field: string) {
  if (props.sort !== field) return '↕';
  return props.filters.direction === 'asc' ? '↑' : '↓';
}

// Modal states and forms
const showAddFeatureModal = ref(false);
const showDeleteModal = ref(false);
const editingFeature = ref<Feature | null>(null);
const featureToDelete = ref<Feature | null>(null);

const featureForm = ref({
  name: '',
  description: ''
});


const deleteFeature = () => {
  if (featureToDelete.value) {
    router.delete(`/plans/${props.plan.id}/features/${featureToDelete.value.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        showDeleteModal.value = false
        featureToDelete.value = null
      }
    })
  }
}




interface Feature {
  id: number;
  name: string;
  description: string;
}



function editFeature(feature: Feature) {
  editingFeature.value = feature;
  featureForm.value = {
    name: feature.name,
    description: feature.description
  };
  showAddFeatureModal.value = true;
}

function confirmDeleteFeature(feature: Feature) {
  featureToDelete.value = feature;
  showDeleteModal.value = true;
}

function closeFeatureModal() {
  showAddFeatureModal.value = false;
  editingFeature.value = null;
  featureForm.value = {
    name: '',
    description: ''
  };
}

function saveFeature() {
  if (editingFeature.value) {
    router.put(`/plans/${props.plan.id}/features/${editingFeature.value.id}`, featureForm.value, {
      preserveScroll: true,
      onSuccess: () => closeFeatureModal()
    });
  } else {
    router.post(`/plans/${props.plan.id}/features`, featureForm.value, {
      preserveScroll: true,
      onSuccess: () => closeFeatureModal()
    });
  }
}

// Feature management functions

</script>

<template>
  <Head :title="`Plan Features - ${props.plan.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Plan Features</h2>
          <div class="flex gap-4 items-center">
            <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100 rounded-full">
              {{ props.plan.name }}
            </span>
            <button
              @click="showAddFeatureModal = true"
              class="px-4 py-2 text-white bg-blue-500 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
            >
              Add Feature
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
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Feature</span>
                    <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('name') }}</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('code')">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Code</span>
                    <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('code') }}</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('description')">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Description</span>
                    <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">{{ getSortIcon('description') }}</span>
                  </div>
                </th>
                <th class="px-6 py-3 text-left">
                  <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
              <tr v-for="feature in plan.features" :key="feature.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ feature.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ feature.code }}</td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-300">{{ feature.description }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button
                    @click="editFeature(feature)"
                    class="mr-3 text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    Edit
                  </button>
                  <button
                    @click="confirmDeleteFeature(feature)"
                    class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
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
           
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Feature Modal -->
    <Modal :show="showAddFeatureModal" @close="closeFeatureModal">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
          {{ editingFeature ? 'Edit Feature' : 'Add New Feature' }}
        </h3>
        <form @submit.prevent="saveFeature">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input
              type="text"
              v-model="featureForm.name"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              required
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
            <input
              type="text"
              v-model="featureForm.code"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
              required
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
            <textarea
              v-model="featureForm.description"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            ></textarea>
          </div>
          <div class="flex justify-end gap-4">
            <button
              type="button"
              @click="closeFeatureModal"
              class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-white bg-blue-500 rounded-md hover:bg-blue-600 transition-colors"
            >
              {{ editingFeature ? 'Update' : 'Add' }} Feature
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Confirm Delete</h3>
        <p class="mb-4 text-gray-600 dark:text-gray-400">
          Are you sure you want to delete this feature? This action cannot be undone.
        </p>
        <div class="flex justify-end gap-4">
          <button
            @click="showDeleteModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="deleteFeature"
            class="px-4 py-2 text-white bg-red-500 rounded-md hover:bg-red-600 transition-colors"
          >
            Delete
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>


