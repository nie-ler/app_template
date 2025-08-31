<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

interface Plan {
  id: number;
  name: string;
  description: string;
  price: number;
  is_active: boolean;
}

interface Feature {
  code: string;
  name: string;
  description: string;
  planAssignments: Record<number, boolean>;
}

const props = defineProps<{
  plans: Plan[];
  features: Feature[];
  filters: {
    sort: string;
    direction: string;
  };
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Plans Management',
    href: route('plans.index'),
  },
];

// Sorting functions
function sort(field: string) {
  router.get(
    route('plans.index', {
      sort: field,
      direction: props.filters.sort === field && props.filters.direction === 'asc' ? 'desc' : 'asc',
    }),
    {},
    { preserveState: true }
  );
}

function getSortIcon(field: string) {
  if (props.filters.sort !== field) return '↕';
  return props.filters.direction === 'asc' ? '↑' : '↓';
}

function toggleFeature(feature: Feature, planId: number) {
  router.post(
    route('plans.features.toggle', { 
      plan: planId,
      feature: feature.code 
    }),
    {},
    { preserveState: true }
  );
}
</script>

<template>
  <Head title="Plans Management" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Plan Features Matrix</h2>
          <button
            @click="$router.push(route('tenant.plans.create'))"
            class="px-4 py-2 text-white bg-blue-500 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
          >
            Add New Plan
          </button>
        </div>

        <!-- Feature Matrix Table -->
        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                  <th class="px-6 py-3 text-left cursor-pointer group" @click="sort('name')">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Feature</span>
                      <span class="text-gray-400 dark:text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                        {{ getSortIcon('name') }}
                      </span>
                    </div>
                  </th>
                  <th 
                    v-for="plan in plans" 
                    :key="plan.id" 
                    class="px-6 py-3 text-center"
                  >
                    <div class="space-y-1">
                      <div class="text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                        {{ plan.name }}
                      </div>
                      <div class="text-xs text-gray-400">${{ plan.price }}/month</div>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                <tr v-for="feature in features" :key="feature.code" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td class="px-6 py-4">
                    <div>
                      <div class="font-medium text-gray-900 dark:text-gray-100">{{ feature.name }}</div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">{{ feature.description }}</div>
                    </div>
                  </td>
                  <td 
                    v-for="plan in plans" 
                    :key="plan.id"
                    class="px-6 py-4 text-center"
                  >
                    <button
                      @click="toggleFeature(feature, plan.id)"
                      class="inline-flex items-center justify-center w-8 h-8 transition-colors"
                      :class="{
                        'text-green-500 hover:text-green-600': feature.planAssignments[plan.id],
                        'text-gray-400 hover:text-gray-500': !feature.planAssignments[plan.id]
                      }"
                    >
                      <CheckCircleIcon v-if="feature.planAssignments[plan.id]" class="w-6 h-6" />
                      <XCircleIcon v-else class="w-6 h-6" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

