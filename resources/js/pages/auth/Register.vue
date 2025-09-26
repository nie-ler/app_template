<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    planId?: number | string;
    planSlug?: string;
}>();

// Redirect to home if planId is not set or empty
if (!props.planId || props.planId === '') {
    window.location.href = '/';
}

const form = useForm({
    name: '',
    email: '',
    company: '',
    password: '',
    password_confirmation: '',
    plan_id: props.planId || '', // Include planId in the form data
})

function submit() {
  form.post(route('register'))
}
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />
        
        <!-- General error messages -->
        <div v-if="Object.keys(form.errors).length > 0">
            <!-- Display known error types -->
            <div v-if="form.errors.tenant" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.tenant }}</div>
            <div v-if="form.errors.user" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.user }}</div>
            <div v-if="form.errors.stripe" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.stripe }}</div>
            <div v-if="form.errors.subscription" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.subscription }}</div>
            <div v-if="form.errors.payment" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.payment }}</div>
            <div v-if="form.errors.plan" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.plan }}</div>
            <div v-if="form.errors.setup" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">{{ form.errors.setup }}</div>
            
            <!-- Display any other errors that might not be covered above -->
            <div v-for="(message, key) in form.errors" :key="key">
                <!-- Only show errors not already displayed above -->
                <template v-if="!['tenant', 'user', 'stripe', 'subscription', 'payment', 'plan', 'setup', 'name', 'email', 'company', 'password', 'password_confirmation'].includes(key)">
                    <div v-if="form.errors.setup" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <strong>{{ key }}:</strong> {{ message }}
                    </div>
                </template>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name {{props.planSlug}}</Label>
                    <Input 
                        id="name" 
                        type="text" 
                        required autofocus :tabindex="1" 
                        autocomplete="name" 
                        v-model="form.name" 
                        placeholder="your full name" 
                    />
                    <InputError :message="form.errors.name ? form.errors.name[0] : null" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">Organisation Name*</Label>
                    <Input 
                        id="company" 
                        type="text" 
                        :tabindex="2" 
                        v-model="form.company" 
                        placeholder="Name of your organisation" 
                    />
                    <InputError :message="form.errors.company ? form.errors.company[0] : null" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        required :tabindex="3" 
                        autocomplete="email" 
                        v-model="form.email" 
                        placeholder="email@example.com" 
                    />
                    <InputError :message="form.errors.email ? form.errors.email[0] : null" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password ? form.errors.password[0] : null" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation ? form.errors.password_confirmation[0] : null" />
                </div>

                <!-- Hidden field for plan_id -->
                <Input
                    v-if="props.planId"
                    type="hidden"
                    v-model="form.plan_id"
                />

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="6">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
