<!-- <template>
    <div class="text-lg font-bold text-gray-800 whitespace-nowrap">
        Yuri Agent Management
    </div>
</template> -->

<script setup>
defineProps({
    showText: {
        type: Boolean,
        default: true, // Defaultnya tulisan muncul (buat di Login)
    },
});
</script>

<template>
    <div class="flex items-center gap-3">
        <!-- <svg
            viewBox="0 0 316 316"
            xmlns="http://www.w3.org/2000/svg"
            class="h-8 w-8 fill-current"
        >
            <path
                d="M305.8 81.125C305.77 80.9932 305.69 80.875 305.572 80.7909C305.455 80.7069 305.307 80.6629 305.156 80.6667H10.8438C10.6924 80.6629 10.5443 80.7069 10.427 80.7909C10.3097 80.875 10.2299 80.9932 10.2 81.125L2 112H314L305.8 81.125Z"
            />
        </svg> -->

        <span v-if="showText" class="font-bold text-lg whitespace-nowrap">
            Yuri Agent Management
        </span>
    </div>
</template>
