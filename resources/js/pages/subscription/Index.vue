<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CalendarDays, CreditCard, CheckCircle2, AlertTriangle, Receipt, SquarePen, Calendar, Ban, ArrowUpDown } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Subscription',
        href: '/subscription',
    },
];



interface Invoice {
    id: string;
    number: string;
    amount_paid: number;
    currency: string;
    created: number;
    period_start: number;
    period_end: number;
    status: 'paid' | 'open' | 'void' | 'uncollectible';
    hosted_invoice_url: string;
}

interface Plan {
    id: string;
    name: string;
    amount: number;
    interval: 'month' | 'year';
    currency: string;
}

interface Subscription {
    name: string;
    status: string;
    trial_ends_at: string | null;
    current_period_start: string;
    current_period_end: string;
    plan: {
        name: string;
        amount: number;
        interval: 'month' | 'year';
        currency: string;
    };
    payment_method: {
        brand: string;
        last4: string;
        exp_month: number;
        exp_year: number;
    } | null;
    upcoming_invoice: {
        amount_due: number;
        currency: string;
        period_start: number;
        period_end: number;
    } | null;
    invoices: Invoice[];
    available_plans: Plan[];
}

const props = defineProps<{
    subscription: Subscription;
    tenant: any[];
}>();

const loading = ref(false);

// Actions
const changePlan = async (planId: string) => {
    loading.value = true;
    try {
        await router.post(`/${tenant.value}/subscription/change-plan`, { plan: planId });
    } catch (error) {
        console.error('Failed to change plan:', error);
    } finally {
        loading.value = false;
    }
};

const changePaymentCycle = async (interval: 'month' | 'year') => {
    loading.value = true;
    try {
        await router.post(`/${tenant.value}/subscription/change-cycle`, { interval });
    } catch (error) {
        console.error('Failed to change payment cycle:', error);
    } finally {
        loading.value = false;
    }
};

const cancelSubscription = async () => {
    if (!confirm('Are you sure you want to cancel your subscription? This action cannot be undone.')) return;
    
    loading.value = true;
    try {
        await router.delete(`/${tenant.value}/subscription`);
    } catch (error) {
        console.error('Failed to cancel subscription:', error);
    } finally {
        loading.value = false;
    }
};

const updatePaymentMethod = async () => {
    loading.value = true;
    try {
        const { url } = await router.post(`/${tenant.value}/subscription/update-payment`, {}, {
            only: ['url']
        });
        window.location.href = url;
    } catch (error) {
        console.error('Failed to init payment update:', error);
    } finally {
        loading.value = false;
    }
};

// Format currency helper
const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: currency
    }).format(amount / 100);
};

// Format date helper
const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Computed properties for status
const statusColor = computed(() => {
    switch (props.subscription.status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100';
        case 'trialing':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100';
        case 'past_due':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100';
        default:
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100';
    }
});
</script>

<template>
    <Head title="Subscription" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <!-- Subscription Status Card -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold">Current Subscription</h2>
                        <span :class="[
                            'px-3 py-1 rounded-full text-sm font-medium',
                            statusColor
                        ]">
                            {{ props.subscription.status.charAt(0).toUpperCase() + props.subscription.status.slice(1) }}
                        </span>
                    </div>
                </div>

                <!-- Plan Details -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                            <CheckCircle2 class="h-6 w-6 text-blue-600 dark:text-blue-300" />
                        </div>
                        <div>
                            <h3 class="font-medium">{{ props.subscription.plan.name }}</h3>
                            <p class="text-2xl font-semibold mt-2">
                                {{ formatCurrency(props.subscription.plan.amount, props.subscription.plan.currency) }}
                                <span class="text-sm text-gray-500 dark:text-gray-400">/{{ props.subscription.plan.interval }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Subscription Period -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                            <CalendarDays class="h-6 w-6 text-purple-600 dark:text-purple-300" />
                        </div>
                        <div>
                            <h3 class="font-medium">Subscription Period</h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                From: {{ formatDate(new Date(props.subscription.current_period_start * 1000).toISOString()) }}<br>
                                To: {{ formatDate(new Date(props.subscription.current_period_end * 1000).toISOString()) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div v-if="props.subscription.payment_method" class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                    <div class="flex items-start gap-4">
                        <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                            <CreditCard class="h-6 w-6 text-green-600 dark:text-green-300" />
                        </div>
                        <div>
                            <h3 class="font-medium">Payment Method</h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                {{ props.subscription.payment_method.brand.charAt(0).toUpperCase() + props.subscription.payment_method.brand.slice(1) }}
                                ending in {{ props.subscription.payment_method.last4 }}<br>
                                Expires: {{ props.subscription.payment_method.exp_month }}/{{ props.subscription.payment_method.exp_year }}
                            </p>
                        </div>
                    </div>
                </div>


            </div>

            <!-- Trial Information if applicable -->
            <div v-if="props.subscription.trial_ends_at" class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-start gap-4">
                    <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900">
                        <AlertTriangle class="h-6 w-6 text-yellow-600 dark:text-yellow-300" />
                    </div>
                    <div>
                        <h3 class="font-medium">Trial Period</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Your trial period ends on {{ formatDate(props.subscription.trial_ends_at) }}
                        </p>
                    </div>
                </div>
            </div>


            <!-- Upcoming Invoice -->
            <div v-if="props.subscription.upcoming_invoice" class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <div class="flex items-start gap-4">
                    <div class="rounded-full bg-indigo-100 p-3 dark:bg-indigo-900">
                        <Receipt class="h-6 w-6 text-indigo-600 dark:text-indigo-300" />
                    </div>
                    <div>
                        <h3 class="font-medium">Upcoming Invoice</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Amount Due: {{ formatCurrency(props.subscription.upcoming_invoice.amount_due, props.subscription.upcoming_invoice.currency) }}<br>
                            Date: {{ formatDate(new Date(props.subscription.upcoming_invoice.period_start * 1000).toISOString()) }}
                        </p>
                    </div>
                </div>
            </div>            


            <!-- Invoice History -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <h3 class="text-lg font-medium mb-4">Invoice History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Invoice Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="invoice in props.subscription.invoices" :key="invoice.id" class="text-sm">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-300">{{ invoice.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-300">
                                    {{ formatDate(new Date(invoice.created * 1000).toISOString()) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-gray-300">
                                    {{ formatCurrency(invoice.amount_paid, invoice.currency) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="{
                                        'px-2 py-1 text-xs rounded-full': true,
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100': invoice.status === 'paid',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100': invoice.status === 'open',
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100': ['void', 'uncollectible'].includes(invoice.status)
                                    }">
                                        {{ invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1) }}
                                    </span>
                                </td>                            
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <a 
                                        :href="invoice.hosted_invoice_url" 
                                        target="_blank"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        View Invoice
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Subscription Management -->
            <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl border border-sidebar-border/70 p-6">
                <h3 class="text-lg font-medium mb-4">Subscription Management</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Upgrade/Change Plan -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Change Plan</label>
                        <select 
                            :value="props.subscription.plan.name"
                            @change="changePlan($event.target.value)"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                            :disabled="loading"
                        >
                            <option v-for="plan in props.subscription.available_plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} - {{ formatCurrency(plan.amount, plan.currency) }}/{{ plan.interval }}
                            </option>
                        </select>
                    </div>

                    <!-- Payment Cycle -->
                    <button 
                        @click="changePaymentCycle(props.subscription.plan.interval === 'month' ? 'year' : 'month')"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                        :disabled="loading"
                    >
                        <ArrowUpDown class="h-5 w-5 mr-2" />
                        Switch to {{ props.subscription.plan.interval === 'month' ? 'Annual' : 'Monthly' }} Billing
                    </button>

                    <!-- Update Payment Method -->
                    <button 
                        @click="updatePaymentMethod"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                        :disabled="loading"
                    >
                        <SquarePen class="h-5 w-5 mr-2" />
                        Update Payment Method
                    </button>

                    <!-- Cancel Subscription -->
                    <button 
                        @click="cancelSubscription"
                        class="inline-flex items-center px-4 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-700 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20"
                        :disabled="loading"
                    >
                        <Ban class="h-5 w-5 mr-2" />
                        Cancel Subscription
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
